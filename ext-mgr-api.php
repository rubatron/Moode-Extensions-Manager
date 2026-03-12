<?php
// ext-mgr JSON API endpoint with maintenance actions.
header('Content-Type: application/json; charset=utf-8');

$action = $_REQUEST['action'] ?? 'list';
$registryPath = __DIR__ . DIRECTORY_SEPARATOR . 'registry.json';
$metaPath = __DIR__ . DIRECTORY_SEPARATOR . 'ext-mgr.meta.json';
$versionPath = __DIR__ . DIRECTORY_SEPARATOR . 'ext-mgr.version';
$releasePath = __DIR__ . DIRECTORY_SEPARATOR . 'ext-mgr.release.json';

function defaultMeta() {
    return [
        'name' => 'Extension Manager',
        'slug' => 'ext-mgr',
        'version' => '0.0.0-dev',
        'latestVersion' => '0.0.0-dev',
        'creator' => 'Rubatron Team',
        'license' => 'GPL-3.0-or-later',
        'description' => 'Central manager for moOde extension discovery and pin state.',
        'releaseFocus' => '1.2',
        'updateIntegration' => [
            'provider' => 'github',
            'repository' => 'rubatron/Moode-Extensions-Manager',
            'signatureVerification' => 'planned',
            'systemSettingsHook' => 'api-managed',
        ],
        'updated' => date('Y-m-d'),
        'maintenance' => [
            'lastAction' => 'none',
            'lastResult' => 'n/a',
            'lastRunAt' => null,
        ],
    ];
}

function defaultReleasePolicy() {
    return [
        'schemaVersion' => '2',
        'channel' => 'dev',
        'latestVersion' => '0.0.0-dev',
        'provider' => 'github',
        'repository' => 'rubatron/Moode-Extensions-Manager',
        'signatureVerification' => 'planned',
        'checksumAlgorithm' => 'sha256',
        'integrityManifestPath' => 'ext-mgr.integrity.json',
        'systemSettingsHook' => 'api-managed',
        'releaseSelection' => 'channel-aware',
        'prereleaseStrategy' => 'prefer-prerelease',
        'notes' => 'Release policy for ext-mgr self-update via provider metadata.',
        'managedFiles' => [
            'ext-mgr.php',
            'ext-mgr-api.php',
            'ext-mgr.meta.json',
            'ext-mgr.release.json',
            'ext-mgr.version',
            'assets/js/ext-mgr.js',
            'scripts/ext-mgr-import-wizard.sh',
            'README.md',
        ],
    ];
}

function isSafeManagedPath($filePath) {
    if (!is_string($filePath)) {
        return false;
    }
    $clean = trim(str_replace('\\', '/', $filePath));
    if ($clean === '') {
        return false;
    }
    if (strpos($clean, '..') !== false) {
        return false;
    }
    if (strpos($clean, ':') !== false) {
        return false;
    }
    if (substr($clean, 0, 1) === '/') {
        return false;
    }
    return true;
}

function normalizeReleasePolicy($policy) {
    $defaults = defaultReleasePolicy();
    if (!is_array($policy)) {
        $policy = [];
    }

    $normalized = array_replace_recursive($defaults, $policy);

    $allowedChannels = ['dev', 'beta', 'stable'];
    if (!in_array($normalized['channel'], $allowedChannels, true)) {
        $normalized['channel'] = $defaults['channel'];
    }

    $allowedProviders = ['github', 'gitlab', 'custom'];
    if (!in_array($normalized['provider'], $allowedProviders, true)) {
        $normalized['provider'] = $defaults['provider'];
    }

    $allowedSigStates = ['planned', 'required', 'disabled'];
    if (!in_array($normalized['signatureVerification'], $allowedSigStates, true)) {
        $normalized['signatureVerification'] = $defaults['signatureVerification'];
    }

    $allowedAlgorithms = ['sha256'];
    if (!in_array((string)$normalized['checksumAlgorithm'], $allowedAlgorithms, true)) {
        $normalized['checksumAlgorithm'] = $defaults['checksumAlgorithm'];
    }

    if (!isset($normalized['integrityManifestPath']) || !isSafeManagedPath((string)$normalized['integrityManifestPath'])) {
        $normalized['integrityManifestPath'] = $defaults['integrityManifestPath'];
    } else {
        $normalized['integrityManifestPath'] = trim(str_replace('\\', '/', (string)$normalized['integrityManifestPath']));
    }

    if (!isset($normalized['managedFiles']) || !is_array($normalized['managedFiles'])) {
        $normalized['managedFiles'] = $defaults['managedFiles'];
    }

    $managedFiles = [];
    foreach ($normalized['managedFiles'] as $filePath) {
        if (!isSafeManagedPath($filePath)) {
            continue;
        }
        $clean = trim(str_replace('\\', '/', $filePath));
        $managedFiles[] = $clean;
    }
    $normalized['managedFiles'] = array_values(array_unique($managedFiles));
    if (count($normalized['managedFiles']) === 0) {
        $normalized['managedFiles'] = $defaults['managedFiles'];
    }

    return $normalized;
}

function normalizeVersion($value) {
    $version = trim((string)$value);
    if ($version === '') {
        return '';
    }
    if ($version[0] === 'v' || $version[0] === 'V') {
        $version = substr($version, 1);
    }
    return trim($version);
}

