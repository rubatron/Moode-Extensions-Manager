<?php
// ext-mgr JSON API endpoint with maintenance actions.
$action = $_REQUEST['action'] ?? 'list';
if ($action !== 'download_extension_template') {
    header('Content-Type: application/json; charset=utf-8');
}

$baseDir = dirname((string)(realpath(__FILE__) ?: __FILE__));
$registryPath = $baseDir . DIRECTORY_SEPARATOR . 'registry.json';
$metaPath = $baseDir . DIRECTORY_SEPARATOR . 'ext-mgr.meta.json';
$versionPath = $baseDir . DIRECTORY_SEPARATOR . 'ext-mgr.version';
$releasePath = $baseDir . DIRECTORY_SEPARATOR . 'ext-mgr.release.json';
$symlinkHelperPath = '/usr/local/sbin/ext-mgr-repair-symlink';
$extensionsRootPath = '/var/www/extensions';
$extensionsInstalledPath = $extensionsRootPath . '/installed';
$extensionsCachePath = $extensionsRootPath . '/cache';
$extensionsBackupPath = $baseDir . DIRECTORY_SEPARATOR . 'backup';

function defaultMeta() {
    return [
        'name' => 'Extension Manager',
        'slug' => 'ext-mgr',
        'version' => '0.0.0-dev',
        'latestVersion' => '0.0.0-dev',
        'creator' => 'Rubatron Team',
        'license' => 'GPL-3.0-or-later',
        'description' => 'Central manager for moOde extension discovery and menu visibility state.',
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
        'managerVisibility' => [
            'header' => true,
            'library' => true,
            'system' => true,
            'm' => true,
        ],
    ];
}

function defaultReleasePolicy() {
    return [
        'schemaVersion' => '2',
        'channel' => 'stable',
        'updateTrack' => 'channel',
        'branch' => 'main',
        'customBaseUrl' => '',
        'devBranch' => 'dev',
        'availableBranches' => ['main', 'dev'],
        'latestVersion' => '0.0.0',
        'provider' => 'github',
        'repository' => 'rubatron/Moode-Extensions-Manager',
        'signatureVerification' => 'planned',
        'checksumAlgorithm' => 'sha256',
        'integrityManifestPath' => 'ext-mgr.integrity.json',
        'systemSettingsHook' => 'api-managed',
        'releaseSelection' => 'channel-aware',
        'prereleaseStrategy' => 'prefer-stable',
        'notes' => 'Release policy for ext-mgr self-update via provider metadata.',
        'managedFiles' => [
            'ext-mgr.php',
            'ext-mgr-api.php',
            'ext-mgr.meta.json',
            'ext-mgr.release.json',
            'ext-mgr.version',
            'assets/js/ext-mgr.js',
            'assets/css/ext-mgr.css',
            'scripts/ext-mgr-import-wizard.sh',
            'scripts/ext-mgr-registry-sync.sh',
            'content/guidance.md',
            'content/developer-requirements.md',
            'content/faq.md',
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

    $allowedTracks = ['channel', 'branch', 'custom'];
    if (!in_array((string)$normalized['updateTrack'], $allowedTracks, true)) {
        $normalized['updateTrack'] = $defaults['updateTrack'];
    }

    $normalized['branch'] = trim((string)($normalized['branch'] ?? $defaults['branch']));
    if ($normalized['branch'] === '' || preg_match('/^[a-zA-Z0-9._\/-]+$/', $normalized['branch']) !== 1) {
        $normalized['branch'] = $defaults['branch'];
    }

    $normalized['devBranch'] = trim((string)($normalized['devBranch'] ?? $defaults['devBranch']));
    if ($normalized['devBranch'] === '' || preg_match('/^[a-zA-Z0-9._\/-]+$/', $normalized['devBranch']) !== 1) {
        $normalized['devBranch'] = $defaults['devBranch'];
    }

    $normalized['availableBranches'] = ['main', 'dev'];
    if (!in_array($normalized['branch'], $normalized['availableBranches'], true)) {
        $normalized['branch'] = 'main';
    }
    if (!in_array($normalized['devBranch'], $normalized['availableBranches'], true)) {
        $normalized['devBranch'] = 'dev';
    }

    $normalized['customBaseUrl'] = trim((string)($normalized['customBaseUrl'] ?? ''));
    if ($normalized['customBaseUrl'] !== '' && preg_match('/^https?:\/\//i', $normalized['customBaseUrl']) !== 1) {
        $normalized['customBaseUrl'] = '';
    }
    if ($normalized['customBaseUrl'] !== '') {
        $normalized['customBaseUrl'] = rtrim($normalized['customBaseUrl'], '/');
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

function readTextFile($path, $fallback) {
    if (!is_string($path) || !file_exists($path) || !is_readable($path)) {
        return $fallback;
    }
    $data = @file_get_contents($path);
    if (!is_string($data) || trim($data) === '') {
        return $fallback;
    }
    return $data;
}

function readGuidanceDocs($baseDir) {
    $contentDir = rtrim((string)$baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'content';

    return [
        'guidanceMarkdown' => readTextFile(
            $contentDir . DIRECTORY_SEPARATOR . 'guidance.md',
            "# Guidance\n\nNo guidance content found."
        ),
        'requirementsMarkdown' => readTextFile(
            $contentDir . DIRECTORY_SEPARATOR . 'developer-requirements.md',
            "# Developer Requirements\n\nNo requirements content found."
        ),
        'faqMarkdown' => readTextFile(
            $contentDir . DIRECTORY_SEPARATOR . 'faq.md',
            "# FAQ\n\nNo FAQ content found."
        ),
        'source' => [
            'contentDir' => $contentDir,
            'guidanceFile' => $contentDir . DIRECTORY_SEPARATOR . 'guidance.md',
            'requirementsFile' => $contentDir . DIRECTORY_SEPARATOR . 'developer-requirements.md',
            'faqFile' => $contentDir . DIRECTORY_SEPARATOR . 'faq.md',
        ],
    ];
}

function writeJsonFile($path, $data) {
    $tmp = $path . '.tmp';
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        return false;
    }
    $payload = $encoded . PHP_EOL;
    if (file_put_contents($tmp, $payload) !== false && @rename($tmp, $path)) {
        return true;
    }

    if (file_exists($tmp)) {
        @unlink($tmp);
    }

    // Fallback for environments where directory rename permissions are restricted
    // but the target file itself is writable.
    return file_put_contents($path, $payload, LOCK_EX) !== false;
}

function canWriteJsonPath($path) {
    if (file_exists($path)) {
        return is_writable($path);
    }
    return is_writable(dirname($path));
}

function formatWriteFailure($path, $label) {
    $dir = dirname($path);
    $fileWritable = file_exists($path) ? (is_writable($path) ? 'yes' : 'no') : 'missing';
    $dirWritable = is_writable($dir) ? 'yes' : 'no';

    return 'Failed to write ' . $label
        . ' (path=' . $path
        . ', fileWritable=' . $fileWritable
        . ', dirWritable=' . $dirWritable
        . ').';
}

function readSystemTotalMemMiB() {
    $meminfoPath = '/proc/meminfo';
    if (!is_readable($meminfoPath)) {
        return null;
    }

    $contents = @file_get_contents($meminfoPath);
    if (!is_string($contents) || $contents === '') {
        return null;
    }

    if (preg_match('/^MemTotal:\s+([0-9]+)\s+kB/im', $contents, $matches) !== 1) {
        return null;
    }

    $totalKiB = (float)$matches[1];
    if ($totalKiB <= 0) {
        return null;
    }

    return $totalKiB / 1024.0;
}

function buildRuntimeMemoryHealth() {
    $currentMiB = memory_get_usage(true) / 1048576.0;
    $totalMiB = readSystemTotalMemMiB();
    $pct = null;
    if (is_float($totalMiB) && $totalMiB > 0) {
        $pct = ($currentMiB / $totalMiB) * 100.0;
    }

    return [
        'serviceMemoryMiB' => round($currentMiB, 2),
        'systemMemoryMiB' => is_float($totalMiB) ? round($totalMiB, 2) : null,
        'serviceMemoryPctOfSystem' => is_float($pct) ? round($pct, 4) : null,
    ];
}

function ensureDirectory($path, $mode = 0775) {
    if (!is_string($path) || $path === '') {
        return false;
    }
    if (is_dir($path)) {
        return true;
    }
    return mkdir($path, $mode, true) || is_dir($path);
}

function removePathRecursiveWithStats($path, &$removedEntries, &$freedBytes) {
    if (!is_string($path) || $path === '' || !file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        $size = @filesize($path);
        if (is_int($size) || is_float($size)) {
            $freedBytes += max(0, (int)$size);
        }
        if (@unlink($path)) {
            $removedEntries++;
        }
        return;
    }

    $items = scandir($path);
    if (!is_array($items)) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        removePathRecursiveWithStats($path . DIRECTORY_SEPARATOR . $item, $removedEntries, $freedBytes);
    }

    if (@rmdir($path)) {
        $removedEntries++;
    }
}

function clearDirectoryContents($dir, &$removedEntries, &$freedBytes, &$error) {
    $removedEntries = 0;
    $freedBytes = 0;
    $error = '';

    if (!ensureDirectory($dir)) {
        $error = 'Failed to create cache directory: ' . $dir;
        return false;
    }

    $items = scandir($dir);
    if (!is_array($items)) {
        $error = 'Failed to read directory: ' . $dir;
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        removePathRecursiveWithStats($dir . DIRECTORY_SEPARATOR . $item, $removedEntries, $freedBytes);
    }

    return true;
}

function computeDirectorySizeBytes($path) {
    if (!is_string($path) || $path === '' || !file_exists($path)) {
        return 0;
    }
    if (is_file($path) || is_link($path)) {
        $size = @filesize($path);
        return (is_int($size) || is_float($size)) ? max(0, (int)$size) : 0;
    }

    $total = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $total += max(0, (int)$item->getSize());
        }
    }
    return $total;
}

function computeDirectoryEntryCount($path) {
    if (!is_dir($path)) {
        return 0;
    }

    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $count++;
    }
    return $count;
}