function safeVersionCompare($left, $right, $operator) {
    $leftNormalized = normalizeVersion($left);
    $rightNormalized = normalizeVersion($right);
    if ($leftNormalized === '' || $rightNormalized === '') {
        return false;
    }
    return version_compare($leftNormalized, $rightNormalized, $operator);
}

function safeHasUpdate($latestVersion, $currentVersion) {
    return safeVersionCompare($latestVersion, $currentVersion, '>');
}

function readJsonFile($path, $fallback) {
    if (!file_exists($path)) {
        return $fallback;
    }
    $json = file_get_contents($path);
    if ($json === false || trim($json) === '') {
        return $fallback;
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return $fallback;
    }
    return $data;
}

function writeJsonFile($path, $data) {
    $tmp = $path . '.tmp';
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        return false;
    }
    if (file_put_contents($tmp, $encoded . PHP_EOL) === false) {
        return false;
    }
    return rename($tmp, $path);
}

function readMeta($path) {
    $defaults = defaultMeta();
    $meta = readJsonFile($path, $defaults);
    // Shallow merge with defaults so missing keys remain available.
    $meta = array_replace_recursive($defaults, $meta);
    return $meta;
}

function readVersionValue($path) {
    if (!file_exists($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $version = trim($raw);
    return $version === '' ? null : $version;
}

function writeVersionValue($path, $version) {
    if (!is_string($version) || trim($version) === '') {
        return false;
    }
    return file_put_contents($path, trim($version) . PHP_EOL) !== false;
}

function writeTextFileAtomic($path, $content) {
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $content) === false) {
        return false;
    }
    return rename($tmp, $path);
}

function isPhpFunctionEnabled($name) {
    if (!function_exists($name)) {
        return false;
    }
    $disabled = ini_get('disable_functions');
    if (!is_string($disabled) || trim($disabled) === '') {
        return true;
    }
    $items = array_map('trim', explode(',', $disabled));
    return !in_array($name, $items, true);
}

function httpGetViaWget($url, &$error) {
    $error = '';

    if (!isPhpFunctionEnabled('exec')) {
        $error = 'wget fallback unavailable: exec() is disabled.';
        return null;
    }

    $whichOutput = [];
    $whichCode = 0;
    @exec('command -v wget 2>/dev/null', $whichOutput, $whichCode);
    if ($whichCode !== 0 || !is_array($whichOutput) || count($whichOutput) === 0) {
        $error = 'wget fallback unavailable: wget command not found.';
        return null;
    }

    $wgetPath = trim((string)$whichOutput[0]);
    if ($wgetPath === '') {
        $error = 'wget fallback unavailable: invalid wget path.';
        return null;
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'extmgr_wget_');
    if ($tmpFile === false) {
        $error = 'wget fallback unavailable: unable to allocate temp file.';
        return null;
    }

    $cmd = escapeshellarg($wgetPath)
        . ' --quiet --max-redirect=5 --timeout=20 -O '
        . escapeshellarg($tmpFile)
        . ' '
        . escapeshellarg($url)
        . ' 2>&1';

    $output = [];
    $exitCode = 0;
    @exec($cmd, $output, $exitCode);
    if ($exitCode !== 0) {
        $error = 'Network error via wget: ' . trim(implode("\n", $output));
        @unlink($tmpFile);
        return null;
    }

    $content = @file_get_contents($tmpFile);
    @unlink($tmpFile);
    if ($content === false) {
        $error = 'wget fallback failed to read downloaded content.';
        return null;
    }

    return $content;
}

function httpGet($url, &$error) {
    $error = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ext-mgr-updater/1.2');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            $error = 'Network error: ' . curl_error($ch);
            curl_close($ch);
            return null;
        }
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status >= 400) {
            $error = 'HTTP ' . $status . ' from upstream.';
            return null;
        }
        return $response;
    }

    $wgetError = '';
    $wgetResponse = httpGetViaWget($url, $wgetError);
    if ($wgetResponse !== null) {
        return $wgetResponse;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'ignore_errors' => true,
            'header' => "User-Agent: ext-mgr-updater/1.2\r\nAccept: application/vnd.github+json\r\nX-GitHub-Api-Version: 2022-11-28\r\n",
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        $error = 'Network error: unable to fetch resource. ' . ($wgetError !== '' ? 'Wget fallback: ' . $wgetError : '');
        return null;
    }

    $statusCode = 200;
    if (isset($http_response_header) && is_array($http_response_header) && count($http_response_header) > 0) {
        if (preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $statusCode = (int)$matches[1];
        }
    }

    if ($statusCode >= 400) {
        $error = 'HTTP ' . $statusCode . ' from upstream.';
        return null;
    }

    return $response;
}

function githubApiUrl($repository, $path) {
    $parts = explode('/', (string)$repository, 2);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
        return null;
    }
    return 'https://api.github.com/repos/' . rawurlencode($parts[0]) . '/' . rawurlencode($parts[1]) . $path;
}

function githubRawFileUrl($repository, $ref, $filePath) {
    $parts = explode('/', (string)$repository, 2);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
        return null;
    }

    $pathSegments = array_map('rawurlencode', explode('/', $filePath));
    return 'https://raw.githubusercontent.com/' . rawurlencode($parts[0]) . '/' . rawurlencode($parts[1]) . '/' . rawurlencode($ref) . '/' . implode('/', $pathSegments);
}