function copyPathRecursive($sourcePath, $targetPath, &$copiedItems, &$error) {
    if (is_link($sourcePath) || is_file($sourcePath)) {
        $targetDir = dirname($targetPath);
        if (!ensureDirectory($targetDir)) {
            $error = 'Failed to create backup directory: ' . $targetDir;
            return false;
        }
        if (!@copy($sourcePath, $targetPath)) {
            $error = 'Failed to backup file: ' . $sourcePath;
            return false;
        }
        $copiedItems++;
        return true;
    }

    if (!is_dir($sourcePath)) {
        return true;
    }

    if (!ensureDirectory($targetPath)) {
        $error = 'Failed to create backup directory: ' . $targetPath;
        return false;
    }

    $items = scandir($sourcePath);
    if (!is_array($items)) {
        $error = 'Failed to read source directory: ' . $sourcePath;
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        if (!copyPathRecursive($sourcePath . DIRECTORY_SEPARATOR . $item, $targetPath . DIRECTORY_SEPARATOR . $item, $copiedItems, $error)) {
            return false;
        }
    }
    return true;
}

function createExtMgrBackupSnapshot($baseDir, $backupRoot, &$snapshotPath, &$copiedItems, &$error) {
    $snapshotPath = '';
    $copiedItems = 0;
    $error = '';

    if (!ensureDirectory($backupRoot)) {
        $error = 'Failed to create backup root: ' . $backupRoot;
        return false;
    }

    $snapshotName = 'snapshot-' . date('Ymd-His');
    $snapshotPath = $backupRoot . DIRECTORY_SEPARATOR . $snapshotName;
    if (!ensureDirectory($snapshotPath)) {
        $error = 'Failed to create backup snapshot folder: ' . $snapshotPath;
        return false;
    }

    $items = [
        'ext-mgr.php',
        'ext-mgr-api.php',
        'ext-mgr.meta.json',
        'ext-mgr.release.json',
        'ext-mgr.version',
        'ext-mgr.integrity.json',
        'registry.json',
        'assets',
        'scripts',
        'content',
    ];

    foreach ($items as $relative) {
        $sourcePath = $baseDir . DIRECTORY_SEPARATOR . $relative;
        if (!file_exists($sourcePath)) {
            continue;
        }
        $targetPath = $snapshotPath . DIRECTORY_SEPARATOR . $relative;
        if (!copyPathRecursive($sourcePath, $targetPath, $copiedItems, $error)) {
            return false;
        }
    }

    return true;
}

function readCpuUsageSamplePct() {
    $statPath = '/proc/stat';
    if (!is_readable($statPath)) {
        return null;
    }

    $sample = static function () use ($statPath) {
        $line = @fgets(@fopen($statPath, 'r'));
        if (!is_string($line) || strpos($line, 'cpu ') !== 0) {
            return null;
        }
        $parts = preg_split('/\s+/', trim($line));
        if (!is_array($parts) || count($parts) < 8) {
            return null;
        }
        $vals = [];
        foreach (array_slice($parts, 1) as $p) {
            $vals[] = (float)$p;
        }
        $idle = ($vals[3] ?? 0) + ($vals[4] ?? 0);
        $total = array_sum($vals);
        return ['idle' => $idle, 'total' => $total];
    };

    $a = $sample();
    if (!is_array($a)) {
        return null;
    }
    usleep(120000);
    $b = $sample();
    if (!is_array($b)) {
        return null;
    }

    $deltaTotal = $b['total'] - $a['total'];
    $deltaIdle = $b['idle'] - $a['idle'];
    if ($deltaTotal <= 0) {
        return null;
    }

    return round(max(0.0, min(100.0, (($deltaTotal - $deltaIdle) / $deltaTotal) * 100.0)), 2);
}

function readMemoryOverview() {
    $memTotal = null;
    $memAvailable = null;
    $meminfoPath = '/proc/meminfo';
    if (is_readable($meminfoPath)) {
        $contents = @file_get_contents($meminfoPath);
        if (is_string($contents) && $contents !== '') {
            if (preg_match('/^MemTotal:\s+([0-9]+)\s+kB/im', $contents, $mTotal) === 1) {
                $memTotal = ((float)$mTotal[1]) / 1024.0;
            }
            if (preg_match('/^MemAvailable:\s+([0-9]+)\s+kB/im', $contents, $mAvail) === 1) {
                $memAvailable = ((float)$mAvail[1]) / 1024.0;
            }
        }
    }

    $memUsed = null;
    $usedPct = null;
    if (is_float($memTotal) && is_float($memAvailable)) {
        $memUsed = max(0.0, $memTotal - $memAvailable);
        if ($memTotal > 0) {
            $usedPct = ($memUsed / $memTotal) * 100.0;
        }
    }

    return [
        'totalMiB' => is_float($memTotal) ? round($memTotal, 2) : null,
        'availableMiB' => is_float($memAvailable) ? round($memAvailable, 2) : null,
        'usedMiB' => is_float($memUsed) ? round($memUsed, 2) : null,
        'usedPct' => is_float($usedPct) ? round($usedPct, 2) : null,
    ];
}

function diskUsageForPath($path) {
    if (!is_string($path) || $path === '' || !file_exists($path)) {
        return [
            'path' => $path,
            'totalBytes' => null,
            'freeBytes' => null,
            'usedBytes' => null,
            'usedPct' => null,
        ];
    }

    $total = @disk_total_space($path);
    $free = @disk_free_space($path);
    $used = null;
    $pct = null;
    if (is_float($total) || is_int($total)) {
        if (is_float($free) || is_int($free)) {
            $used = (float)$total - (float)$free;
            if ((float)$total > 0) {
                $pct = ((float)$used / (float)$total) * 100.0;
            }
        }
    }

    return [
        'path' => $path,
        'totalBytes' => (is_float($total) || is_int($total)) ? (int)$total : null,
        'freeBytes' => (is_float($free) || is_int($free)) ? (int)$free : null,
        'usedBytes' => (is_float($used) || is_int($used)) ? (int)$used : null,
        'usedPct' => is_float($pct) ? round($pct, 2) : null,
    ];
}

function readProcessRssMiBFromProc($pid) {
    $statusPath = '/proc/' . $pid . '/status';
    if (!is_readable($statusPath)) {
        return null;
    }
    $contents = @file_get_contents($statusPath);
    if (!is_string($contents) || $contents === '') {
        return null;
    }
    if (preg_match('/^VmRSS:\s+([0-9]+)\s+kB/im', $contents, $m) !== 1) {
        return null;
    }
    return ((float)$m[1]) / 1024.0;
}