function chooseGithubReleaseByChannel($releases, $channel) {
    $stable = [];
    $prerelease = [];

    foreach ($releases as $release) {
        if (!is_array($release)) {
            continue;
        }

        $isDraft = !empty($release['draft']);
        $isPrerelease = !empty($release['prerelease']);
        if ($isDraft) {
            continue;
        }

        if ($isPrerelease) {
            $prerelease[] = $release;
        } else {
            $stable[] = $release;
        }
    }

    if ($channel === 'stable') {
        return $stable[0] ?? null;
    }

    if ($channel === 'beta') {
        return $prerelease[0] ?? ($stable[0] ?? null);
    }

    if ($channel === 'dev') {
        return $prerelease[0] ?? ($stable[0] ?? null);
    }

    return null;
}

function githubReleaseApiPathForChannel($channel) {
    if ($channel === 'stable') {
        return '/releases/latest';
    }
    return '/releases?per_page=30';
}

function resolveRemoteReleaseCandidate($policy, &$error) {
    $error = '';
    $provider = (string)($policy['provider'] ?? 'github');
    if ($provider !== 'github') {
        $error = 'Unsupported provider: ' . $provider;
        return null;
    }

    $repository = (string)($policy['repository'] ?? '');
    $repoParts = explode('/', $repository, 2);
    if (count($repoParts) !== 2 || $repoParts[0] === '' || $repoParts[1] === '') {
        $error = 'Invalid repository format in release policy.';
        return null;
    }

    $channel = (string)($policy['channel'] ?? 'dev');
    $apiPath = githubReleaseApiPathForChannel($channel);
    $apiUrl = githubApiUrl($repository, $apiPath);
    if ($apiUrl === null) {
        $error = 'Invalid repository format in release policy.';
        return null;
    }
    $networkError = '';
    $payload = httpGet($apiUrl, $networkError);
    if ($payload === null) {
        $error = $networkError;
        return null;
    }

    $decoded = json_decode($payload, true);
    if (!is_array($decoded)) {
        $error = 'Invalid JSON response from release provider.';
        return null;
    }

    $releases = isset($decoded['id']) ? [$decoded] : $decoded;
    $selected = chooseGithubReleaseByChannel($releases, $channel);
    if (!is_array($selected)) {
        $error = 'No release candidate found for channel: ' . $channel;
        return null;
    }

    $tag = trim((string)($selected['tag_name'] ?? ''));
    if ($tag === '') {
        $error = 'Selected release has no tag_name.';
        return null;
    }

    $version = normalizeVersion($tag);
    if ($version === '') {
        $error = 'Unable to derive version from release tag.';
        return null;
    }

    return [
        'provider' => 'github',
        'repository' => $repository,
        'channel' => $channel,
        'tag' => $tag,
        'version' => $version,
        'name' => (string)($selected['name'] ?? $tag),
        'publishedAt' => (string)($selected['published_at'] ?? ''),
        'prerelease' => !empty($selected['prerelease']),
        'draft' => !empty($selected['draft']),
    ];
}

function fetchManagedFilesFromRelease($policy, $candidate, &$error) {
    $error = '';
    $files = $policy['managedFiles'] ?? [];
    if (!is_array($files) || count($files) === 0) {
        $error = 'No managed files configured in release policy.';
        return null;
    }

    $repo = (string)($candidate['repository'] ?? '');
    $tag = (string)($candidate['tag'] ?? '');
    if ($repo === '' || $tag === '') {
        $error = 'Release candidate is missing repository/tag.';
        return null;
    }

    $payloads = [];
    foreach ($files as $filePath) {
        if (!isSafeManagedPath($filePath)) {
            $error = 'Unsafe managed file path: ' . $filePath;
            return null;
        }
        $clean = trim(str_replace('\\', '/', (string)$filePath));
        $url = githubRawFileUrl($repo, $tag, $clean);
        if ($url === null) {
            $error = 'Unable to build raw URL for ' . $clean;
            return null;
        }

        $networkError = '';
        $content = httpGet($url, $networkError);
        if ($content === null) {
            $error = 'Failed fetching ' . $clean . ': ' . $networkError;
            return null;
        }
        $payloads[$clean] = $content;
    }

    return $payloads;
}

function normalizeDigestValue($value) {
    $normalized = strtolower(trim((string)$value));
    if (strpos($normalized, 'sha256:') === 0) {
        $normalized = substr($normalized, 7);
    }
    return $normalized;
}

function fetchIntegrityManifestFromRelease($policy, $candidate, &$error) {
    $error = '';

    $repo = (string)($candidate['repository'] ?? '');
    $tag = (string)($candidate['tag'] ?? '');
    $manifestPath = trim(str_replace('\\', '/', (string)($policy['integrityManifestPath'] ?? 'ext-mgr.integrity.json')));

    if ($repo === '' || $tag === '') {
        $error = 'Release candidate is missing repository/tag for integrity manifest.';
        return null;
    }

    if (!isSafeManagedPath($manifestPath)) {
        $error = 'Unsafe integrity manifest path configured.';
        return null;
    }

    $url = githubRawFileUrl($repo, $tag, $manifestPath);
    if ($url === null) {
        $error = 'Unable to build integrity manifest URL.';
        return null;
    }

    $networkError = '';
    $payload = httpGet($url, $networkError);
    if ($payload === null) {
        $error = 'Failed fetching integrity manifest: ' . $networkError;
        return null;
    }

    $decoded = json_decode($payload, true);
    if (!is_array($decoded)) {
        $error = 'Integrity manifest is not valid JSON.';
        return null;
    }

    $algorithm = strtolower(trim((string)($decoded['algorithm'] ?? 'sha256')));
    if ($algorithm !== 'sha256') {
        $error = 'Unsupported integrity algorithm in manifest: ' . $algorithm;
        return null;
    }

    $files = $decoded['files'] ?? null;
    if (!is_array($files) || count($files) === 0) {
        $error = 'Integrity manifest does not contain files map.';
        return null;
    }

    $normalizedFiles = [];
    foreach ($files as $path => $digest) {
        $cleanPath = trim(str_replace('\\', '/', (string)$path));
        if (!isSafeManagedPath($cleanPath)) {
            continue;
        }
        $normalizedDigest = normalizeDigestValue($digest);
        if (!preg_match('/^[a-f0-9]{64}$/', $normalizedDigest)) {
            continue;
        }
        $normalizedFiles[$cleanPath] = $normalizedDigest;
    }

    if (count($normalizedFiles) === 0) {
        $error = 'Integrity manifest has no valid digests.';
        return null;
    }

    return [
        'path' => $manifestPath,
        'algorithm' => $algorithm,
        'files' => $normalizedFiles,
    ];
}

function verifyPayloadsAgainstManifest($payloads, $managedFiles, $manifest, &$error, &$details) {
    $error = '';
    $details = [
        'checked' => 0,
        'matched' => 0,
        'missingEntries' => [],
        'mismatches' => [],
    ];

    if (!is_array($manifest) || !isset($manifest['files']) || !is_array($manifest['files'])) {
        $error = 'Invalid manifest data for verification.';
        return false;
    }

    foreach ($managedFiles as $relativePath) {
        if (!isset($payloads[$relativePath]) || !is_string($payloads[$relativePath])) {
            $details['missingEntries'][] = $relativePath;
            continue;
        }

        $details['checked']++;
        if (!isset($manifest['files'][$relativePath])) {
            $details['missingEntries'][] = $relativePath;
            continue;
        }

        $expected = normalizeDigestValue($manifest['files'][$relativePath]);
        if (!preg_match('/^[a-f0-9]{64}$/', $expected)) {
            $details['mismatches'][] = $relativePath . ':invalid-manifest-digest';
            continue;
        }

        $actual = hash('sha256', $payloads[$relativePath]);
        if (!hash_equals($expected, $actual)) {
            $details['mismatches'][] = $relativePath;
            continue;
        }

        $details['matched']++;
    }

    if (count($details['mismatches']) > 0) {
        $error = 'Checksum mismatch for: ' . implode(', ', $details['mismatches']);
        return false;
    }

    if (count($details['missingEntries']) > 0) {
        $error = 'Manifest missing entries for: ' . implode(', ', $details['missingEntries']);
        return false;
    }

    return true;
}

function applyManagedFiles($baseDir, $payloads, &$error) {
    $error = '';
    if (!is_array($payloads) || count($payloads) === 0) {
        $error = 'No payloads to apply.';
        return false;
    }

    $backupRoot = $baseDir . DIRECTORY_SEPARATOR . '.ext-mgr-update-backups';
    $backupStamp = date('Ymd_His') . '_' . substr(sha1((string)microtime(true)), 0, 10);
    $backupDir = $backupRoot . DIRECTORY_SEPARATOR . $backupStamp;
    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        $error = 'Failed to create backup directory.';
        return false;
    }

    $fileOrder = array_keys($payloads);
    usort($fileOrder, static function ($a, $b) {
        if ($a === 'ext-mgr-api.php') {
            return 1;
        }
        if ($b === 'ext-mgr-api.php') {
            return -1;
        }
        return strcmp($a, $b);
    });

    $appliedFiles = [];

    $rollback = static function () use (&$appliedFiles, $baseDir, $backupDir) {
        for ($i = count($appliedFiles) - 1; $i >= 0; $i--) {
            $relativePath = $appliedFiles[$i];
            $fullPath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $backupPath = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            if (file_exists($backupPath)) {
                $dir = dirname($fullPath);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                @rename($backupPath, $fullPath);
            } else {
                @unlink($fullPath);
            }
        }
    };

    foreach ($fileOrder as $relativePath) {
        if (!isSafeManagedPath($relativePath)) {
            $rollback();
            $error = 'Unsafe managed file path: ' . $relativePath;
            return false;
        }

        if (!is_string($payloads[$relativePath])) {
            $rollback();
            $error = 'Invalid payload type for file: ' . $relativePath;
            return false;
        }

        $fullPath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $dir = dirname($fullPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            $rollback();
            $error = 'Failed to create directory: ' . $dir;
            return false;
        }

        $backupPath = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $backupParent = dirname($backupPath);
        if (!is_dir($backupParent) && !mkdir($backupParent, 0775, true) && !is_dir($backupParent)) {
            $rollback();
            $error = 'Failed to create backup parent directory: ' . $backupParent;
            return false;
        }

        if (file_exists($fullPath)) {
            if (!copy($fullPath, $backupPath)) {
                $rollback();
                $error = 'Failed to backup file: ' . $relativePath;
                return false;
            }
        }

        if (!writeTextFileAtomic($fullPath, $payloads[$relativePath])) {
            $rollback();
            $error = 'Failed to write file: ' . $relativePath;
            return false;
        }

        $appliedFiles[] = $relativePath;
    }

    // Keep only recent backup snapshots to limit disk growth.
    $backups = glob($backupRoot . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
    if (is_array($backups) && count($backups) > 5) {
        rsort($backups, SORT_STRING);
        $stale = array_slice($backups, 5);
        foreach ($stale as $staleDir) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($staleDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    @rmdir($item->getPathname());
                } else {
                    @unlink($item->getPathname());
                }
            }
            @rmdir($staleDir);
        }
    }

    return true;
}