function estimateExtensionRuntimeMemory($extensions) {
    $totals = [];
    $requirements = [];
    $scanPath = '/proc';
    $matchedAny = false;

    if (!is_dir($scanPath)) {
        return [
            'method' => 'unavailable',
            'totalMiB' => 0.0,
            'topConsumers' => [],
            'requirements' => ['Linux /proc access is required.'],
        ];
    }

    $extensionIds = [];
    foreach ((array)$extensions as $ext) {
        if (!is_array($ext)) {
            continue;
        }
        $id = (string)($ext['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $extensionIds[] = $id;
        $totals[$id] = 0.0;
    }

    $procEntries = @scandir($scanPath);
    if (!is_array($procEntries)) {
        return [
            'method' => 'degraded',
            'totalMiB' => 0.0,
            'topConsumers' => [],
            'requirements' => ['Unable to enumerate /proc entries.'],
        ];
    }

    foreach ($procEntries as $pid) {
        if (!preg_match('/^[0-9]+$/', $pid)) {
            continue;
        }

        $cmdline = @file_get_contents('/proc/' . $pid . '/cmdline');
        if (!is_string($cmdline) || $cmdline === '') {
            continue;
        }
        $cmdline = str_replace("\0", ' ', $cmdline);

        foreach ($extensionIds as $id) {
            if (strpos($cmdline, '/extensions/installed/' . $id . '/') === false
                && strpos($cmdline, $id . '.service') === false
                && strpos($cmdline, 'ext-mgr-' . $id) === false) {
                continue;
            }

            $rssMiB = readProcessRssMiBFromProc($pid);
            if (!is_float($rssMiB)) {
                continue;
            }

            $matchedAny = true;
            $totals[$id] += $rssMiB;
            break;
        }
    }

    $rows = [];
    $sum = 0.0;
    foreach ($totals as $id => $miB) {
        if ($miB <= 0) {
            continue;
        }
        $rows[] = ['id' => $id, 'memoryMiB' => round($miB, 2)];
        $sum += $miB;
    }

    usort($rows, static function ($a, $b) {
        return ($b['memoryMiB'] <=> $a['memoryMiB']);
    });

    if (!$matchedAny) {
        $requirements[] = 'Run each extension as a named systemd service (for example ext-mgr-ext1.service).';
        $requirements[] = 'Include extension id in process command line or service unit name.';
        $requirements[] = 'Optional: add extension watchdog logging for memory samples.';
    }

    return [
        'method' => $matchedAny ? 'proc-cmdline-match' : 'heuristic-no-match',
        'totalMiB' => round($sum, 2),
        'topConsumers' => array_slice($rows, 0, 5),
        'requirements' => $requirements,
    ];
}

function buildExtensionsStorageSummary($extensionsInstalledPath, $registryExtensions) {
    $total = 0;
    $count = 0;
    foreach ((array)$registryExtensions as $ext) {
        $id = (string)($ext['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $path = $extensionsInstalledPath . DIRECTORY_SEPARATOR . $id;
        if (!is_dir($path)) {
            continue;
        }
        $total += computeDirectorySizeBytes($path);
        $count++;
    }

    return [
        'totalBytes' => (int)$total,
        'extensionCount' => $count,
    ];
}

function readBackupSnapshotInfo($backupRoot) {
    if (!ensureDirectory($backupRoot)) {
        return [
            'path' => $backupRoot,
            'snapshotCount' => 0,
            'latest' => null,
        ];
    }

    $entries = scandir($backupRoot);
    if (!is_array($entries)) {
        return [
            'path' => $backupRoot,
            'snapshotCount' => 0,
            'latest' => null,
        ];
    }

    $snapshots = [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (is_dir($backupRoot . DIRECTORY_SEPARATOR . $entry)) {
            $snapshots[] = $entry;
        }
    }

    rsort($snapshots, SORT_STRING);

    return [
        'path' => $backupRoot,
        'snapshotCount' => count($snapshots),
        'latest' => $snapshots[0] ?? null,
    ];
}

function buildMaintenanceStatus($cacheDir, $backupRoot) {
    ensureDirectory($cacheDir);
    ensureDirectory($backupRoot);

    return [
        'cache' => [
            'path' => $cacheDir,
            'bytes' => computeDirectorySizeBytes($cacheDir),
            'fileCount' => computeDirectoryEntryCount($cacheDir),
        ],
        'backup' => readBackupSnapshotInfo($backupRoot),
    ];
}

function buildSystemResourceSnapshot($registryExtensions, $extensionsInstalledPath) {
    $memory = readMemoryOverview();
    $load = sys_getloadavg();
    $runtimeMemory = buildRuntimeMemoryHealth();

    return [
        'cpu' => [
            'usagePct' => readCpuUsageSamplePct(),
        ],
        'load' => [
            'one' => is_array($load) ? (float)($load[0] ?? 0.0) : null,
            'five' => is_array($load) ? (float)($load[1] ?? 0.0) : null,
            'fifteen' => is_array($load) ? (float)($load[2] ?? 0.0) : null,
        ],
        'memory' => $memory,
        'disk' => [
            'root' => diskUsageForPath('/'),
            'extensions' => diskUsageForPath('/var/www/extensions'),
        ],
        'extMgr' => [
            'memoryMiB' => $runtimeMemory['serviceMemoryMiB'],
            'memoryPctOfSystem' => $runtimeMemory['serviceMemoryPctOfSystem'],
        ],
        'extensions' => [
            'runtimeMemory' => estimateExtensionRuntimeMemory($registryExtensions),
            'storage' => buildExtensionsStorageSummary($extensionsInstalledPath, $registryExtensions),
        ],
    ];
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

function sanitizeExtensionId($value) {
    $id = strtolower(trim((string)$value));
    if ($id === '' || preg_match('/^[a-z0-9._-]+$/', $id) !== 1) {
        return 'template-extension';
    }
    return $id;
}

function buildTemplatePackageFiles($extensionId) {
    $displayName = ucwords(str_replace(['-', '_', '.'], ' ', $extensionId));
    $defaultIconClass = 'fa-solid fa-sharp fa-puzzle-piece';

    $manifest = [
        'id' => $extensionId,
        'name' => $displayName,
        'version' => '0.1.0',
        'main' => 'template.php',
        'ext_mgr' => [
            'enabled' => true,
            'state' => 'active',
            'stageProfile' => 'hidden-until-ready',
            'menuVisibility' => [
                'm' => false,
                'library' => false,
                'system' => false,
            ],
            'iconClass' => $defaultIconClass,
        ],
    ];

    $info = [
        'name' => $displayName,
        'version' => '0.1.0',
        'author' => 'Your Name',
        'license' => 'GPL-3.0-or-later',
        'description' => 'Starter template generated by ext-mgr import wizard.',
        'repository' => '',
        'settingsPage' => '/' . $extensionId . '.php',
        'iconClass' => $defaultIconClass,
    ];

    $templatePhp = "<?php\n"
        . "header('Content-Type: text/html; charset=utf-8');\n\n"
        . "\$usingMoodeShell = false;\n"
        . "\$section = 'extensions';\n\n"
        . "if (file_exists('/var/www/inc/common.php')) {\n"
        . "    require_once '/var/www/inc/common.php';\n"
        . "}\n"
        . "if (file_exists('/var/www/inc/session.php')) {\n"
        . "    require_once '/var/www/inc/session.php';\n"
        . "}\n\n"
        . "if (function_exists('sqlConnect') && function_exists('phpSession')) {\n"
        . "    @sqlConnect();\n"
        . "    @phpSession('open');\n"
        . "}\n\n"
        . "if (function_exists('storeBackLink')) {\n"
        . "    @storeBackLink(\$section, '{$extensionId}');\n"
        . "}\n\n"
        . "if (file_exists('/var/www/header.php')) {\n"
        . "    \$usingMoodeShell = true;\n"
        . "    include '/var/www/header.php';\n"
        . "    echo '<link rel=\"stylesheet\" href=\"/extensions/installed/{$extensionId}/assets/css/template.css\">' . \"\\n\";\n"
        . "} else {\n"
        . "    ?>\n"
        . "<!doctype html>\n"
        . "<html lang=\"en\">\n"
        . "<head>\n"
        . "    <meta charset=\"utf-8\">\n"
        . "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
        . "    <title>{$displayName}</title>\n"
        . "    <link rel=\"stylesheet\" href=\"/extensions/installed/{$extensionId}/assets/css/template.css\">\n"
        . "</head>\n"
        . "<body>\n"
        . "    <?php\n"
        . "}\n"
        . "?>\n\n"
        . "<div id=\"container\">\n"
        . "  <div class=\"container ext-template-shell\">\n"
        . "    <div class=\"ext-template-header\">\n"
        . "      <i id=\"ext-template-icon\" class=\"{$defaultIconClass}\" aria-hidden=\"true\"></i>\n"
        . "      <h1 class=\"config-title\">{$displayName}</h1>\n"
        . "    </div>\n"
        . "    <p class=\"config-help-static\">Template extension page. The moOde shell (back, home and M menu) is provided via header.php.</p>\n"
        . "\n"
        . "    <section class=\"ext-template-card\">\n"
        . "      <h2 class=\"ext-template-card-title\">Icon Picker (Starter)</h2>\n"
        . "      <p class=\"config-help-static\">Pick an icon class and copy it into info.json \"iconClass\".</p>\n"
        . "      <div class=\"ext-template-picker-row\">\n"
        . "        <label for=\"ext-template-icon-picker\">Icon</label>\n"
        . "        <select id=\"ext-template-icon-picker\"></select>\n"
        . "      </div>\n"
        . "      <div id=\"ext-template-icon-value\" class=\"ext-template-code\">{$defaultIconClass}</div>\n"
        . "    </section>\n"
        . "  </div>\n"
        . "</div>\n"
        . "\n"
        . "<script src=\"/extensions/installed/{$extensionId}/assets/js/template.js\"></script>\n"
        . "\n"
        . "<?php\n"
        . "if (\$usingMoodeShell) {\n"
        . "    if (file_exists('/var/www/footer.min.php')) {\n"
        . "        include '/var/www/footer.min.php';\n"
        . "    } elseif (file_exists('/var/www/footer.php')) {\n"
        . "        include '/var/www/footer.php';\n"
        . "    } else {\n"
        . "        include '/var/www/inc/footer.php';\n"
        . "    }\n"
        . "} else {\n"
        . "    ?>\n"
        . "</body>\n"
        . "</html>\n"
        . "    <?php\n"
        . "}\n";

    $templateJs = "(function () {\n"
        . "  'use strict';\n"
        . "\n"
        . "  var picker = document.getElementById('ext-template-icon-picker');\n"
        . "  var icon = document.getElementById('ext-template-icon');\n"
        . "  var value = document.getElementById('ext-template-icon-value');\n"
        . "  if (!picker || !icon || !value) {\n"
        . "    return;\n"
        . "  }\n"
        . "\n"
        . "  var icons = [\n"
        . "    'fa-solid fa-sharp fa-puzzle-piece',\n"
        . "    'fa-solid fa-sharp fa-music',\n"
        . "    'fa-solid fa-sharp fa-wave-square',\n"
        . "    'fa-solid fa-sharp fa-sliders',\n"
        . "    'fa-solid fa-sharp fa-gauge',\n"
        . "    'fa-solid fa-sharp fa-radio',\n"
        . "    'fa-solid fa-sharp fa-headphones',\n"
        . "    'fa-solid fa-sharp fa-folder-open'\n"
        . "  ];\n"
        . "\n"
        . "  icons.forEach(function (iconClass) {\n"
        . "    var option = document.createElement('option');\n"
        . "    option.value = iconClass;\n"
        . "    option.textContent = iconClass;\n"
        . "    picker.appendChild(option);\n"
        . "  });\n"
        . "\n"
        . "  function apply(iconClass) {\n"
        . "    icon.className = iconClass;\n"
        . "    value.textContent = iconClass;\n"
        . "  }\n"
        . "\n"
        . "  picker.value = icons[0];\n"
        . "  apply(picker.value);\n"
        . "\n"
        . "  picker.addEventListener('change', function () {\n"
        . "    apply(picker.value);\n"
        . "  });\n"
        . "})();\n";

    $templateCss = ".ext-template-shell {\n"
        . "  padding-bottom: 1.2rem;\n"
        . "}\n"
        . "\n"
        . ".ext-template-header {\n"
        . "  display: flex;\n"
        . "  align-items: center;\n"
        . "  gap: 0.6rem;\n"
        . "}\n"
        . "\n"
        . ".ext-template-header i {\n"
        . "  font-size: 1.2rem;\n"
        . "  color: var(--accentxts, #d35400);\n"
        . "}\n"
        . "\n"
        . ".ext-template-card {\n"
        . "  margin-top: 0.8rem;\n"
        . "  border: 1px solid rgba(128, 128, 128, 0.28);\n"
        . "  border-radius: 6px;\n"
        . "  padding: 0.7rem;\n"
        . "  background: rgba(0, 0, 0, 0.14);\n"
        . "}\n"
        . "\n"
        . ".ext-template-card-title {\n"
        . "  font-size: 1rem;\n"
        . "  margin: 0 0 0.45rem 0;\n"
        . "}\n"
        . "\n"
        . ".ext-template-picker-row {\n"
        . "  display: grid;\n"
        . "  grid-template-columns: 110px minmax(220px, 1fr);\n"
        . "  gap: 0.45rem 0.7rem;\n"
        . "  align-items: center;\n"
        . "}\n"
        . "\n"
        . ".ext-template-code {\n"
        . "  margin-top: 0.55rem;\n"
        . "  padding: 0.45rem 0.55rem;\n"
        . "  border: 1px solid rgba(128, 128, 128, 0.32);\n"
        . "  border-radius: 4px;\n"
        . "  font-family: monospace;\n"
        . "  color: #d6dbe0;\n"
        . "}\n";

    return [
        'manifest.json' => json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        'info.json' => json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        'ext-mgr.menu-preset.json' => json_encode([
            'profile' => 'hidden-until-ready',
            'description' => 'Pre-stage visibility profile for ext-mgr menu surfaces.',
            'menuVisibility' => [
                'm' => false,
                'library' => false,
                'system' => false,
            ],
            'notes' => [
                'Flip m=true when the extension is stable for M menu usage.',
                'Flip library=true when route and UX are validated for Library menu.',
                'Flip system=true when you want a System/Configure menu presence.',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        'template.php' => $templatePhp,
        'assets/js/template.js' => $templateJs,
        'assets/css/template.css' => $templateCss,
        'scripts/install.sh' => "#!/usr/bin/env bash\nset -euo pipefail\n\necho '[{$extensionId}] install.sh placeholder'\n",
        'README.md' => "# {$displayName}\n\nGenerated by ext-mgr Import Wizard template kit.\n\n## Import behavior\n- Installs into /var/www/extensions/installed/{$extensionId}\n- Creates canonical route /{$extensionId}.php\n- Starts hidden from M menu, Library menu and System/Configure surfaces\n\n## moOde shell requirements\n- Extension pages should include /var/www/header.php and footer integration\n- This guarantees Back arrow, Home button and M menu are available\n- The generated template.php already follows this requirement\n\n## Pre-stage menu visibility\n- Edit manifest.json -> ext_mgr.menuVisibility\n- Recommended progression:\n  1) system=true for internal QA\n  2) m=true after interaction checks\n  3) library=true after UX/content checks\n\n## Icon support\n- info.json now includes iconClass\n- Use the starter icon picker in template.php to pick a class\n- Copy the chosen class to info.json -> iconClass\n\n## Minimum files\n- template.php\n- assets/js/template.js\n- assets/css/template.css\n- info.json\n- scripts/install.sh\n",
        'assets/css/.gitkeep' => "",
        'cache/.gitkeep' => "",
        'data/.gitkeep' => "",
    ];
}

function writeTemplateFilesToDirectory($rootDir, $files, &$error) {
    $error = '';

    foreach ($files as $relativePath => $content) {
        $normalizedPath = trim(str_replace('\\', '/', (string)$relativePath), '/');
        if ($normalizedPath === '') {
            continue;
        }

        $targetPath = $rootDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            $error = 'Failed to create template directory: ' . $targetDir;
            return false;
        }

        if (file_put_contents($targetPath, (string)$content) === false) {
            $error = 'Failed to write template file: ' . $normalizedPath;
            return false;
        }
    }

    return true;
}

function writeTemplateZipViaCommand($zipPath, $extensionId, $files, &$error) {
    $error = '';

    if (!isPhpFunctionEnabled('exec')) {
        $error = 'zip fallback unavailable: exec() is disabled.';
        return false;
    }

    $whichOutput = [];
    $whichCode = 0;
    @exec('command -v zip 2>/dev/null', $whichOutput, $whichCode);
    if ($whichCode !== 0 || !is_array($whichOutput) || count($whichOutput) === 0) {
        $error = 'zip fallback unavailable: zip command not found.';
        return false;
    }

    $zipBinary = trim((string)$whichOutput[0]);
    if ($zipBinary === '') {
        $error = 'zip fallback unavailable: invalid zip binary path.';
        return false;
    }

    $buildRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'extmgr_tpl_build_' . uniqid('', true);
    $packageRootName = $extensionId . '-template';
    $packageRoot = $buildRoot . DIRECTORY_SEPARATOR . $packageRootName;

    if (!mkdir($packageRoot, 0775, true) && !is_dir($packageRoot)) {
        $error = 'Failed to prepare template build directory.';
        return false;
    }

    $writeError = '';
    if (!writeTemplateFilesToDirectory($packageRoot, $files, $writeError)) {
        removePathRecursive($buildRoot);
        $error = $writeError;
        return false;
    }

    if (file_exists($zipPath)) {
        @unlink($zipPath);
    }

    $cmd = 'cd ' . escapeshellarg($buildRoot)
        . ' && '
        . escapeshellarg($zipBinary)
        . ' -rq '
        . escapeshellarg($zipPath)
        . ' '
        . escapeshellarg($packageRootName)
        . ' 2>&1';

    $output = [];
    $exitCode = 0;
    @exec($cmd, $output, $exitCode);

    removePathRecursive($buildRoot);

    if ($exitCode !== 0 || !is_file($zipPath) || filesize($zipPath) <= 0) {
        $error = 'zip command failed: ' . trim(implode("\n", $output));
        return false;
    }

    return true;
}

function writeTemplateZipArchive($zipPath, $extensionId, &$error) {
    $error = '';
    $files = buildTemplatePackageFiles($extensionId);

    if (!class_exists('ZipArchive')) {
        if (writeTemplateZipViaCommand($zipPath, $extensionId, $files, $error)) {
            return true;
        }
        $error = 'ZipArchive extension is unavailable in PHP. ' . $error;
        return false;
    }

    $rootDir = $extensionId . '-template';

    $zip = new ZipArchive();
    $opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($opened !== true) {
        $error = 'Failed to open ZIP file for writing.';
        return false;
    }

    foreach ($files as $relativePath => $content) {
        $entryPath = $rootDir . '/' . trim(str_replace('\\', '/', (string)$relativePath), '/');
        if ($entryPath === $rootDir . '/') {
            continue;
        }
        if (!$zip->addFromString($entryPath, (string)$content)) {
            $zip->close();
            $error = 'Failed to add file to ZIP: ' . $relativePath;
            return false;
        }
    }

    $zip->close();
    return true;
}

function isSafeArchiveEntryPath($entryPath) {
    if (!is_string($entryPath)) {
        return false;
    }

    $clean = str_replace('\\', '/', trim($entryPath));
    if ($clean === '' || $clean === '.' || $clean === '..') {
        return false;
    }
    if (strpos($clean, "\0") !== false) {
        return false;
    }
    if (substr($clean, 0, 1) === '/') {
        return false;
    }
    if (preg_match('/^[a-zA-Z]:\//', $clean) === 1) {
        return false;
    }
    if (strpos($clean, '../') !== false || strpos($clean, '/..') !== false) {
        return false;
    }

    return true;
}

function listZipEntriesViaUnzip($zipPath, &$error) {
    $error = '';
    if (!isPhpFunctionEnabled('exec')) {
        $error = 'unzip fallback unavailable: exec() is disabled.';
        return null;
    }

    $whichOutput = [];
    $whichCode = 0;
    @exec('command -v unzip 2>/dev/null', $whichOutput, $whichCode);
    if ($whichCode !== 0 || !is_array($whichOutput) || count($whichOutput) === 0) {
        $error = 'unzip fallback unavailable: unzip command not found.';
        return null;
    }

    $unzipBinary = trim((string)$whichOutput[0]);
    if ($unzipBinary === '') {
        $error = 'unzip fallback unavailable: invalid unzip binary path.';
        return null;
    }

    $listCmd = escapeshellarg($unzipBinary) . ' -Z1 ' . escapeshellarg($zipPath) . ' 2>&1';
    $listOutput = [];
    $listExitCode = 0;
    @exec($listCmd, $listOutput, $listExitCode);
    if ($listExitCode !== 0) {
        $error = 'Failed to list zip entries via unzip: ' . trim(implode("\n", $listOutput));
        return null;
    }

    return [
        'binary' => $unzipBinary,
        'entries' => $listOutput,
    ];
}

function extractZipArchiveSafely($zipPath, $extractDir, &$error) {
    $error = '';

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $openStatus = $zip->open($zipPath);
        if ($openStatus !== true) {
            $error = 'Invalid ZIP package.';
            return false;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = (string)$zip->getNameIndex($i);
            if (!isSafeArchiveEntryPath($entryName)) {
                $zip->close();
                $error = 'Unsafe ZIP entry path detected: ' . $entryName;
                return false;
            }
        }

        if (!$zip->extractTo($extractDir)) {
            $zip->close();
            $error = 'Failed to extract ZIP package.';
            return false;
        }

        $zip->close();
        return true;
    }

    $listError = '';
    $listResult = listZipEntriesViaUnzip($zipPath, $listError);
    if (!is_array($listResult)) {
        $error = 'ZipArchive is unavailable. ' . $listError;
        return false;
    }

    foreach ((array)$listResult['entries'] as $entryName) {
        $entryName = trim((string)$entryName);
        if ($entryName === '') {
            continue;
        }
        if (!isSafeArchiveEntryPath($entryName)) {
            $error = 'Unsafe ZIP entry path detected: ' . $entryName;
            return false;
        }
    }

    $extractCmd = escapeshellarg((string)$listResult['binary'])
        . ' -qq -o '
        . escapeshellarg($zipPath)
        . ' -d '
        . escapeshellarg($extractDir)
        . ' 2>&1';

    $extractOutput = [];
    $extractExitCode = 0;
    @exec($extractCmd, $extractOutput, $extractExitCode);
    if ($extractExitCode !== 0) {
        $error = 'Failed to extract ZIP package via unzip: ' . trim(implode("\n", $extractOutput));
        return false;
    }

    return true;
}

function removePathRecursive($path) {
    if (!is_string($path) || $path === '' || !file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }

    $items = scandir($path);
    if (!is_array($items)) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        removePathRecursive($path . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($path);
}

function detectImportSourceDir($extractRoot) {
    $manifestAtRoot = $extractRoot . DIRECTORY_SEPARATOR . 'manifest.json';
    if (is_file($manifestAtRoot)) {
        return $extractRoot;
    }

    $items = scandir($extractRoot);
    if (!is_array($items)) {
        return null;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $candidate = $extractRoot . DIRECTORY_SEPARATOR . $item;
        if (!is_dir($candidate)) {
            continue;
        }
        if (is_file($candidate . DIRECTORY_SEPARATOR . 'manifest.json')) {
            return $candidate;
        }
    }

    return null;
}

function runImportWizard($wizardPath, $sourceDir, &$error, &$outputText) {
    $error = '';
    $outputText = '';

    if (!isPhpFunctionEnabled('exec')) {
        $error = 'exec() is disabled; import wizard cannot run.';
        return false;
    }

    if (!is_file($wizardPath)) {
        $error = 'Import wizard script not found: ' . $wizardPath;
        return false;
    }

    $commands = [
        escapeshellarg($wizardPath) . ' ' . escapeshellarg($sourceDir) . ' 2>&1',
        'sudo -n ' . escapeshellarg($wizardPath) . ' ' . escapeshellarg($sourceDir) . ' 2>&1',
    ];

    foreach ($commands as $command) {
        $output = [];
        $exitCode = 0;
        @exec($command, $output, $exitCode);
        $outputText = trim(implode("\n", $output));
        if ($exitCode === 0) {
            return true;
        }
    }

    $error = 'Import wizard failed. ' . ($outputText !== '' ? $outputText : 'No output captured.');
    return false;
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

function normalizeCustomBaseUrl($url) {
    $normalized = trim((string)$url);
    if ($normalized === '') {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $normalized) !== 1) {
        return '';
    }
    return rtrim($normalized, '/');
}

function buildCustomFileUrl($baseUrl, $filePath) {
    $base = normalizeCustomBaseUrl($baseUrl);
    if ($base === '' || !isSafeManagedPath($filePath)) {
        return null;
    }

    $pathSegments = array_map('rawurlencode', explode('/', trim(str_replace('\\', '/', (string)$filePath))));
    return $base . '/' . implode('/', $pathSegments);
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

function resolveRemoteBranchCandidate($repository, $branch, &$error) {
    $error = '';
    $branch = trim((string)$branch);
    if ($branch === '') {
        $error = 'Missing branch name for branch update track.';
        return null;
    }

    $apiUrl = githubApiUrl($repository, '/branches/' . rawurlencode($branch));
    if ($apiUrl === null) {
        $error = 'Invalid repository format in release policy.';
        return null;
    }

    $networkError = '';
    $payload = httpGet($apiUrl, $networkError);
    if ($payload === null) {
        $tagError = '';
        $fallbackChannel = strtolower($branch) === 'dev' ? 'dev' : 'stable';
        $tagCandidate = resolveRemoteTagCandidate($repository, $fallbackChannel, $tagError);
        if (is_array($tagCandidate)) {
            $error = '';
            return $tagCandidate;
        }

        $error = $networkError . ' | tag fallback failed: ' . $tagError;
        return null;
    }

    $decoded = json_decode($payload, true);
    if (!is_array($decoded)) {
        $error = 'Invalid JSON response from branch provider.';
        return null;
    }

    $commitSha = trim((string)($decoded['commit']['sha'] ?? ''));
    if ($commitSha === '') {
        $error = 'Branch response missing commit sha.';
        return null;
    }

    $shortSha = substr($commitSha, 0, 8);

    return [
        'provider' => 'github',
        'repository' => $repository,
        'channel' => 'dev',
        'tag' => $branch,
        'ref' => $branch,
        'version' => 'branch-' . $branch . '+' . $shortSha,
        'name' => 'Branch ' . $branch,
        'publishedAt' => '',
        'prerelease' => true,
        'draft' => false,
        'source' => 'branch',
        'branch' => $branch,
        'commitSha' => $commitSha,
    ];
}

function resolveAvailableRemoteBranches($repository, &$error) {
    $error = '';
    $apiUrl = githubApiUrl($repository, '/branches?per_page=50');
    if ($apiUrl === null) {
        $error = 'Invalid repository format in release policy.';
        return null;
    }

    $networkError = '';
    $payload = httpGet($apiUrl, $networkError);
    if ($payload === null) {
        $tagError = '';
        $tagCandidate = resolveRemoteTagCandidate($repository, $channel, $tagError);
        if (is_array($tagCandidate)) {
            $error = '';
            return $tagCandidate;
        }

        $error = $networkError . ' | tag fallback failed: ' . $tagError;
        return null;
    }

    $decoded = json_decode($payload, true);
    if (!is_array($decoded)) {
        $error = 'Invalid JSON response from branches provider.';
        return null;
    }

    $branches = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = trim((string)($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $branches[] = $name;
    }

    if (count($branches) === 0) {
        $error = 'No branches returned from provider.';
        return null;
    }

    return array_values(array_unique($branches));
}

function hasUpdateForCandidate($candidate, $currentVersion, $policy) {
    if (!is_array($candidate)) {
        return false;
    }

    $source = (string)($candidate['source'] ?? 'releases');
    if ($source === 'branch') {
        $candidateCommit = trim((string)($candidate['commitSha'] ?? ''));
        $lastAppliedCommit = trim((string)($policy['lastAppliedCommit'] ?? ''));
        if ($candidateCommit === '') {
            return false;
        }
        if ($lastAppliedCommit === '') {
            return true;
        }
        return strtolower($candidateCommit) !== strtolower($lastAppliedCommit);
    }

    return safeHasUpdate((string)($candidate['version'] ?? ''), (string)$currentVersion);
}

function chooseGithubTagByChannel($tags, $channel) {
    if (!is_array($tags) || count($tags) === 0) {
        return null;
    }

    $stable = [];
    $beta = [];
    $dev = [];

    foreach ($tags as $tag) {
        if (!is_array($tag)) {
            continue;
        }
        $name = trim((string)($tag['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $normalized = strtolower(normalizeVersion($name));
        if ($normalized === '') {
            continue;
        }

        if (strpos($normalized, 'dev') !== false || strpos($normalized, 'alpha') !== false) {
            $dev[] = $tag;
            continue;
        }
        if (strpos($normalized, 'beta') !== false || strpos($normalized, 'rc') !== false) {
            $beta[] = $tag;
            continue;
        }
        $stable[] = $tag;
    }

    if ($channel === 'stable') {
        return $stable[0] ?? null;
    }
    if ($channel === 'beta') {
        return $beta[0] ?? ($stable[0] ?? null);
    }
    if ($channel === 'dev') {
        return $dev[0] ?? ($beta[0] ?? ($stable[0] ?? null));
    }

    return $stable[0] ?? ($beta[0] ?? ($dev[0] ?? null));
}

function resolveRemoteTagCandidate($repository, $channel, &$error) {
    $error = '';
    $apiUrl = githubApiUrl($repository, '/tags?per_page=50');
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
        $error = 'Invalid JSON response from tags provider.';
        return null;
    }

    $selected = chooseGithubTagByChannel($decoded, $channel);
    if (!is_array($selected)) {
        $error = 'No tag candidate found for channel: ' . $channel;
        return null;
    }

    $tag = trim((string)($selected['name'] ?? ''));
    if ($tag === '') {
        $error = 'Selected tag has no name.';
        return null;
    }

    $version = normalizeVersion($tag);
    if ($version === '') {
        $error = 'Unable to derive version from selected tag.';
        return null;
    }

    return [
        'provider' => 'github',
        'repository' => $repository,
        'channel' => $channel,
        'tag' => $tag,
        'ref' => $tag,
        'version' => $version,
        'name' => $tag,
        'publishedAt' => '',
        'prerelease' => (strpos(strtolower($version), 'beta') !== false || strpos(strtolower($version), 'rc') !== false || strpos(strtolower($version), 'dev') !== false || strpos(strtolower($version), 'alpha') !== false),
        'draft' => false,
        'source' => 'tags-fallback',
    ];
}

function resolveCustomBaseCandidate($policy, &$error) {
    $error = '';
    $baseUrl = normalizeCustomBaseUrl($policy['customBaseUrl'] ?? '');
    if ($baseUrl === '') {
        $error = 'Custom mode requires a valid customBaseUrl (http/https).';
        return null;
    }

    $versionUrl = buildCustomFileUrl($baseUrl, 'ext-mgr.version');
    if ($versionUrl === null) {
        $error = 'Invalid custom URL for ext-mgr.version.';
        return null;
    }

    $networkError = '';
    $payload = httpGet($versionUrl, $networkError);
    if ($payload === null) {
        $error = 'Failed fetching custom version file: ' . $networkError;
        return null;
    }

    $versionLine = trim((string)preg_split('/\r?\n/', $payload)[0]);
    $version = normalizeVersion($versionLine);
    if ($version === '') {
        $error = 'Custom ext-mgr.version is empty or invalid.';
        return null;
    }

    return [
        'provider' => 'custom',
        'repository' => '',
        'channel' => 'custom',
        'tag' => 'custom-base',
        'ref' => 'custom-base',
        'version' => $version,
        'name' => 'Custom URL Source',
        'publishedAt' => '',
        'prerelease' => false,
        'draft' => false,
        'source' => 'custom',
        'baseUrl' => $baseUrl,
    ];
}

function resolveRemoteReleaseCandidate($policy, &$error) {
    $error = '';
    $updateTrack = (string)($policy['updateTrack'] ?? 'channel');
    if ($updateTrack === 'custom') {
        return resolveCustomBaseCandidate($policy, $error);
    }

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

    if ($updateTrack === 'branch') {
        $branchName = (string)($policy['branch'] ?? 'main');
        return resolveRemoteBranchCandidate($repository, $branchName, $error);
    }

    $channel = (string)($policy['channel'] ?? 'stable');
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
        $tagError = '';
        $tagCandidate = resolveRemoteTagCandidate($repository, $channel, $tagError);
        if (is_array($tagCandidate)) {
            $error = '';
            return $tagCandidate;
        }

        $error = 'No release candidate found for channel: ' . $channel . '. Tag fallback failed: ' . $tagError;
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
        'ref' => $tag,
        'version' => $version,
        'name' => (string)($selected['name'] ?? $tag),
        'publishedAt' => (string)($selected['published_at'] ?? ''),
        'prerelease' => !empty($selected['prerelease']),
        'draft' => !empty($selected['draft']),
        'source' => 'releases',
    ];
}

function fetchManagedFilesFromRelease($policy, $candidate, &$error) {
    $error = '';
    $files = $policy['managedFiles'] ?? [];
    if (!is_array($files) || count($files) === 0) {
        $error = 'No managed files configured in release policy.';
        return null;
    }

    $source = (string)($candidate['source'] ?? 'releases');
    $repo = (string)($candidate['repository'] ?? '');
    $ref = (string)($candidate['ref'] ?? ($candidate['tag'] ?? ''));
    $customBase = normalizeCustomBaseUrl($candidate['baseUrl'] ?? ($policy['customBaseUrl'] ?? ''));

    $payloads = [];
    foreach ($files as $filePath) {
        if (!isSafeManagedPath($filePath)) {
            $error = 'Unsafe managed file path: ' . $filePath;
            return null;
        }
        $clean = trim(str_replace('\\', '/', (string)$filePath));
        if ($source === 'custom') {
            $url = buildCustomFileUrl($customBase, $clean);
            if ($url === null) {
                $error = 'Unable to build custom URL for ' . $clean;
                return null;
            }
        } else {
            if ($repo === '' || $ref === '') {
                $error = 'Release candidate is missing repository/ref.';
                return null;
            }
            $url = githubRawFileUrl($repo, $ref, $clean);
            if ($url === null) {
                $error = 'Unable to build raw URL for ' . $clean;
                return null;
            }
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

    $source = (string)($candidate['source'] ?? 'releases');
    $repo = (string)($candidate['repository'] ?? '');
    $tag = (string)($candidate['tag'] ?? '');
    $customBase = normalizeCustomBaseUrl($candidate['baseUrl'] ?? ($policy['customBaseUrl'] ?? ''));
    $manifestPath = trim(str_replace('\\', '/', (string)($policy['integrityManifestPath'] ?? 'ext-mgr.integrity.json')));

    if (!isSafeManagedPath($manifestPath)) {
        $error = 'Unsafe integrity manifest path configured.';
        return null;
    }

    if ($source === 'custom') {
        $url = buildCustomFileUrl($customBase, $manifestPath);
        if ($url === null) {
            $error = 'Unable to build custom integrity manifest URL.';
            return null;
        }
    } else {
        if ($repo === '' || $tag === '') {
            $error = 'Release candidate is missing repository/tag for integrity manifest.';
            return null;
        }

        $url = githubRawFileUrl($repo, $tag, $manifestPath);
        if ($url === null) {
            $error = 'Unable to build integrity manifest URL.';
            return null;
        }
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
        'channel' => (string)($policy['channel'] ?? 'stable'),
        'updateTrack' => (string)($policy['updateTrack'] ?? 'channel'),
        'branch' => (string)($policy['branch'] ?? 'main'),
        'customBaseUrl' => (string)($policy['customBaseUrl'] ?? ''),
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

function normalizeUiPathOrUrl($value) {
    $v = trim((string)$value);
    if ($v === '') {
        return null;
    }
    if (preg_match('/^https?:\/\//i', $v) === 1) {
        return $v;
    }
    if (substr($v, 0, 1) !== '/') {
        $v = '/' . ltrim($v, '/');
    }
    return $v;
}

function normalizeIconClass($value, $fallback = 'fa-solid fa-sharp fa-puzzle-piece') {
    $raw = trim((string)$value);
    if ($raw === '') {
        return $fallback;
    }
    if (strpos($raw, 'fa-') === false) {
        return $fallback;
    }
    if (preg_match('/[^a-z0-9\-\s]/i', $raw) === 1) {
        return $fallback;
    }
    return $raw;
}

function loadExtensionInfo($extId, $entryPath, $fallbackName, $fallbackVersion) {
    $installedDir = '/var/www/extensions/installed/' . $extId;
    $candidates = [
        $installedDir . '/info.json',
        $installedDir . '/extension-info.json',
        $installedDir . '/' . $extId . '.info.json',
    ];

    $infoFile = null;
    $rawInfo = null;
    foreach ($candidates as $candidate) {
        if (!is_file($candidate)) {
            continue;
        }
        $decoded = readJsonFile($candidate, null);
        if (is_array($decoded)) {
            $infoFile = $candidate;
            $rawInfo = $decoded;
            break;
        }
    }

    $settingsPage = normalizeUiPathOrUrl($rawInfo['settingsPage'] ?? null)
        ?? normalizeUiPathOrUrl($rawInfo['settings_page'] ?? null)
        ?? normalizeUiPathOrUrl($rawInfo['configPage'] ?? null)
        ?? normalizeUiPathOrUrl($rawInfo['config_page'] ?? null)
        ?? normalizeUiPathOrUrl($entryPath)
        ?? ('/' . $extId . '.php');

    return [
        'name' => trim((string)($rawInfo['name'] ?? $fallbackName)),
        'version' => trim((string)($rawInfo['version'] ?? $fallbackVersion)),
        'author' => trim((string)($rawInfo['author'] ?? 'unknown')),
        'license' => trim((string)($rawInfo['license'] ?? 'unknown')),
        'description' => trim((string)($rawInfo['description'] ?? 'No extension description available.')),
        'repository' => trim((string)($rawInfo['repository'] ?? '')),
        'helpUrl' => trim((string)($rawInfo['help_url'] ?? $rawInfo['helpUrl'] ?? '')),
        'settingsPage' => $settingsPage,
        'iconClass' => normalizeIconClass($rawInfo['iconClass'] ?? $rawInfo['icon_class'] ?? ''),
        'infoFile' => $infoFile,
    ];
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
        if (!isset($ext['settingsCardOnly'])) {
            $ext['settingsCardOnly'] = false;
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
        if (!array_key_exists('system', $ext['menuVisibility'])) {
            $ext['menuVisibility']['system'] = true;
        }

        $ext['pinned'] = (bool)$ext['pinned'];
        $ext['enabled'] = (bool)$ext['enabled'];
        $ext['settingsCardOnly'] = (bool)$ext['settingsCardOnly'];
        $ext['state'] = $ext['enabled'] ? 'active' : 'inactive';
        $ext['menuVisibility']['m'] = (bool)$ext['menuVisibility']['m'];
        $ext['menuVisibility']['library'] = (bool)$ext['menuVisibility']['library'];
        $ext['menuVisibility']['system'] = (bool)$ext['menuVisibility']['system'];
        $ext['extensionInfo'] = loadExtensionInfo(
            (string)$ext['id'],
            (string)$ext['entry'],
            (string)$ext['name'],
            (string)$ext['version']
        );

        // Keep flat compatibility fields for downstream scripts.
        $ext['showInMMenu'] = $ext['menuVisibility']['m'];
        $ext['showInLibrary'] = $ext['menuVisibility']['library'];
    }
    unset($ext);

    return $registry;
}

function sanitizeRegistryForPersist($registry) {
    if (!isset($registry['extensions']) || !is_array($registry['extensions'])) {
        $registry['extensions'] = [];
        return $registry;
    }

    foreach ($registry['extensions'] as &$ext) {
        if (!is_array($ext)) {
            $ext = [];
            continue;
        }
        unset($ext['extensionInfo']);
    }
    unset($ext);

    return $registry;
}

function applyImportedExtensionDefaults($registryPath, $extId) {
    if (!isValidExtensionId($extId)) {
        return false;
    }

    $registry = normalizeRegistry(readRegistry($registryPath));
    $updated = false;
    foreach ($registry['extensions'] as &$ext) {
        if ((string)($ext['id'] ?? '') !== $extId) {
            continue;
        }

        if (!isset($ext['menuVisibility']) || !is_array($ext['menuVisibility'])) {
            $ext['menuVisibility'] = ['m' => false, 'library' => false, 'system' => false];
        }
        $ext['menuVisibility']['m'] = false;
        $ext['menuVisibility']['library'] = false;
        $ext['menuVisibility']['system'] = false;
        $ext['showInMMenu'] = false;
        $ext['showInLibrary'] = false;
        if (!isset($ext['settingsCardOnly'])) {
            $ext['settingsCardOnly'] = false;
        }

        $updated = true;
        break;
    }
    unset($ext);

    if (!$updated) {
        return false;
    }

    return writeJsonFile($registryPath, sanitizeRegistryForPersist($registry));
}

function responseData($registryPath, $metaPath, $versionPath, $releasePath) {
    global $extensionsCachePath, $extensionsBackupPath;

    $registry = normalizeRegistry(readRegistry($registryPath));
    [$meta, $policy] = buildMeta($metaPath, $versionPath, $releasePath);
    $guidance = readGuidanceDocs(dirname($registryPath));
    $activeCount = 0;
    $inactiveCount = 0;
    $mVisibleCount = 0;
    $libraryVisibleCount = 0;
    $systemVisibleCount = 0;
    $settingsCardCount = 0;
    foreach ($registry['extensions'] as $ext) {
        if (!empty($ext['enabled'])) {
            $activeCount++;
        } else {
            $inactiveCount++;
        }
        if (!empty($ext['menuVisibility']['m'])) {
            $mVisibleCount++;
        }
        if (!empty($ext['menuVisibility']['library'])) {
            $libraryVisibleCount++;
        }
        if (!empty($ext['menuVisibility']['system'])) {
            $systemVisibleCount++;
        }
        if (!empty($ext['settingsCardOnly'])) {
            $settingsCardCount++;
        }
    }

    $runtimeMemory = buildRuntimeMemoryHealth();

    return [
        'extensions' => $registry['extensions'],
        'meta' => $meta,
        'releasePolicy' => $policy,
        'guidance' => $guidance,
        'health' => [
            'apiService' => 'online',
            'registry' => canWriteJsonPath($registryPath) ? 'writable' : 'read-only',
            'extensionCount' => count($registry['extensions']),
            'activeCount' => $activeCount,
            'inactiveCount' => $inactiveCount,
            'mVisibleCount' => $mVisibleCount,
            'libraryVisibleCount' => $libraryVisibleCount,
            'systemVisibleCount' => $systemVisibleCount,
            'settingsCardCount' => $settingsCardCount,
            'serviceMemoryPctOfSystem' => $runtimeMemory['serviceMemoryPctOfSystem'],
            'serviceMemoryMiB' => $runtimeMemory['serviceMemoryMiB'],
            'systemMemoryMiB' => $runtimeMemory['systemMemoryMiB'],
        ],
        'maintenance' => buildMaintenanceStatus($extensionsCachePath, $extensionsBackupPath),
    ];
}

function syncRegistryWithFilesystem($registryPath, $pruneMissing = false) {
    $registry = normalizeRegistry(readRegistry($registryPath));
    $next = [];
    $summary = [
        'total' => 0,
        'installed' => 0,
        'missing' => 0,
        'pruned' => 0,
    ];

    foreach ($registry['extensions'] as $ext) {
        if (!is_array($ext)) {
            continue;
        }

        $summary['total']++;
        $id = (string)($ext['id'] ?? '');
        if ($id === '') {
            continue;
        }

        $installedDir = '/var/www/extensions/installed/' . $id;
        $canonicalLink = '/var/www/' . $id . '.php';
        $present = is_dir($installedDir) && (is_link($canonicalLink) || file_exists($canonicalLink));

        $ext['installed'] = $present;

        if (!$present) {
            $ext['enabled'] = false;
            $ext['state'] = 'missing';
            $summary['missing']++;
            if ($pruneMissing) {
                $summary['pruned']++;
                continue;
            }
        } else {
            $summary['installed']++;
            $ext['enabled'] = isset($ext['enabled']) ? (bool)$ext['enabled'] : true;
            $ext['state'] = $ext['enabled'] ? 'active' : 'inactive';
        }

        $next[] = $ext;
    }

    $registry['extensions'] = $next;
    $registry['generated_at'] = date('c');
    writeJsonFile($registryPath, sanitizeRegistryForPersist($registry));

    return $summary;
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
            $helperResult = runPrivilegedSymlinkRepair($extId, $entryPath, $helperError);
            if (is_array($helperResult)) {
                return $helperResult;
            }

            $error = 'Unable to replace existing link/file at ' . $linkPath . '. Helper fallback: ' . $helperError;
            return null;
        }
    }

    if (!@symlink($targetFile, $linkPath)) {
        $helperResult = runPrivilegedSymlinkRepair($extId, $entryPath, $helperError);
        if (is_array($helperResult)) {
            return $helperResult;
        }

        $error = 'Failed to create symlink ' . $linkPath . ' -> ' . $targetFile . '. Check filesystem permissions. Helper fallback: ' . $helperError;
        return null;
    }

    return [
        'linkPath' => $linkPath,
        'targetPath' => $targetFile,
    ];
}

function runPrivilegedSymlinkRepair($extId, $entryPath, &$error) {
    global $symlinkHelperPath;
    $error = '';

    if (!isPhpFunctionEnabled('exec')) {
        $error = 'exec() disabled; cannot invoke privileged helper.';
        return null;
    }

    if (!is_file($symlinkHelperPath)) {
        $error = 'Symlink helper not installed at ' . $symlinkHelperPath . '. Re-run install.sh.';
        return null;
    }

    $entryRelative = ltrim(trim((string)$entryPath), '/');
    if (!isSafeRelativeSubPath($entryRelative)) {
        $entryRelative = '';
    }

    $cmd = 'sudo -n '
        . escapeshellarg($symlinkHelperPath)
        . ' '
        . escapeshellarg($extId)
        . ' '
        . escapeshellarg($entryRelative)
        . ' 2>&1';

    $output = [];
    $exitCode = 0;
    @exec($cmd, $output, $exitCode);
    if ($exitCode !== 0) {
        $error = 'sudo helper failed: ' . trim(implode("\n", $output));
        return null;
    }

    $line = trim((string)($output[0] ?? ''));
    $parts = explode('|', $line, 2);
    $linkPath = trim((string)($parts[0] ?? ('/var/www/' . $extId . '.php')));
    $targetPath = trim((string)($parts[1] ?? ''));

    if ($targetPath === '' && is_link($linkPath)) {
        $resolved = @readlink($linkPath);
        if (is_string($resolved) && $resolved !== '') {
            $targetPath = $resolved;
        }
    }

    return [
        'linkPath' => $linkPath,
        'targetPath' => $targetPath,
    ];
}

function removeExtensionById($extId, $registryPath, $backupRoot, &$error) {
    $error = '';

    if (!isValidExtensionId($extId)) {
        $error = 'Invalid extension id.';
        return null;
    }

    if ($extId === 'ext-mgr') {
        $error = 'Removing ext-mgr itself is not supported from this action.';
        return null;
    }

    $registry = normalizeRegistry(readRegistry($registryPath));
    $found = false;
    $nextExtensions = [];
    foreach ($registry['extensions'] as $ext) {
        if (($ext['id'] ?? '') === $extId) {
            $found = true;
            continue;
        }
        $nextExtensions[] = $ext;
    }

    if (!$found) {
        $error = 'Extension not found in registry.';
        return null;
    }

    $registry['extensions'] = $nextExtensions;
    $registry['generated_at'] = date('c');
    if (!writeJsonFile($registryPath, sanitizeRegistryForPersist($registry))) {
        $error = formatWriteFailure($registryPath, 'registry');
        return null;
    }

    $installedDir = '/var/www/extensions/installed/' . $extId;
    $linkPath = '/var/www/' . $extId . '.php';
    $backupDir = rtrim($backupRoot, '/\\') . DIRECTORY_SEPARATOR . 'removed-extensions' . DIRECTORY_SEPARATOR . $extId . '-' . date('Ymd-His');

    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        $error = 'Extension removed from registry but failed to create backup directory: ' . $backupDir;
        return [
            'id' => $extId,
            'removedFromRegistry' => true,
            'removedInstallDir' => false,
            'removedRoute' => false,
            'backupPath' => $backupDir,
            'warning' => $error,
        ];
    }

    $removedInstallDir = false;
    if (is_dir($installedDir)) {
        $targetInstalledBackup = $backupDir . DIRECTORY_SEPARATOR . 'installed';
        if (@rename($installedDir, $targetInstalledBackup)) {
            $removedInstallDir = true;
        } else {
            $error = 'Extension removed from registry but failed to move installed directory: ' . $installedDir;
        }
    }

    $removedRoute = false;
    if (is_link($linkPath)) {
        $linkTarget = @readlink($linkPath);
        if (is_string($linkTarget) && $linkTarget !== '') {
            @file_put_contents($backupDir . DIRECTORY_SEPARATOR . 'route-link-target.txt', $linkTarget . PHP_EOL);
        }
        $removedRoute = @unlink($linkPath);
        if (!$removedRoute && $error === '') {
            $error = 'Failed to remove canonical route symlink: ' . $linkPath;
        }
    } elseif (is_file($linkPath)) {
        $targetRouteBackup = $backupDir . DIRECTORY_SEPARATOR . basename($linkPath);
        if (@rename($linkPath, $targetRouteBackup)) {
            $removedRoute = true;
        } elseif ($error === '') {
            $error = 'Failed to move canonical route file to backup: ' . $linkPath;
        }
    }

    return [
        'id' => $extId,
        'removedFromRegistry' => true,
        'removedInstallDir' => $removedInstallDir,
        'removedRoute' => $removedRoute,
        'backupPath' => $backupDir,
        'warning' => $error !== '' ? $error : null,
    ];
}

if ($action === 'download_extension_template') {
    $templateId = sanitizeExtensionId((string)($_REQUEST['template_id'] ?? 'template-extension'));
    $tmpZip = tempnam(sys_get_temp_dir(), 'extmgr_tpl_');
    if (!is_string($tmpZip) || $tmpZip === '') {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Unable to allocate temporary file for template package.']);
        exit;
    }

    $zipPath = $tmpZip . '.zip';
    @unlink($tmpZip);

    $zipError = '';
    if (!writeTemplateZipArchive($zipPath, $templateId, $zipError)) {
        @unlink($zipPath);
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $zipError]);
        exit;
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $templateId . '-template.zip"');
    header('Content-Length: ' . (string)filesize($zipPath));
    header('Cache-Control: no-store');

    readfile($zipPath);
    @unlink($zipPath);
    exit;
}

if ($action === 'import_extension_upload') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Use POST for extension upload.']);
        exit;
    }

    if (!isset($_FILES['package']) || !is_array($_FILES['package'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing package upload field.']);
        exit;
    }

    $upload = $_FILES['package'];
    $uploadError = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Upload failed with code ' . $uploadError . '.']);
        exit;
    }

    $tmpFile = (string)($upload['tmp_name'] ?? '');
    $originalName = strtolower(trim((string)($upload['name'] ?? '')));
    if ($tmpFile === '' || !is_uploaded_file($tmpFile)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid uploaded file.']);
        exit;
    }

    if (substr($originalName, -4) !== '.zip') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Only .zip extension packages are supported.']);
        exit;
    }

    $workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'extmgr_import_' . uniqid('', true);
    $extractDir = $workDir . DIRECTORY_SEPARATOR . 'extract';
    if (!mkdir($extractDir, 0775, true) && !is_dir($extractDir)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to prepare import workspace.']);
        exit;
    }

    $zipPath = $workDir . DIRECTORY_SEPARATOR . 'package.zip';
    if (!move_uploaded_file($tmpFile, $zipPath)) {
        removePathRecursive($workDir);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to move uploaded package.']);
        exit;
    }

    $extractError = '';
    if (!extractZipArchiveSafely($zipPath, $extractDir, $extractError)) {
        removePathRecursive($workDir);
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $extractError !== '' ? $extractError : 'Failed to extract ZIP package.']);
        exit;
    }

    $sourceDir = detectImportSourceDir($extractDir);
    if (!is_string($sourceDir) || $sourceDir === '') {
        removePathRecursive($workDir);
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'manifest.json not found in uploaded package root.']);
        exit;
    }

    $wizardPath = $baseDir . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'ext-mgr-import-wizard.sh';
    $execError = '';
    $wizardOutput = '';
    if (!runImportWizard($wizardPath, $sourceDir, $execError, $wizardOutput)) {
        removePathRecursive($workDir);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $execError]);
        exit;
    }

    $manifestData = readJsonFile($sourceDir . DIRECTORY_SEPARATOR . 'manifest.json', []);
    $importedId = sanitizeExtensionId((string)($manifestData['id'] ?? 'unknown'));

    applyImportedExtensionDefaults($registryPath, $importedId);

    syncRegistryWithFilesystem($registryPath, false);
    $state = responseData($registryPath, $metaPath, $versionPath, $releasePath);

    removePathRecursive($workDir);

    echo json_encode([
        'ok' => true,
        'data' => [
            'extensionId' => $importedId,
            'wizardOutput' => $wizardOutput,
            'state' => $state,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'list' || $action === 'refresh') {
    syncRegistryWithFilesystem($registryPath, false);
    $data = responseData($registryPath, $metaPath, $versionPath, $releasePath);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'status') {
    syncRegistryWithFilesystem($registryPath, false);
    $data = responseData($registryPath, $metaPath, $versionPath, $releasePath);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'registry_sync') {
    $prune = (string)($_REQUEST['prune'] ?? '0');
    $summary = syncRegistryWithFilesystem($registryPath, ($prune === '1' || strtolower($prune) === 'true'));
    $data = responseData($registryPath, $metaPath, $versionPath, $releasePath);
    echo json_encode([
        'ok' => true,
        'data' => [
            'summary' => $summary,
            'state' => $data,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'check_update') {
    [$meta, $policy] = buildMeta($metaPath, $versionPath, $releasePath);

    $branchWarning = null;
    $policy['availableBranches'] = ['main', 'dev'];

    $resolveError = '';
    $candidate = resolveRemoteReleaseCandidate($policy, $resolveError);

    if (is_array($candidate)) {
        $meta['latestVersion'] = (string)$candidate['version'];
        updateReleasePolicyFromCandidate($releasePath, $policy, $candidate);
        $policy = readReleasePolicy($releasePath);
    }

    $hasUpdate = is_array($candidate)
        ? hasUpdateForCandidate($candidate, (string)$meta['version'], $policy)
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
            'branchWarning' => $branchWarning,
            'comparison' => [
                'current' => (string)$meta['version'],
                'latest' => is_array($candidate) ? (string)$candidate['version'] : (string)$meta['latestVersion'],
                'source' => $meta['versionSources'] ?? [],
            ],
            'providerStatus' => [
                'reachable' => is_array($candidate),
                'provider' => (string)($policy['provider'] ?? 'github'),
                'repository' => (string)($policy['repository'] ?? ''),
                'signatureVerification' => (string)($policy['signatureVerification'] ?? 'planned'),
                'checksumAlgorithm' => (string)($policy['checksumAlgorithm'] ?? 'sha256'),
                'integrityManifestPath' => (string)($policy['integrityManifestPath'] ?? 'ext-mgr.integrity.json'),
                'updateTrack' => (string)($policy['updateTrack'] ?? 'channel'),
                'channel' => (string)($policy['channel'] ?? 'stable'),
                'branch' => (string)($policy['branch'] ?? 'main'),
                'customBaseUrl' => (string)($policy['customBaseUrl'] ?? ''),
                'availableBranches' => ['main', 'dev'],
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
    $hasUpdate = hasUpdateForCandidate($candidate, (string)$meta['version'], $policy);
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
    if (isset($candidate['commitSha']) && is_string($candidate['commitSha']) && $candidate['commitSha'] !== '') {
        $policy['lastAppliedCommit'] = (string)$candidate['commitSha'];
    }
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

if ($action === 'set_update_advanced') {
    $track = strtolower(trim((string)($_REQUEST['track'] ?? 'channel')));
    $channel = strtolower(trim((string)($_REQUEST['channel'] ?? 'stable')));
    $branch = trim((string)($_REQUEST['branch'] ?? 'main'));
    $customUrl = normalizeCustomBaseUrl((string)($_REQUEST['custom_url'] ?? ''));

    if ($track !== 'channel' && $track !== 'branch' && $track !== 'custom') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid track. Use channel, branch, or custom.']);
        exit;
    }

    if ($channel !== 'dev' && $channel !== 'beta' && $channel !== 'stable') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid channel. Use dev, beta, or stable.']);
        exit;
    }

    if ($track === 'branch') {
        if ($branch === '' || preg_match('/^[a-zA-Z0-9._\/\-]+$/', $branch) !== 1) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid branch name.']);
            exit;
        }

        if (!in_array($branch, ['main', 'dev'], true)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid branch. Allowed: main or dev.']);
            exit;
        }
    }

    if ($track === 'custom' && $customUrl === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Custom mode requires a valid custom URL (http/https).']);
        exit;
    }

    $policy = readReleasePolicy($releasePath);
    $policy['updateTrack'] = $track;
    $policy['channel'] = $channel;
    $policy['branch'] = in_array($branch, ['main', 'dev'], true) ? $branch : 'main';
    $policy['customBaseUrl'] = $track === 'custom' ? $customUrl : '';
    $policy['provider'] = $track === 'custom' ? 'custom' : 'github';
    $policy['availableBranches'] = ['main', 'dev'];

    if (!writeJsonFile($releasePath, $policy)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => formatWriteFailure($releasePath, 'release policy')]);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'data' => [
            'releasePolicy' => $policy,
            'message' => 'Advanced update settings saved.',
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
                'description' => 'Sync Extensions uses provider metadata and managed-file apply flow.',
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

    if (!writeJsonFile($registryPath, sanitizeRegistryForPersist($registry))) {
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
            if (!isset($ext['menuVisibility']) || !is_array($ext['menuVisibility'])) {
                $ext['menuVisibility'] = ['m' => true, 'library' => true, 'system' => true];
            }
            if ($enabled) {
                // Requested defaults on re-enable: M + Library visible, System hidden.
                $ext['menuVisibility']['m'] = true;
                $ext['menuVisibility']['library'] = true;
                $ext['menuVisibility']['system'] = false;
            } else {
                // Disabled extensions should not remain visible in menu integrations.
                $ext['menuVisibility']['m'] = false;
                $ext['menuVisibility']['library'] = false;
                $ext['menuVisibility']['system'] = false;
                $ext['settingsCardOnly'] = false;
            }
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

    if (!writeJsonFile($registryPath, sanitizeRegistryForPersist($registry))) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => formatWriteFailure($registryPath, 'registry')]);
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

if ($action === 'remove_extension') {
    $id = (string)($_REQUEST['id'] ?? '');
    if ($id === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing id']);
        exit;
    }

    $removeError = '';
    $result = removeExtensionById($id, $registryPath, $extensionsBackupPath, $removeError);
    if (!is_array($result)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $removeError !== '' ? $removeError : 'Failed to remove extension']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'data' => $result,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'system_resources') {
    $registry = normalizeRegistry(readRegistry($registryPath));

    echo json_encode([
        'ok' => true,
        'data' => [
            'resources' => buildSystemResourceSnapshot($registry['extensions'], $extensionsInstalledPath),
            'maintenance' => buildMaintenanceStatus($extensionsCachePath, $extensionsBackupPath),
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'clear_cache') {
    $removedEntries = 0;
    $freedBytes = 0;
    $clearError = '';
    if (!clearDirectoryContents($extensionsCachePath, $removedEntries, $freedBytes, $clearError)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $clearError]);
        exit;
    }

    $meta = readMeta($metaPath);
    $meta = markMetaMaintenance($meta, 'clear_cache', 'success');
    writeJsonFile($metaPath, $meta);

    echo json_encode([
        'ok' => true,
        'data' => [
            'path' => $extensionsCachePath,
            'removedEntries' => $removedEntries,
            'freedBytes' => $freedBytes,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'create_backup_snapshot') {
    $snapshotPath = '';
    $copiedItems = 0;
    $backupError = '';

    if (!createExtMgrBackupSnapshot($baseDir, $extensionsBackupPath, $snapshotPath, $copiedItems, $backupError)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $backupError]);
        exit;
    }

    $meta = readMeta($metaPath);
    $meta = markMetaMaintenance($meta, 'create_backup_snapshot', 'success');
    writeJsonFile($metaPath, $meta);

    echo json_encode([
        'ok' => true,
        'data' => [
            'snapshotPath' => $snapshotPath,
            'copiedItems' => $copiedItems,
            'backup' => readBackupSnapshotInfo($extensionsBackupPath),
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'set_manager_visibility') {
    $area = strtolower(trim((string)($_REQUEST['area'] ?? '')));
    $value = (string)($_REQUEST['value'] ?? '1');
    $allowed = ['header', 'library', 'system', 'm'];

    if (!in_array($area, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid area. Use header, library, system, or m.']);
        exit;
    }

    $visible = ($value === '1' || strtolower($value) === 'true');
    $meta = readMeta($metaPath);
    if (!isset($meta['managerVisibility']) || !is_array($meta['managerVisibility'])) {
        $meta['managerVisibility'] = ['header' => true, 'library' => true, 'system' => true, 'm' => true];
    }

    $meta['managerVisibility'][$area] = $visible;
    foreach ($allowed as $entry) {
        if (!array_key_exists($entry, $meta['managerVisibility'])) {
            $meta['managerVisibility'][$entry] = true;
        }
        $meta['managerVisibility'][$entry] = (bool)$meta['managerVisibility'][$entry];
    }

    if (!writeJsonFile($metaPath, $meta)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => formatWriteFailure($metaPath, 'meta')]);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'data' => [
            'area' => $area,
            'visible' => $visible,
            'visibility' => $meta['managerVisibility'],
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

    if ($menu !== 'm' && $menu !== 'library' && $menu !== 'system') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid menu target. Use m, library, or system.']);
        exit;
    }

    $registry = normalizeRegistry(readRegistry($registryPath));
    $updated = false;
    $visible = ($value === '1' || strtolower($value) === 'true');

    foreach ($registry['extensions'] as &$ext) {
        if (($ext['id'] ?? '') === $id) {
            if (empty($ext['enabled'])) {
                http_response_code(409);
                echo json_encode(['ok' => false, 'error' => 'Extension is inactive. Enable it before changing menu visibility.']);
                exit;
            }
            if (!isset($ext['menuVisibility']) || !is_array($ext['menuVisibility'])) {
                $ext['menuVisibility'] = ['m' => true, 'library' => true, 'system' => true];
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

    if (!writeJsonFile($registryPath, sanitizeRegistryForPersist($registry))) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => formatWriteFailure($registryPath, 'registry')]);
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

if ($action === 'set_settings_card_only') {
    $id = (string)($_REQUEST['id'] ?? '');
    $value = (string)($_REQUEST['value'] ?? '0');

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
            if (empty($ext['enabled'])) {
                http_response_code(409);
                echo json_encode(['ok' => false, 'error' => 'Extension is inactive. Enable it before changing settings card mode.']);
                exit;
            }
            $ext['settingsCardOnly'] = $enabled;
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

    if (!writeJsonFile($registryPath, sanitizeRegistryForPersist($registry))) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => formatWriteFailure($registryPath, 'registry')]);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'data' => [
            'id' => $id,
            'settingsCardOnly' => $enabled,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unsupported action'], JSON_UNESCAPED_SLASHES);