function updateReleasePolicyFromCandidate($policyPath, $policy, $candidate) {
    if (!is_array($policy)) {
        return;
    }
    if (!is_array($candidate)) {
        return;
    }
    $policy['latestVersion'] = (string)($candidate['version'] ?? ($policy['latestVersion'] ?? '0.0.0-dev'));
    $policy['lastCheckedAt'] = date('c');
    $policy['lastResolvedTag'] = (string)($candidate['tag'] ?? '');
    writeJsonFile($policyPath, $policy);
}

function markMetaMaintenance($meta, $actionName, $result) {
    if (!is_array($meta)) {
        $meta = defaultMeta();
    }
    if (!isset($meta['maintenance']) || !is_array($meta['maintenance'])) {
        $meta['maintenance'] = [];
    }
    $meta['maintenance']['lastAction'] = $actionName;
    $meta['maintenance']['lastResult'] = $result;
    $meta['maintenance']['lastRunAt'] = date('c');
    return $meta;
}

function readReleasePolicy($path) {
    $policy = readJsonFile($path, defaultReleasePolicy());
    return normalizeReleasePolicy($policy);
}

function buildMeta($metaPath, $versionPath, $releasePath) {
    $meta = readMeta($metaPath);
    $policy = readReleasePolicy($releasePath);
    $currentVersion = readVersionValue($versionPath);

    if ($currentVersion !== null) {
        $meta['version'] = $currentVersion;
    }

    $meta['latestVersion'] = (string)($policy['latestVersion'] ?? $meta['latestVersion']);
    $meta['updateIntegration'] = [
        'provider' => (string)($policy['provider'] ?? 'github'),
        'repository' => (string)($policy['repository'] ?? ''),
        'signatureVerification' => (string)($policy['signatureVerification'] ?? 'planned'),
        'systemSettingsHook' => (string)($policy['systemSettingsHook'] ?? 'placeholder'),
        'channel' => (string)($policy['channel'] ?? 'dev'),
    ];

    $meta['versionSources'] = [
        'currentVersionFile' => $versionPath,
        'releasePolicyFile' => $releasePath,
        'metaFile' => $metaPath,
    ];

    return [$meta, $policy];
}

function readRegistry($path) {
    $data = readJsonFile($path, ['extensions' => []]);
    if (!isset($data['extensions']) || !is_array($data['extensions'])) {
        $data['extensions'] = [];
    }
    return $data;
}

function normalizeRegistry($registry) {
    if (!isset($registry['extensions']) || !is_array($registry['extensions'])) {
        $registry['extensions'] = [];
    }

    foreach ($registry['extensions'] as &$ext) {
        if (!is_array($ext)) {
            $ext = [];
        }
        if (!isset($ext['id']) || $ext['id'] === '') {
            $ext['id'] = 'unknown-' . uniqid();
        }
        if (!isset($ext['name']) || $ext['name'] === '') {
            $ext['name'] = $ext['id'];
        }
        if (!isset($ext['entry']) || $ext['entry'] === '') {
            if (isset($ext['path']) && $ext['path'] !== '') {
                $ext['entry'] = $ext['path'];
            } else {
                $ext['entry'] = '/' . $ext['id'] . '.php';
            }
        }
        // Keep legacy compatibility for clients still reading "path".
        $ext['path'] = $ext['entry'];
        if (!isset($ext['pinned'])) {
            $ext['pinned'] = false;
        }
        if (!isset($ext['version'])) {
            $ext['version'] = 'unknown';
        }
        if (!isset($ext['versionSource'])) {
            $ext['versionSource'] = 'registry';
        }
        if (!isset($ext['enabled'])) {
            $ext['enabled'] = true;
        }
        if (!isset($ext['state']) || $ext['state'] === '') {
            $ext['state'] = !empty($ext['enabled']) ? 'active' : 'inactive';
        }

        $legacyM = isset($ext['showInMMenu']) ? (bool)$ext['showInMMenu'] : null;
        $legacyLibrary = isset($ext['showInLibrary']) ? (bool)$ext['showInLibrary'] : null;
        if (!isset($ext['menuVisibility']) || !is_array($ext['menuVisibility'])) {
            $ext['menuVisibility'] = [];
        }
        if (!array_key_exists('m', $ext['menuVisibility'])) {
            $ext['menuVisibility']['m'] = $legacyM !== null ? $legacyM : true;
        }
        if (!array_key_exists('library', $ext['menuVisibility'])) {
            $ext['menuVisibility']['library'] = $legacyLibrary !== null ? $legacyLibrary : true;
        }

        $ext['pinned'] = (bool)$ext['pinned'];
        $ext['enabled'] = (bool)$ext['enabled'];
        $ext['state'] = $ext['enabled'] ? 'active' : 'inactive';
        $ext['menuVisibility']['m'] = (bool)$ext['menuVisibility']['m'];
        $ext['menuVisibility']['library'] = (bool)$ext['menuVisibility']['library'];

        // Keep flat compatibility fields for downstream scripts.
        $ext['showInMMenu'] = $ext['menuVisibility']['m'];
        $ext['showInLibrary'] = $ext['menuVisibility']['library'];
    }
    unset($ext);

    return $registry;
}

function responseData($registryPath, $metaPath, $versionPath, $releasePath) {
    $registry = normalizeRegistry(readRegistry($registryPath));
    [$meta, $policy] = buildMeta($metaPath, $versionPath, $releasePath);
    $activeCount = 0;
    $inactiveCount = 0;
    $pinnedCount = 0;
    foreach ($registry['extensions'] as $ext) {
        if (!empty($ext['enabled'])) {
            $activeCount++;
        } else {
            $inactiveCount++;
        }
        if (!empty($ext['pinned'])) {
            $pinnedCount++;
        }
    }

    return [
        'extensions' => $registry['extensions'],
        'meta' => $meta,
        'releasePolicy' => $policy,
        'health' => [
            'apiService' => 'online',
            'registry' => is_writable(dirname($registryPath)) ? 'writable' : 'read-only',
            'extensionCount' => count($registry['extensions']),
            'activeCount' => $activeCount,
            'inactiveCount' => $inactiveCount,
            'pinnedCount' => $pinnedCount,
        ],
    ];
}

function isValidExtensionId($id) {
    return is_string($id) && preg_match('/^[a-zA-Z0-9._-]+$/', $id) === 1;
}

function isSafeRelativeSubPath($path) {
    if (!is_string($path)) {
        return false;
    }
    $clean = trim(str_replace('\\', '/', $path));
    if ($clean === '' || substr($clean, 0, 1) === '/' || strpos($clean, '..') !== false) {
        return false;
    }
    return true;
}

function resolveExtensionEntryFile($extId, $entryPath, &$error) {
    $error = '';
    $installedDir = '/var/www/extensions/installed/' . $extId;
    if (!is_dir($installedDir)) {
        $error = 'Installed extension directory not found: ' . $installedDir;
        return null;
    }

    $candidates = [];

    $manifestPath = $installedDir . '/manifest.json';
    $manifest = readJsonFile($manifestPath, []);
    if (is_array($manifest) && isset($manifest['main']) && is_string($manifest['main']) && $manifest['main'] !== '') {
        $candidates[] = $manifest['main'];
    }

    if (is_string($entryPath) && trim($entryPath) !== '') {
        $entryRelative = ltrim(trim(str_replace('\\', '/', $entryPath)), '/');
        if ($entryRelative !== '') {
            $candidates[] = $entryRelative;
            $candidates[] = basename($entryRelative);
        }
    }

    $candidates[] = $extId . '.php';
    $candidates[] = 'index.php';

    $seen = [];
    foreach ($candidates as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate === '' || isset($seen[$candidate])) {
            continue;
        }
        $seen[$candidate] = true;

        if (!isSafeRelativeSubPath($candidate)) {
            continue;
        }

        $fullPath = $installedDir . '/' . $candidate;
        if (is_file($fullPath)) {
            return $fullPath;
        }
    }

    $error = 'No entry file found for extension ' . $extId . ' under ' . $installedDir;
    return null;
}

function repairExtensionSymlink($extId, $entryPath, &$error) {
    $error = '';
    if (!isValidExtensionId($extId)) {
        $error = 'Invalid extension id.';
        return null;
    }

    $targetFile = resolveExtensionEntryFile($extId, $entryPath, $resolveError);
    if (!is_string($targetFile) || $targetFile === '') {
        $error = $resolveError;
        return null;
    }

    $linkPath = '/var/www/' . $extId . '.php';
    if (file_exists($linkPath) || is_link($linkPath)) {
        if (!@unlink($linkPath)) {
            $error = 'Unable to replace existing link/file at ' . $linkPath;
            return null;
        }
    }

    if (!@symlink($targetFile, $linkPath)) {
        $error = 'Failed to create symlink ' . $linkPath . ' -> ' . $targetFile . '. Check filesystem permissions.';
        return null;
    }

    return [
        'linkPath' => $linkPath,
        'targetPath' => $targetFile,
    ];
}

if ($action === 'list' || $action === 'refresh') {
    $data = responseData($registryPath, $metaPath, $versionPath, $releasePath);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'status') {
    $data = responseData($registryPath, $metaPath, $versionPath, $releasePath);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'check_update') {
    [$meta, $policy] = buildMeta($metaPath, $versionPath, $releasePath);
    $resolveError = '';
    $candidate = resolveRemoteReleaseCandidate($policy, $resolveError);

    if (is_array($candidate)) {
        $meta['latestVersion'] = (string)$candidate['version'];
        updateReleasePolicyFromCandidate($releasePath, $policy, $candidate);
        $policy = readReleasePolicy($releasePath);
    }

    $hasUpdate = is_array($candidate)
        ? safeHasUpdate((string)$candidate['version'], (string)$meta['version'])
        : safeHasUpdate((string)$meta['latestVersion'], (string)$meta['version']);

    $meta = markMetaMaintenance($meta, 'check_update', is_array($candidate) ? 'success' : 'provider-error');
    writeJsonFile($metaPath, $meta);

    echo json_encode([
        'ok' => true,
        'data' => [
            'meta' => $meta,
            'releasePolicy' => $policy,
            'hasUpdate' => $hasUpdate,
            'candidate' => $candidate,
            'warning' => $candidate === null ? $resolveError : null,
            'comparison' => [
                'current' => (string)$meta['version'],
                'latest' => is_array($candidate) ? (string)$candidate['version'] : (string)$meta['latestVersion'],
                'source' => $meta['versionSources'] ?? [],
            ],
            'providerStatus' => [
                'reachable' => is_array($candidate),
                'signatureVerification' => (string)($policy['signatureVerification'] ?? 'planned'),
                'checksumAlgorithm' => (string)($policy['checksumAlgorithm'] ?? 'sha256'),
                'integrityManifestPath' => (string)($policy['integrityManifestPath'] ?? 'ext-mgr.integrity.json'),
            ],
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'run_update') {
    [$meta, $policy] = buildMeta($metaPath, $versionPath, $releasePath);
    $resolveError = '';
    $candidate = resolveRemoteReleaseCandidate($policy, $resolveError);
    if (!is_array($candidate)) {
        $meta = markMetaMaintenance($meta, 'update', 'provider-error');
        writeJsonFile($metaPath, $meta);
        http_response_code(502);
        echo json_encode([
            'ok' => false,
            'error' => 'Unable to resolve update candidate: ' . $resolveError,
        ]);
        exit;
    }

    $targetVersion = (string)$candidate['version'];
    $hasUpdate = safeHasUpdate($targetVersion, (string)$meta['version']);
    if (!$hasUpdate) {
        $meta = markMetaMaintenance($meta, 'update', 'noop-latest');
        writeJsonFile($metaPath, $meta);

        echo json_encode([
            'ok' => true,
            'data' => [
                'updated' => false,
                'meta' => $meta,
                'releasePolicy' => $policy,
                'candidate' => $candidate,
                'comparison' => [
                    'current' => (string)$meta['version'],
                    'latest' => $targetVersion,
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $fetchError = '';
    $payloads = fetchManagedFilesFromRelease($policy, $candidate, $fetchError);
    if (!is_array($payloads)) {
        $meta = markMetaMaintenance($meta, 'update', 'download-failed');
        writeJsonFile($metaPath, $meta);
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Failed to fetch update payloads: ' . $fetchError]);
        exit;
    }

    $verificationMode = (string)($policy['signatureVerification'] ?? 'planned');
    $integrity = [
        'mode' => $verificationMode,
        'algorithm' => (string)($policy['checksumAlgorithm'] ?? 'sha256'),
        'manifestPath' => (string)($policy['integrityManifestPath'] ?? 'ext-mgr.integrity.json'),
        'status' => 'not-run',
    ];

    if ($verificationMode !== 'disabled') {
        $manifestError = '';
        $manifest = fetchIntegrityManifestFromRelease($policy, $candidate, $manifestError);
        if (!is_array($manifest)) {
            if ($verificationMode === 'required') {
                $meta = markMetaMaintenance($meta, 'update', 'integrity-failed');
                writeJsonFile($metaPath, $meta);
                http_response_code(502);
                echo json_encode(['ok' => false, 'error' => 'Integrity verification required but failed: ' . $manifestError]);
                exit;
            }
            $integrity['status'] = 'degraded';
            $integrity['warning'] = $manifestError;
        } else {
            $verificationError = '';
            $verificationDetails = [];
            if (!verifyPayloadsAgainstManifest($payloads, (array)($policy['managedFiles'] ?? []), $manifest, $verificationError, $verificationDetails)) {
                $meta = markMetaMaintenance($meta, 'update', 'integrity-failed');
                writeJsonFile($metaPath, $meta);
                http_response_code(502);
                echo json_encode([
                    'ok' => false,
                    'error' => 'Integrity verification failed: ' . $verificationError,
                    'integrity' => [
                        'mode' => $verificationMode,
                        'algorithm' => (string)($manifest['algorithm'] ?? 'sha256'),
                        'manifestPath' => (string)($manifest['path'] ?? ''),
                        'status' => 'failed',
                        'details' => $verificationDetails,
                    ],
                ]);
                exit;
            }

            $integrity['status'] = 'verified';
            $integrity['algorithm'] = (string)($manifest['algorithm'] ?? $integrity['algorithm']);
            $integrity['manifestPath'] = (string)($manifest['path'] ?? $integrity['manifestPath']);
            $integrity['details'] = $verificationDetails;
        }
    } else {
        $integrity['status'] = 'disabled';
    }

    $applyError = '';
    if (!applyManagedFiles(__DIR__, $payloads, $applyError)) {
        $meta = markMetaMaintenance($meta, 'update', 'apply-failed');
        writeJsonFile($metaPath, $meta);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to apply update payloads: ' . $applyError]);
        exit;
    }

    if (!writeVersionValue($versionPath, $targetVersion)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Update applied but failed to write version file.']);
        exit;
    }

    $policy['latestVersion'] = $targetVersion;
    $policy['lastAppliedAt'] = date('c');
    $policy['lastResolvedTag'] = (string)$candidate['tag'];
    if (!writeJsonFile($releasePath, $policy)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Update applied but failed to persist release policy.']);
        exit;
    }

    $meta = readMeta($metaPath);
    $meta['version'] = $targetVersion;
    $meta['latestVersion'] = $targetVersion;
    $meta['updated'] = date('Y-m-d');
    $meta = markMetaMaintenance($meta, 'update', 'success');
    if (!writeJsonFile($metaPath, $meta)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Update applied but failed to write metadata.']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'data' => [
            'updated' => true,
            'meta' => $meta,
            'releasePolicy' => $policy,
            'candidate' => $candidate,
            'integrity' => $integrity,
            'managedFileCount' => count($payloads),
            'comparison' => [
                'current' => (string)$meta['version'],
                'latest' => $targetVersion,
            ],
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'system_update_hook') {
    [$meta, $policy] = buildMeta($metaPath, $versionPath, $releasePath);
    $resolveError = '';
    $candidate = resolveRemoteReleaseCandidate($policy, $resolveError);
    echo json_encode([
        'ok' => true,
        'data' => [
            'meta' => $meta,
            'releasePolicy' => $policy,
            'hook' => [
                'status' => is_array($candidate) ? 'ready' : 'degraded',
                'description' => 'System Settings integration uses provider metadata and managed-file apply flow.',
                'lastError' => $candidate === null ? $resolveError : null,
                'candidate' => $candidate,
                'nextSteps' => [
                    'Resolve trusted signing key source',
                    'Enable signature verification as required in release policy',
                    'Verify package signature before applying update',
                ],
            ],
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'repair') {
    $meta = readMeta($metaPath);
    $registry = normalizeRegistry(readRegistry($registryPath));

    if (!writeJsonFile($registryPath, $registry)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to repair registry']);
        exit;
    }

    $meta['maintenance']['lastAction'] = 'repair';
    $meta['maintenance']['lastResult'] = 'success';
    $meta['maintenance']['lastRunAt'] = date('c');

    if (!writeJsonFile($metaPath, $meta)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Registry repaired but metadata write failed']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'data' => [
            'message' => 'Repair complete',
            'meta' => $meta,
            'extensions' => $registry['extensions'],
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'pin') {
    $id = (string)($_REQUEST['id'] ?? '');
    $value = (string)($_REQUEST['value'] ?? '1');
    if ($id === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing id']);
        exit;
    }

    $registry = normalizeRegistry(readRegistry($registryPath));
    $updated = false;

    foreach ($registry['extensions'] as &$ext) {
        if (($ext['id'] ?? '') === $id) {
            $ext['pinned'] = ($value === '1' || strtolower($value) === 'true');
            $updated = true;
            break;
        }
    }
    unset($ext);

    if (!$updated) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Extension not found']);
        exit;
    }

    if (!writeJsonFile($registryPath, $registry)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to write registry']);
        exit;
    }

    echo json_encode(['ok' => true, 'data' => ['id' => $id]], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'set_enabled') {
    $id = (string)($_REQUEST['id'] ?? '');
    $value = (string)($_REQUEST['value'] ?? '1');
    if ($id === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing id']);
        exit;
    }

    $registry = normalizeRegistry(readRegistry($registryPath));
    $updated = false;
    $enabled = ($value === '1' || strtolower($value) === 'true');

    foreach ($registry['extensions'] as &$ext) {
        if (($ext['id'] ?? '') === $id) {
            $ext['enabled'] = $enabled;
            $ext['state'] = $enabled ? 'active' : 'inactive';
            $updated = true;
            break;
        }
    }
    unset($ext);

    if (!$updated) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Extension not found']);
        exit;
    }

    if (!writeJsonFile($registryPath, $registry)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to write registry']);
        exit;
    }

    echo json_encode(['ok' => true, 'data' => ['id' => $id, 'enabled' => $enabled]], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'repair_symlink') {
    $id = (string)($_REQUEST['id'] ?? '');
    if ($id === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing id']);
        exit;
    }

    $registry = normalizeRegistry(readRegistry($registryPath));
    $entryPath = '';
    $found = false;

    foreach ($registry['extensions'] as $ext) {
        if (($ext['id'] ?? '') === $id) {
            $entryPath = (string)($ext['entry'] ?? '');
            $found = true;
            break;
        }
    }

    if (!$found) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Extension not found']);
        exit;
    }

    $repairError = '';
    $result = repairExtensionSymlink($id, $entryPath, $repairError);
    if (!is_array($result)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $repairError !== '' ? $repairError : 'Failed to repair symlink']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'data' => [
            'id' => $id,
            'linkPath' => $result['linkPath'],
            'targetPath' => $result['targetPath'],
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'set_menu_visibility') {
    $id = (string)($_REQUEST['id'] ?? '');
    $menu = strtolower(trim((string)($_REQUEST['menu'] ?? '')));
    $value = (string)($_REQUEST['value'] ?? '1');

    if ($id === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing id']);
        exit;
    }

    if ($menu !== 'm' && $menu !== 'library') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid menu target. Use m or library.']);
        exit;
    }

    $registry = normalizeRegistry(readRegistry($registryPath));
    $updated = false;
    $visible = ($value === '1' || strtolower($value) === 'true');

    foreach ($registry['extensions'] as &$ext) {
        if (($ext['id'] ?? '') === $id) {
            if (!isset($ext['menuVisibility']) || !is_array($ext['menuVisibility'])) {
                $ext['menuVisibility'] = ['m' => true, 'library' => true];
            }
            $ext['menuVisibility'][$menu] = $visible;
            $ext['showInMMenu'] = (bool)$ext['menuVisibility']['m'];
            $ext['showInLibrary'] = (bool)$ext['menuVisibility']['library'];
            $updated = true;
            break;
        }
    }
    unset($ext);

    if (!$updated) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Extension not found']);
        exit;
    }

    if (!writeJsonFile($registryPath, $registry)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to write registry']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'data' => [
            'id' => $id,
            'menu' => $menu,
            'visible' => $visible,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unsupported action'], JSON_UNESCAPED_SLASHES);
