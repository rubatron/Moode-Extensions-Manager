<?php
// ext-mgr JSON API endpoint with maintenance actions.
$action = $_REQUEST['action'] ?? 'list';
if ($action !== 'download_extension_template' && $action !== 'download_extension_log') {
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
$extensionsSysLogsRootPath = $extensionsRootPath . '/sys/logs';
$extensionsLogsPath = $extensionsSysLogsRootPath . '/extensionslogs';
$extMgrLogsPath = $extensionsSysLogsRootPath . '/ext-mgr logs';
$extMgrRuntimeLogsPath = $extensionsRootPath . '/sys/.ext-mgr/logs';

function defaultMeta()
{
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
            'library' => false,
            'system' => true,
            'm' => false,
        ],
    ];
}

function defaultReleasePolicy()
{
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
            'ext-mgr-shell-bridge.php',
            'ext-mgr.meta.json',
            'ext-mgr.release.json',
            'ext-mgr.version',
            'assets/js/ext-mgr.js',
            'assets/js/ext-mgr-logs.js',
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

function isSafeManagedPath($filePath)
{
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

function normalizeReleasePolicy($policy)
{
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

function normalizeVersion($value)
{
    $version = trim((string)$value);
    if ($version === '') {
        return '';
    }
    if ($version[0] === 'v' || $version[0] === 'V') {
        $version = substr($version, 1);
    }
    return trim($version);
}

function safeVersionCompare($left, $right, $operator)
{
    $leftNormalized = normalizeVersion($left);
    $rightNormalized = normalizeVersion($right);
    if ($leftNormalized === '' || $rightNormalized === '') {
        return false;
    }
    return version_compare($leftNormalized, $rightNormalized, $operator);
}

function safeHasUpdate($latestVersion, $currentVersion)
{
    return safeVersionCompare($latestVersion, $currentVersion, '>');
}

function readJsonFile($path, $fallback)
{
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

function readTextFile($path, $fallback)
{
    if (!is_string($path) || !file_exists($path) || !is_readable($path)) {
        return $fallback;
    }
    $data = @file_get_contents($path);
    if (!is_string($data) || trim($data) === '') {
        return $fallback;
    }
    return $data;
}

function readGuidanceDocs($baseDir)
{
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

function writeJsonFile($path, $data)
{
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

function canWriteJsonPath($path)
{
    if (file_exists($path)) {
        return is_writable($path);
    }
    return is_writable(dirname($path));
}

function formatWriteFailure($path, $label)
{
    $dir = dirname($path);
    $fileWritable = file_exists($path) ? (is_writable($path) ? 'yes' : 'no') : 'missing';
    $dirWritable = is_writable($dir) ? 'yes' : 'no';

    return 'Failed to write ' . $label
        . ' (path=' . $path
        . ', fileWritable=' . $fileWritable
        . ', dirWritable=' . $dirWritable
        . ').';
}

function readSystemTotalMemMiB()
{
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

function buildRuntimeMemoryHealth()
{
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

function readExtMgrServiceHealth()
{
    $statePath = '/var/www/extensions/sys/.ext-mgr/service-state.json';
    $fallback = [
        'status' => 'not-installed',
        'detail' => 'service-state-missing',
    ];

    if (!is_readable($statePath)) {
        return $fallback;
    }

    $data = readJsonFile($statePath, $fallback);
    if (!is_array($data)) {
        return $fallback;
    }

    $updatedAt = isset($data['updatedAt']) ? strtotime((string)$data['updatedAt']) : false;
    if ($updatedAt === false) {
        return [
            'status' => (string)($data['status'] ?? 'unknown'),
            'detail' => (string)($data['detail'] ?? 'missing-updated-at'),
        ];
    }

    $ageSeconds = time() - $updatedAt;
    $status = (string)($data['status'] ?? 'unknown');
    if ($ageSeconds > 120 && $status === 'online') {
        $status = 'stale';
    }

    return [
        'status' => $status,
        'detail' => (string)($data['detail'] ?? 'ok'),
        'updatedAt' => (string)($data['updatedAt'] ?? ''),
    ];
}

function readExtMgrWatchdogHealth()
{
    $statePath = '/var/www/extensions/sys/.ext-mgr/watchdog-state.json';
    $fallback = [
        'status' => 'not-installed',
        'detail' => 'watchdog-state-missing',
    ];

    if (!is_readable($statePath)) {
        return $fallback;
    }

    $data = readJsonFile($statePath, $fallback);
    if (!is_array($data)) {
        return $fallback;
    }

    $updatedAt = isset($data['updatedAt']) ? strtotime((string)$data['updatedAt']) : false;
    if ($updatedAt === false) {
        return [
            'status' => (string)($data['status'] ?? 'unknown'),
            'detail' => (string)($data['detail'] ?? 'missing-updated-at'),
        ];
    }

    $ageSeconds = time() - $updatedAt;
    $status = (string)($data['status'] ?? 'unknown');
    if ($ageSeconds > 180 && $status === 'online') {
        $status = 'stale';
    }

    return [
        'status' => $status,
        'detail' => (string)($data['detail'] ?? 'ok'),
        'updatedAt' => (string)($data['updatedAt'] ?? ''),
    ];
}

function logTypes()
{
    return ['install', 'system', 'error'];
}

function ensureExtMgrLogLayout()
{
    global $extensionsSysLogsRootPath, $extensionsLogsPath, $extMgrLogsPath;

    ensureDirectory($extensionsSysLogsRootPath, 0775);
    ensureDirectory($extensionsLogsPath, 0775);
    ensureDirectory($extMgrLogsPath, 0775);

    foreach (logTypes() as $type) {
        $path = $extMgrLogsPath . DIRECTORY_SEPARATOR . $type . '.log';
        if (!file_exists($path)) {
            @file_put_contents($path, '');
        }
    }
}

function ensureExtensionLogLayout($extensionId)
{
    global $extensionsInstalledPath, $extensionsLogsPath;

    if (!isValidExtensionId($extensionId)) {
        return;
    }

    $globalDir = $extensionsLogsPath . DIRECTORY_SEPARATOR . $extensionId;
    ensureDirectory($globalDir, 0775);

    foreach (logTypes() as $type) {
        $path = $globalDir . DIRECTORY_SEPARATOR . $type . '.log';
        if (!file_exists($path)) {
            @file_put_contents($path, '');
        }
    }

    $localDir = $extensionsInstalledPath . DIRECTORY_SEPARATOR . $extensionId . DIRECTORY_SEPARATOR . 'logs';
    if (is_dir(dirname($localDir)) || is_dir($extensionsInstalledPath . DIRECTORY_SEPARATOR . $extensionId)) {
        ensureDirectory($localDir, 0775);
        foreach (logTypes() as $type) {
            $path = $localDir . DIRECTORY_SEPARATOR . $type . '.log';
            if (!file_exists($path)) {
                @file_put_contents($path, '');
            }
        }
    }
}

function appendLogLine($path, $message)
{
    if (!is_string($path) || $path === '' || !is_string($message) || trim($message) === '') {
        return;
    }

    $dir = dirname($path);
    ensureDirectory($dir, 0775);

    $line = '[' . date('Y-m-d H:i:s') . '] ' . trim($message) . PHP_EOL;
    @file_put_contents($path, $line, FILE_APPEND);
}

function appendExtMgrLog($type, $message)
{
    global $extMgrLogsPath;

    $type = trim((string)$type);
    if (!in_array($type, logTypes(), true)) {
        $type = 'system';
    }

    ensureExtMgrLogLayout();
    appendLogLine($extMgrLogsPath . DIRECTORY_SEPARATOR . $type . '.log', $message);
}

function appendExtensionLog($extensionId, $type, $message)
{
    global $extensionsLogsPath, $extensionsInstalledPath;

    if (!isValidExtensionId($extensionId)) {
        return;
    }

    $type = trim((string)$type);
    if (!in_array($type, logTypes(), true)) {
        $type = 'system';
    }

    ensureExtensionLogLayout($extensionId);

    $globalPath = $extensionsLogsPath . DIRECTORY_SEPARATOR . $extensionId . DIRECTORY_SEPARATOR . $type . '.log';
    appendLogLine($globalPath, $message);

    $localPath = $extensionsInstalledPath . DIRECTORY_SEPARATOR . $extensionId . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . $type . '.log';
    if (is_dir(dirname($localPath))) {
        appendLogLine($localPath, $message);
    }
}

function buildLogRow($key, $label, $path, $source)
{
    $exists = is_file($path);
    $size = $exists ? @filesize($path) : null;
    $mtime = $exists ? @filemtime($path) : false;

    return [
        'key' => $key,
        'label' => $label,
        'source' => $source,
        'pathHint' => $path,
        'exists' => $exists,
        'sizeBytes' => (is_int($size) || is_float($size)) ? (int)$size : 0,
        'updatedAt' => $mtime ? date('c', (int)$mtime) : null,
    ];
}

function buildVirtualLogRow($key, $label, $source)
{
    return [
        'key' => $key,
        'label' => $label,
        'source' => $source,
        'pathHint' => null,
        'exists' => true,
        'sizeBytes' => 0,
        'updatedAt' => date('c'),
    ];
}

function extMgrSystemLogCandidates()
{
    return [
        ['key' => 'moode', 'label' => 'moOde Log (/var/log/moode.log)', 'path' => '/var/log/moode.log'],
        ['key' => 'syslog', 'label' => 'System Log (/var/log/syslog)', 'path' => '/var/log/syslog'],
        ['key' => 'daemon', 'label' => 'Daemon Log (/var/log/daemon.log)', 'path' => '/var/log/daemon.log'],
        ['key' => 'kern', 'label' => 'Kernel Log (/var/log/kern.log)', 'path' => '/var/log/kern.log'],
        ['key' => 'messages', 'label' => 'System Messages (/var/log/messages)', 'path' => '/var/log/messages'],
    ];
}

function buildCombinedLogContent($targetId, $lineLimit = 120)
{
    $target = trim((string)$targetId);
    if ($target === '') {
        return "[ext-mgr] combined logs unavailable: missing target id.\n";
    }

    $rows = availableLogsForTarget($target);
    $parts = [];
    $moodeLogMissing = false;

    foreach ($rows as $row) {
        $key = (string)($row['key'] ?? '');
        $path = (string)($row['pathHint'] ?? '');
        if ($key === '' || $key === 'all') {
            continue;
        }

        $label = (string)($row['label'] ?? $key);
        $source = (string)($row['source'] ?? 'runtime');
        if ($key === 'moode' && !is_file($path)) {
            $moodeLogMissing = true;
        }

        $parts[] = "===== " . $label . " | key=" . $key . " | source=" . $source . " | path=" . $path . " =====";
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            $parts[] = '[missing or unreadable]';
            $parts[] = '';
            continue;
        }

        $content = tailFileContent($path, $lineLimit);
        $parts[] = $content !== '' ? $content : '[empty]';
        $parts[] = '';
    }

    if ($target === 'ext-mgr' && $moodeLogMissing) {
        $parts[] = '[note] /var/log/moode.log is not available. moOde debug logging may be disabled on this host.';
        $parts[] = '';
    }

    if (count($parts) === 0) {
        return "[ext-mgr] combined logs unavailable: no logs discovered for target " . $target . ".\n";
    }

    return implode(PHP_EOL, $parts);
}

function availableLogsForTarget($targetId)
{
    global $extensionsLogsPath, $extMgrLogsPath, $extMgrRuntimeLogsPath, $extensionsInstalledPath;

    ensureExtMgrLogLayout();

    $logs = [];

    if ($targetId === 'ext-mgr') {
        $logs[] = buildVirtualLogRow('all', 'All Logs (combined)', 'combined');
        $logs[] = buildLogRow('install', 'Install Log', $extMgrLogsPath . DIRECTORY_SEPARATOR . 'install.log', 'ext-mgr');
        $logs[] = buildLogRow('system', 'System Log', $extMgrLogsPath . DIRECTORY_SEPARATOR . 'system.log', 'ext-mgr');
        $logs[] = buildLogRow('error', 'Error Log', $extMgrLogsPath . DIRECTORY_SEPARATOR . 'error.log', 'ext-mgr');
        $logs[] = buildLogRow('service', 'Service Runtime Log', $extMgrRuntimeLogsPath . DIRECTORY_SEPARATOR . 'moode-extmgr-service.log', 'runtime');
        $logs[] = buildLogRow('watchdog', 'Watchdog Runtime Log', $extMgrRuntimeLogsPath . DIRECTORY_SEPARATOR . 'moode-extmgr-watchdog.log', 'runtime');
        $logs[] = buildLogRow('install-helper', 'Install Helper Log', $extMgrRuntimeLogsPath . DIRECTORY_SEPARATOR . 'install-helper.log', 'runtime');
        foreach (extMgrSystemLogCandidates() as $candidate) {
            $logs[] = buildLogRow((string)$candidate['key'], (string)$candidate['label'], (string)$candidate['path'], 'system');
        }
        return $logs;
    }

    ensureExtensionLogLayout($targetId);

    $globalDir = $extensionsLogsPath . DIRECTORY_SEPARATOR . $targetId;
    $localDir = $extensionsInstalledPath . DIRECTORY_SEPARATOR . $targetId . DIRECTORY_SEPARATOR . 'logs';

    $logs[] = buildVirtualLogRow('all', 'All Logs (combined)', 'combined');
    $logs[] = buildLogRow('install', 'Install Log', $globalDir . DIRECTORY_SEPARATOR . 'install.log', 'global');
    $logs[] = buildLogRow('system', 'System Log', $globalDir . DIRECTORY_SEPARATOR . 'system.log', 'global');
    $logs[] = buildLogRow('error', 'Error Log', $globalDir . DIRECTORY_SEPARATOR . 'error.log', 'global');
    $logs[] = buildLogRow('local-install', 'Local Install Log', $localDir . DIRECTORY_SEPARATOR . 'install.log', 'extension');
    $logs[] = buildLogRow('local-system', 'Local System Log', $localDir . DIRECTORY_SEPARATOR . 'system.log', 'extension');
    $logs[] = buildLogRow('local-error', 'Local Error Log', $localDir . DIRECTORY_SEPARATOR . 'error.log', 'extension');

    return $logs;
}

function resolveLogPathForRead($targetId, $key, &$error)
{
    $error = '';
    $targetId = trim((string)$targetId);
    $key = trim((string)$key);

    if ($targetId === '') {
        $error = 'Missing id parameter.';
        return null;
    }

    if ($targetId !== 'ext-mgr' && !isValidExtensionId($targetId)) {
        $error = 'Invalid extension id.';
        return null;
    }

    $rows = availableLogsForTarget($targetId);
    foreach ($rows as $row) {
        if ((string)($row['key'] ?? '') !== $key) {
            continue;
        }
        return (string)($row['pathHint'] ?? '');
    }

    $error = 'Unknown log key for target.';
    return null;
}

function tailFileContent($path, $lineLimit = 120)
{
    if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
        return '';
    }

    $lineLimit = max(1, min(400, (int)$lineLimit));
    $content = @file_get_contents($path);
    if (!is_string($content) || $content === '') {
        return '';
    }

    $lines = preg_split('/\r?\n/', $content);
    if (!is_array($lines)) {
        return '';
    }

    if (count($lines) > $lineLimit) {
        $lines = array_slice($lines, -$lineLimit);
    }

    return implode(PHP_EOL, $lines);
}

function readLogLines($path, $lineLimit = 1200)
{
    if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
        return [];
    }

    $lineLimit = max(1, min(5000, (int)$lineLimit));
    $content = @file_get_contents($path);
    if (!is_string($content) || $content === '') {
        return [];
    }

    $lines = preg_split('/\r?\n/', $content);
    if (!is_array($lines)) {
        return [];
    }

    if (count($lines) > $lineLimit) {
        $lines = array_slice($lines, -$lineLimit);
    }

    return $lines;
}

function parseLogLineEvent($line)
{
    $raw = trim((string)$line);
    if ($raw === '') {
        return null;
    }

    $timestamp = null;
    $message = $raw;
    if (preg_match('/^\[([0-9]{4}-[0-9]{2}-[0-9]{2}\s+[0-9]{2}:[0-9]{2}:[0-9]{2})\]\s*(.*)$/', $raw, $m) === 1) {
        $timestamp = (string)$m[1];
        $message = (string)$m[2];
    }

    return [
        'timestamp' => $timestamp,
        'message' => $message,
        'raw' => $raw,
    ];
}

function normalizeErrorSignature($message)
{
    $msg = strtolower(trim((string)$message));
    if ($msg === '') {
        return '';
    }

    $msg = preg_replace('/\b[0-9]+\b/', '#', $msg);
    $msg = preg_replace('/\s+/', ' ', (string)$msg);
    return trim((string)$msg);
}

function lineLooksError($line)
{
    $l = strtolower((string)$line);
    return (strpos($l, 'error') !== false
        || strpos($l, 'failed') !== false
        || strpos($l, 'exception') !== false
        || strpos($l, 'degraded') !== false
        || strpos($l, 'inactive') !== false
        || strpos($l, 'stale') !== false);
}

function lineLooksRestartEvent($line)
{
    $l = strtolower((string)$line);
    return (strpos($l, 'restart') !== false
        || strpos($l, 'restarting') !== false
        || strpos($l, 'heartbeat stale') !== false
        || strpos($l, 'state=failed') !== false
        || strpos($l, 'state=inactive') !== false);
}

function summarizeLogsForTarget($targetId, $lineLimit = 1200)
{
    $rows = availableLogsForTarget($targetId);

    $systemLines = 0;
    $errorLines = 0;
    $totalLines = 0;
    $errorBuckets = [];
    $restartEvents = [];

    foreach ($rows as $row) {
        $key = (string)($row['key'] ?? '');
        $path = (string)($row['pathHint'] ?? '');
        $lines = readLogLines($path, $lineLimit);
        if (count($lines) === 0) {
            continue;
        }

        $totalLines += count($lines);
        $isSystemSource = (strpos($key, 'system') !== false || $key === 'service' || $key === 'watchdog');
        $isErrorSource = (strpos($key, 'error') !== false);

        foreach ($lines as $line) {
            $event = parseLogLineEvent($line);
            if (!is_array($event)) {
                continue;
            }

            if ($isSystemSource) {
                $systemLines++;
            }

            $errorMatch = $isErrorSource || lineLooksError($event['message']);
            if ($errorMatch) {
                $errorLines++;
                $sig = normalizeErrorSignature($event['message']);
                if ($sig !== '') {
                    if (!isset($errorBuckets[$sig])) {
                        $errorBuckets[$sig] = 0;
                    }
                    $errorBuckets[$sig]++;
                }
            }

            if (lineLooksRestartEvent($event['message'])) {
                $restartEvents[] = [
                    'id' => $targetId,
                    'at' => $event['timestamp'],
                    'message' => $event['message'],
                ];
            }
        }
    }

    arsort($errorBuckets, SORT_NUMERIC);
    $topErrors = [];
    foreach ($errorBuckets as $signature => $count) {
        $topErrors[] = ['signature' => $signature, 'count' => (int)$count];
        if (count($topErrors) >= 8) {
            break;
        }
    }

    usort($restartEvents, static function ($a, $b) {
        $ta = strtotime((string)($a['at'] ?? '')) ?: 0;
        $tb = strtotime((string)($b['at'] ?? '')) ?: 0;
        if ($ta === $tb) {
            return strcmp((string)($a['message'] ?? ''), (string)($b['message'] ?? ''));
        }
        return $tb <=> $ta;
    });

    $denominator = max(1, $systemLines + $errorLines);
    $errorRatePct = round(($errorLines / $denominator) * 100.0, 2);

    return [
        'id' => $targetId,
        'totalLines' => $totalLines,
        'systemLines' => $systemLines,
        'errorLines' => $errorLines,
        'errorRatePct' => $errorRatePct,
        'topErrors' => $topErrors,
        'restartEvents' => array_slice($restartEvents, 0, 25),
    ];
}

function discoverKnownExtensionIds($registryPath, $extensionsLogsPath)
{
    $ids = [];

    $registry = normalizeRegistry(readRegistry($registryPath));
    foreach ((array)($registry['extensions'] ?? []) as $ext) {
        $id = trim((string)($ext['id'] ?? ''));
        if ($id !== '' && isValidExtensionId($id)) {
            $ids[$id] = true;
        }
    }

    if (is_dir($extensionsLogsPath)) {
        $entries = @scandir($extensionsLogsPath);
        if (is_array($entries)) {
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $full = $extensionsLogsPath . DIRECTORY_SEPARATOR . $entry;
                if (is_dir($full) && isValidExtensionId($entry)) {
                    $ids[$entry] = true;
                }
            }
        }
    }

    return array_values(array_keys($ids));
}

function buildLogAnalysisPayload($registryPath, $extensionsLogsPath, $targetId = '')
{
    $targetId = trim((string)$targetId);

    if ($targetId !== '') {
        return [
            'scope' => 'single',
            'target' => summarizeLogsForTarget($targetId),
        ];
    }

    $ids = discoverKnownExtensionIds($registryPath, $extensionsLogsPath);
    sort($ids, SORT_STRING);

    $perExtension = [];
    $globalErrors = [];
    $restartEvents = summarizeLogsForTarget('ext-mgr')['restartEvents'];

    foreach ($ids as $id) {
        $summary = summarizeLogsForTarget($id);
        $perExtension[] = [
            'id' => $id,
            'totalLines' => $summary['totalLines'],
            'systemLines' => $summary['systemLines'],
            'errorLines' => $summary['errorLines'],
            'errorRatePct' => $summary['errorRatePct'],
        ];

        foreach ((array)$summary['topErrors'] as $row) {
            $sig = (string)($row['signature'] ?? '');
            $cnt = (int)($row['count'] ?? 0);
            if ($sig === '' || $cnt <= 0) {
                continue;
            }
            if (!isset($globalErrors[$sig])) {
                $globalErrors[$sig] = 0;
            }
            $globalErrors[$sig] += $cnt;
        }

        foreach ((array)$summary['restartEvents'] as $event) {
            $restartEvents[] = $event;
        }
    }

    usort($perExtension, static function ($a, $b) {
        $rateCmp = ((float)$b['errorRatePct'] <=> (float)$a['errorRatePct']);
        if ($rateCmp !== 0) {
            return $rateCmp;
        }
        return strcmp((string)$a['id'], (string)$b['id']);
    });

    arsort($globalErrors, SORT_NUMERIC);
    $topErrors = [];
    foreach ($globalErrors as $sig => $cnt) {
        $topErrors[] = ['signature' => $sig, 'count' => (int)$cnt];
        if (count($topErrors) >= 12) {
            break;
        }
    }

    usort($restartEvents, static function ($a, $b) {
        $ta = strtotime((string)($a['at'] ?? '')) ?: 0;
        $tb = strtotime((string)($b['at'] ?? '')) ?: 0;
        if ($ta === $tb) {
            return strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
        }
        return $tb <=> $ta;
    });

    return [
        'scope' => 'global',
        'extensionCount' => count($perExtension),
        'perExtension' => $perExtension,
        'topErrors' => $topErrors,
        'restartEvents' => array_slice($restartEvents, 0, 40),
    ];
}

function ensureDirectory($path, $mode = 0775)
{
    if (!is_string($path) || $path === '') {
        return false;
    }
    if (is_dir($path)) {
        return true;
    }
    return mkdir($path, $mode, true) || is_dir($path);
}

function removePathRecursiveWithStats($path, &$removedEntries, &$freedBytes)
{
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

function clearDirectoryContents($dir, &$removedEntries, &$freedBytes, &$error)
{
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

function computeDirectorySizeBytes($path)
{
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

function computeDirectoryEntryCount($path)
{
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

function copyPathRecursive($sourcePath, $targetPath, &$copiedItems, &$error)
{
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

function createExtMgrBackupSnapshot($baseDir, $backupRoot, &$snapshotPath, &$copiedItems, &$error)
{
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

function readCpuUsageSamplePct()
{
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

function readMemoryOverview()
{
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

function diskUsageForPath($path)
{
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

function readProcessRssMiBFromProc($pid)
{
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

function estimateExtensionRuntimeMemory($extensions)
{
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
            if (
                strpos($cmdline, '/extensions/installed/' . $id . '/') === false
                && strpos($cmdline, $id . '.service') === false
                && strpos($cmdline, 'ext-mgr-' . $id) === false
            ) {
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

function buildExtensionsStorageSummary($extensionsInstalledPath, $registryExtensions)
{
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

function readBackupSnapshotInfo($backupRoot)
{
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

function buildMaintenanceStatus($cacheDir, $backupRoot)
{
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

function buildSystemResourceSnapshot($registryExtensions, $extensionsInstalledPath)
{
    $memory = readMemoryOverview();
    $load = sys_getloadavg();
    $runtimeMemory = buildRuntimeMemoryHealth();
    $serviceHealth = readExtMgrServiceHealth();

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

function readMeta($path)
{
    $defaults = defaultMeta();
    $meta = readJsonFile($path, $defaults);
    // Shallow merge with defaults so missing keys remain available.
    $meta = array_replace_recursive($defaults, $meta);
    return $meta;
}

function readVersionValue($path)
{
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

function writeVersionValue($path, $version)
{
    if (!is_string($version) || trim($version) === '') {
        return false;
    }
    return file_put_contents($path, trim($version) . PHP_EOL) !== false;
}

function writeTextFileAtomic($path, $content)
{
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $content) === false) {
        return false;
    }
    return rename($tmp, $path);
}

function sanitizeExtensionId($value)
{
    $id = strtolower(trim((string)$value));
    if ($id === '' || preg_match('/^[a-z0-9._-]+$/', $id) !== 1) {
        return 'template-extension';
    }
    return $id;
}

function isPlaceholderExtensionId($value)
{
    $id = strtolower(trim((string)$value));
    if ($id === '') {
        return true;
    }

    $placeholders = [
        'template-extension',
        'auto-generate',
        'auto',
        'your-extension-id',
        'todo-extension-id',
    ];

    return in_array($id, $placeholders, true);
}

function generateUuidV4()
{
    try {
        $bytes = random_bytes(16);
    } catch (Throwable $e) {
        return strtolower(uniqid('ext-', true));
    }

    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    $hex = bin2hex($bytes);
    return substr($hex, 0, 8)
        . '-' . substr($hex, 8, 4)
        . '-' . substr($hex, 12, 4)
        . '-' . substr($hex, 16, 4)
        . '-' . substr($hex, 20, 12);
}

function extensionIdExists($extId, $registryPath)
{
    $id = trim((string)$extId);
    if ($id === '') {
        return false;
    }

    if (is_dir('/var/www/extensions/installed/' . $id)) {
        return true;
    }

    $registry = readRegistry($registryPath);
    $extensions = is_array($registry['extensions'] ?? null) ? $registry['extensions'] : [];
    foreach ($extensions as $ext) {
        if (!is_array($ext)) {
            continue;
        }
        if ((string)($ext['id'] ?? '') === $id) {
            return true;
        }
    }

    return false;
}

function generateManagedExtensionId($registryPath)
{
    for ($i = 0; $i < 20; $i++) {
        $candidate = 'ext-' . generateUuidV4();
        if (!extensionIdExists($candidate, $registryPath)) {
            return $candidate;
        }
    }

    return 'ext-' . strtolower(bin2hex(random_bytes(6)));
}

function updateImportedManifestWithManagedId($sourceDir, $manifestData, $newId, &$error)
{
    $error = '';
    if (!is_array($manifestData)) {
        $error = 'Invalid manifest payload for managed id update.';
        return null;
    }

    $oldId = strtolower(trim((string)($manifestData['id'] ?? '')));
    $manifestData['id'] = $newId;

    $extMgr = is_array($manifestData['ext_mgr'] ?? null) ? $manifestData['ext_mgr'] : [];
    $service = is_array($extMgr['service'] ?? null) ? $extMgr['service'] : [];
    $serviceName = trim((string)($service['name'] ?? ''));
    $serviceIdPart = preg_replace('/\.service$/', '', strtolower($serviceName));
    if (!is_string($serviceIdPart)) {
        $serviceIdPart = '';
    }
    if ($serviceName === '' || isPlaceholderExtensionId($serviceIdPart) || ($oldId !== '' && $serviceName === $oldId . '.service')) {
        $service['name'] = $newId . '.service';
    }
    $extMgr['service'] = $service;

    $logging = is_array($extMgr['logging'] ?? null) ? $extMgr['logging'] : [];
    $logging['globalDir'] = '/var/www/extensions/sys/logs/extensionslogs/' . $newId;
    $extMgr['logging'] = $logging;
    $manifestData['ext_mgr'] = $extMgr;

    $manifestPath = rtrim($sourceDir, '/\\') . DIRECTORY_SEPARATOR . 'manifest.json';
    if (!writeJsonFile($manifestPath, $manifestData)) {
        $error = 'Failed to write managed extension id into manifest.json.';
        return null;
    }

    $infoPath = rtrim($sourceDir, '/\\') . DIRECTORY_SEPARATOR . 'info.json';
    if (is_file($infoPath)) {
        $info = readJsonFile($infoPath, []);
        if (is_array($info)) {
            $settingsPage = trim((string)($info['settingsPage'] ?? ''));
            if ($settingsPage === '' || $settingsPage === '/' . $oldId . '.php' || $settingsPage === '/template-extension.php') {
                $info['settingsPage'] = '/' . $newId . '.php';
            }
            writeJsonFile($infoPath, $info);
        }
    }

    return $manifestData;
}

function buildTemplatePackageFiles($extensionId)
{
    $displayName = ucwords(str_replace(['-', '_', '.'], ' ', $extensionId));
    $defaultIconClass = 'fa-solid fa-sharp fa-puzzle-piece';

    $manifest = [
        'id' => 'auto-generate',
        'name' => $displayName,
        'version' => '0.1.0',
        'main' => 'template.php',
        'ext_mgr' => [
            'enabled' => true,
            'state' => 'active',
            'stageProfile' => 'visible-by-default',
            'menuVisibility' => [
                'm' => true,
                'library' => true,
                'system' => false,
            ],
            'settingsCardOnly' => true,
            'iconClass' => $defaultIconClass,
            'service' => [
                'name' => 'auto-generate.service',
                'requiresExtMgr' => true,
                'parentService' => 'moode-extmgr.service',
                'dependencies' => [],
            ],
            'logging' => [
                'localDir' => 'logs',
                'globalDir' => '/var/www/extensions/sys/logs/extensionslogs/auto-generate',
                'files' => ['install.log', 'system.log', 'error.log'],
            ],
            'install' => [
                'packages' => [],
                'script' => 'scripts/install.sh',
            ],
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
        . "\$extRouteId = preg_replace('/\\.php$/', '', basename((string)(\$_SERVER['SCRIPT_NAME'] ?? '{$extensionId}.php')));\n"
        . "if (!is_string(\$extRouteId) || trim(\$extRouteId) === '') {\n"
        . "    \$extRouteId = '{$extensionId}';\n"
        . "}\n"
        . "\$assetBase = '/extensions/installed/' . \$extRouteId;\n\n"
        . "if (function_exists('storeBackLink')) {\n"
        . "    @storeBackLink(\$section, \$extRouteId);\n"
        . "}\n\n"
        . "if (file_exists('/var/www/header.php')) {\n"
        . "    \$usingMoodeShell = true;\n"
        . "    include '/var/www/header.php';\n"
        . "    echo '<link rel=\"stylesheet\" href=\"' . htmlspecialchars(\$assetBase, ENT_QUOTES, 'UTF-8') . '/assets/css/template.css\">' . \"\\n\";\n"
        . "} else {\n"
        . "    ?>\n"
        . "<!doctype html>\n"
        . "<html lang=\"en\">\n"
        . "<head>\n"
        . "    <meta charset=\"utf-8\">\n"
        . "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
        . "    <title>{$displayName}</title>\n"
        . "    <?php echo '<link rel=\"stylesheet\" href=\"' . htmlspecialchars(\$assetBase, ENT_QUOTES, 'UTF-8') . '/assets/css/template.css\">'; ?>\n"
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
        . "    <?php // YOUR CODE HERE: add your minimal extension settings UI controls in this page. ?>\n"
        . "\n"
        . "    <section class=\"ext-template-card\">\n"
        . "      <h2 class=\"ext-template-card-title\">Icon Picker (Starter)</h2>\n"
        . "      <p class=\"config-help-static\">Pick an icon class and copy it into info.json \"iconClass\".</p>\n"
        . "      <div class=\"ext-template-picker-row\">\n"
        . "        <label for=\"ext-template-icon-picker\">Icon</label>\n"
        . "        <select id=\"ext-template-icon-picker\"></select>\n"
        . "      </div>\n"
        . "      <div id=\"ext-template-icon-value\" class=\"ext-template-code\">{$defaultIconClass}</div>\n"
        . "      <div class=\"ext-template-code\">YOUR CODE HERE: connect controls to backend/api.php and persist extension settings.</div>\n"
        . "    </section>\n"
        . "  </div>\n"
        . "</div>\n"
        . "\n"
        . "<?php echo '<script src=\"' . htmlspecialchars(\$assetBase, ENT_QUOTES, 'UTF-8') . '/assets/js/template.js\"></script>'; ?>\n"
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
        . "  // YOUR CODE HERE: replace this starter list or append your own UI logic.\n"
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
            'profile' => 'visible-by-default',
            'description' => 'Default visibility profile for ext-mgr menu surfaces.',
            'menuVisibility' => [
                'm' => true,
                'library' => true,
                'system' => false,
            ],
            'settingsCardOnly' => true,
            'notes' => [
                'Default starts visible in M and Library, hidden in System.',
                'Keep settingsCardOnly enabled for quick configure-card access.',
                'Flip system=true when you want a System/Configure menu presence.',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        'template.php' => $templatePhp,
        'assets/js/template.js' => $templateJs,
        'assets/css/template.css' => $templateCss,
        'scripts/install.sh' => "#!/usr/bin/env bash\nset -euo pipefail\n\nEXT_ID='{$extensionId}'\nROOT=\"\${EXT_MGR_EXTENSION_ROOT:-/var/www/extensions/installed/\$EXT_ID}\"\nSERVICE_NAME=\"\${EXT_ID}.service\"\nSERVICE_FILE=\"\$ROOT/scripts/\$SERVICE_NAME\"\n\ncase \"\$ROOT\" in\n  /var/www/extensions/installed/*) ;;\n  *)\n    echo \"[\$EXT_ID] skip install script: unsafe ROOT='\$ROOT'\"\n    exit 0\n    ;;\nesac\n\n# Keep install hook non-destructive by default: no extra folder creation here.\nif [[ -f \"\$SERVICE_FILE\" && -d /etc/systemd/system && -w /etc/systemd/system && -x /usr/bin/systemctl ]]; then\n  install -m 0644 \"\$SERVICE_FILE\" \"/etc/systemd/system/\$SERVICE_NAME\"\n  systemctl daemon-reload\n  systemctl enable --now moode-extmgr.service >/dev/null 2>&1 || true\n  systemctl enable --now \"\$SERVICE_NAME\" >/dev/null 2>&1 || true\nfi\n\necho \"[\$EXT_ID] default install completed\"\n",
        'scripts/repair.sh' => "#!/usr/bin/env bash\nset -euo pipefail\n\nEXT_ID='{$extensionId}'\nROOT=\"\${EXT_MGR_EXTENSION_ROOT:-/var/www/extensions/installed/\$EXT_ID}\"\n\ncase \"\$ROOT\" in\n  /var/www/extensions/installed/*) ;;\n  *)\n    echo \"[\$EXT_ID] skip repair script: unsafe ROOT='\$ROOT'\"\n    exit 0\n    ;;\nesac\n\n# Repair may recreate runtime dirs, but only inside managed extension root.\nmkdir -p \"\$ROOT/logs\" \"\$ROOT/cache\" \"\$ROOT/data\" 2>/dev/null || true\nfind \"\$ROOT/scripts\" -maxdepth 1 -type f \( -name '*.sh' -o -name '*.service' \) -print >/dev/null 2>&1 || true\n\necho \"[\$EXT_ID] default repair completed\"\n",
        'scripts/uninstall.sh' => "#!/usr/bin/env bash\nset -euo pipefail\n\nEXT_ID='{$extensionId}'\nROOT=\"/var/www/extensions/installed/\$EXT_ID\"\nSERVICE_NAME=\"\${EXT_ID}.service\"\n\nif [[ -x /usr/bin/systemctl ]]; then\n  systemctl disable --now \"\$SERVICE_NAME\" >/dev/null 2>&1 || true\nfi\nif [[ -w /etc/systemd/system ]]; then\n  rm -f \"/etc/systemd/system/\$SERVICE_NAME\"\n  if [[ -x /usr/bin/systemctl ]]; then\n    systemctl daemon-reload >/dev/null 2>&1 || true\n  fi\nfi\nrm -rf \"\$ROOT/cache\" \"\$ROOT/data\" \"\$ROOT/logs\"\n\necho \"[\$EXT_ID] default uninstall completed\"\n",
        'scripts/service-runner.sh' => "#!/usr/bin/env bash\nset -euo pipefail\n\nEXT_ID='{$extensionId}'\nwhile true; do\n  echo \"[\$(date +'%Y-%m-%d %H:%M:%S')] [\$EXT_ID] service heartbeat\"\n  sleep 60\ndone\n",
        'scripts/' . $extensionId . '.service' => "[Unit]\nDescription={$displayName} extension service\nRequires=moode-extmgr.service\nAfter=moode-extmgr.service network.target\nPartOf=moode-extmgr.service\n\n[Service]\nType=simple\nUser=moode-extmgrusr\nGroup=moode-extmgr\nWorkingDirectory=/var/www/extensions/installed/{$extensionId}\nExecStart=/usr/bin/env bash /var/www/extensions/installed/{$extensionId}/scripts/service-runner.sh\nRestart=always\nRestartSec=5\n\n[Install]\nWantedBy=multi-user.target\n",
        'README.md' => "# {$displayName}\n\nGenerated by ext-mgr Import Wizard template kit.\n\n## Import behavior\n- Installs into /var/www/extensions/installed/<managed-id>\n- Creates canonical route /<managed-id>.php\n- If manifest id is placeholder/missing, ext-mgr generates a unique managed id automatically\n- Starts visible in M menu and Library menu, hidden in System/Configure\n- Starts with settings-card mode enabled\n\n## Template structure\n- ExtensionTemplate/assets\n- ExtensionTemplate/backend\n- ExtensionTemplate/templates\n- ExtensionTemplate/scripts\n- ExtensionTemplate/packages\n- ExtensionTemplate/data\n- ExtensionTemplate/cache\n\n## Bare minimum UI requirements\n- Include /var/www/header.php and footer integration\n- Show one page title (`h1.config-title`)\n- Show one help line (`p.config-help-static`)\n- Keep one settings card container for user controls\n- Keep route reachable without PHP warnings/errors\n\n## YOUR CODE HERE placeholders\n- template.php contains explicit YOUR CODE HERE markers for UI and setting flow\n- assets/js/template.js has a starter marker for custom UI logic\n- backend/api.php is a starter endpoint to extend\n\n## Default maintenance scripts\n- scripts/install.sh is non-destructive by default and only installs the service when permissions allow it\n- scripts/repair.sh restores runtime directories under the managed extension root\n- scripts/uninstall.sh removes runtime artifacts and disables the extension service when possible\n\n## Packages and dependencies\n- Put bundled dependency artifacts under packages/\n- packages/services can ship extra systemd units that ext-mgr normalizes to moode-extmgrusr:moode-extmgr\n- ext_mgr.install.packages can declare apt packages for host installation\n- ext_mgr.service.dependencies can declare unit dependencies to inject into the main extension service\n\n## Logging layout\n- Extension global logs: /var/www/extensions/sys/logs/extensionslogs/<managed-id>\n- Extension local logs: /var/www/extensions/installed/<managed-id>/logs\n- Default files: install.log, system.log, error.log\n",
        'backend/api.php' => "<?php\nheader('Content-Type: application/json; charset=utf-8');\n\n// YOUR CODE HERE: implement extension-specific API actions and persistence.\necho json_encode([\n    'ok' => true,\n    'extension' => '{$extensionId}',\n    'message' => 'Starter backend endpoint is reachable.',\n], JSON_UNESCAPED_SLASHES);\n",
        'templates/.gitkeep' => "",
        'backend/.gitkeep' => "",
        'assets/images/.gitkeep' => "",
        'packages/.gitkeep' => "",
        'packages/services/.gitkeep' => "",
        'assets/css/.gitkeep' => "",
        'logs/.gitkeep' => "",
        'cache/.gitkeep' => "",
        'data/.gitkeep' => "",
    ];
}

function writeTemplateFilesToDirectory($rootDir, $files, &$error)
{
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

function writeTemplateZipViaCommand($zipPath, $extensionId, $files, &$error)
{
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
    $packageRootName = 'ExtensionTemplate';
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

function writeTemplateZipArchive($zipPath, $extensionId, &$error)
{
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

function isSafeArchiveEntryPath($entryPath)
{
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

function listZipEntriesViaUnzip($zipPath, &$error)
{
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

function extractZipArchiveSafely($zipPath, $extractDir, &$error)
{
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

function removePathRecursive($path)
{
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

function detectImportSourceDir($extractRoot)
{
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

function runImportWizard($wizardPath, $sourceDir, $dryRun, &$error, &$outputText)
{
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

    $modeArg = $dryRun ? '--dry-run ' : '';
    $commands = [
        escapeshellarg($wizardPath) . ' ' . $modeArg . escapeshellarg($sourceDir) . ' 2>&1',
        'sudo -n ' . escapeshellarg($wizardPath) . ' ' . $modeArg . escapeshellarg($sourceDir) . ' 2>&1',
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

function isPhpFunctionEnabled($name)
{
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

function httpGetViaWget($url, &$error)
{
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

function httpGet($url, &$error)
{
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

function githubApiUrl($repository, $path)
{
    $parts = explode('/', (string)$repository, 2);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
        return null;
    }
    return 'https://api.github.com/repos/' . rawurlencode($parts[0]) . '/' . rawurlencode($parts[1]) . $path;
}

function githubRawFileUrl($repository, $ref, $filePath)
{
    $parts = explode('/', (string)$repository, 2);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
        return null;
    }

    $pathSegments = array_map('rawurlencode', explode('/', $filePath));
    return 'https://raw.githubusercontent.com/' . rawurlencode($parts[0]) . '/' . rawurlencode($parts[1]) . '/' . rawurlencode($ref) . '/' . implode('/', $pathSegments);
}

function normalizeCustomBaseUrl($url)
{
    $normalized = trim((string)$url);
    if ($normalized === '') {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $normalized) !== 1) {
        return '';
    }
    return rtrim($normalized, '/');
}

function buildCustomFileUrl($baseUrl, $filePath)
{
    $base = normalizeCustomBaseUrl($baseUrl);
    if ($base === '' || !isSafeManagedPath($filePath)) {
        return null;
    }

    $pathSegments = array_map('rawurlencode', explode('/', trim(str_replace('\\', '/', (string)$filePath))));
    return $base . '/' . implode('/', $pathSegments);
}

function chooseGithubReleaseByChannel($releases, $channel)
{
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

function githubReleaseApiPathForChannel($channel)
{
    if ($channel === 'stable') {
        return '/releases/latest';
    }
    return '/releases?per_page=30';
}

function resolveRemoteBranchCandidate($repository, $branch, &$error)
{
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

function resolveAvailableRemoteBranches($repository, &$error)
{
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

function hasUpdateForCandidate($candidate, $currentVersion, $policy)
{
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

function chooseGithubTagByChannel($tags, $channel)
{
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

function resolveRemoteTagCandidate($repository, $channel, &$error)
{
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

function resolveCustomBaseCandidate($policy, &$error)
{
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

function resolveRemoteReleaseCandidate($policy, &$error)
{
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

function fetchManagedFilesFromRelease($policy, $candidate, &$error)
{
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

function normalizeDigestValue($value)
{
    $normalized = strtolower(trim((string)$value));
    if (strpos($normalized, 'sha256:') === 0) {
        $normalized = substr($normalized, 7);
    }
    return $normalized;
}

function fetchIntegrityManifestFromRelease($policy, $candidate, &$error)
{
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

function verifyPayloadsAgainstManifest($payloads, $managedFiles, $manifest, &$error, &$details)
{
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

function applyManagedFiles($baseDir, $payloads, &$error)
{
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

function updateReleasePolicyFromCandidate($policyPath, $policy, $candidate)
{
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

function markMetaMaintenance($meta, $actionName, $result)
{
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

function readReleasePolicy($path)
{
    $policy = readJsonFile($path, defaultReleasePolicy());
    return normalizeReleasePolicy($policy);
}

function buildMeta($metaPath, $versionPath, $releasePath)
{
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

function readRegistry($path)
{
    $data = readJsonFile($path, ['extensions' => []]);
    if (!isset($data['extensions']) || !is_array($data['extensions'])) {
        $data['extensions'] = [];
    }
    return $data;
}

function normalizeUiPathOrUrl($value)
{
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

function normalizeIconClass($value, $fallback = 'fa-solid fa-sharp fa-puzzle-piece')
{
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

function normalizeScalarStringList($values)
{
    if (!is_array($values)) {
        return [];
    }

    $normalized = [];
    foreach ($values as $value) {
        if (!is_scalar($value)) {
            continue;
        }
        $text = trim((string)$value);
        if ($text === '') {
            continue;
        }
        $normalized[] = $text;
    }

    return array_values(array_unique($normalized));
}

function buildImportPackageReview($sourceDir)
{
    $manifestPath = rtrim((string)$sourceDir, '/\\') . DIRECTORY_SEPARATOR . 'manifest.json';
    $manifest = readJsonFile($manifestPath, []);
    $extMgr = is_array($manifest['ext_mgr'] ?? null) ? $manifest['ext_mgr'] : [];
    $install = is_array($extMgr['install'] ?? null) ? $extMgr['install'] : [];
    $service = is_array($extMgr['service'] ?? null) ? $extMgr['service'] : [];

    $declaredPackages = normalizeScalarStringList($install['packages'] ?? []);
    $serviceDependencies = normalizeScalarStringList($service['dependencies'] ?? []);
    $serviceUnits = [];
    $bundledPackageFiles = [];
    $packageFolders = [];

    foreach (['scripts/*.service', 'packages/services/*.service'] as $pattern) {
        $matches = glob(rtrim($sourceDir, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pattern)) ?: [];
        foreach ($matches as $match) {
            if (!is_file($match)) {
                continue;
            }
            $serviceUnits[] = str_replace('\\', '/', substr($match, strlen(rtrim($sourceDir, '/\\')) + 1));
        }
    }

    $packagesDir = rtrim($sourceDir, '/\\') . DIRECTORY_SEPARATOR . 'packages';
    if (is_dir($packagesDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($packagesDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen(rtrim($sourceDir, '/\\')) + 1));
            $bundledPackageFiles[] = $relative;
        }

        foreach (glob($packagesDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $dir) {
            $packageFolders[] = 'packages/' . basename($dir);
        }
    }

    return [
        'manifestPackages' => $declaredPackages,
        'bundledPackageFiles' => array_values(array_unique($bundledPackageFiles)),
        'packageFolders' => array_values(array_unique($packageFolders)),
        'serviceUnits' => array_values(array_unique($serviceUnits)),
        'serviceDependencies' => $serviceDependencies,
        'installScripts' => [
            'install' => is_file(rtrim($sourceDir, '/\\') . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'install.sh'),
            'repair' => is_file(rtrim($sourceDir, '/\\') . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'repair.sh'),
            'uninstall' => is_file(rtrim($sourceDir, '/\\') . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'uninstall.sh'),
        ],
        'counts' => [
            'manifestPackages' => count($declaredPackages),
            'bundledPackageFiles' => count(array_unique($bundledPackageFiles)),
            'serviceUnits' => count(array_unique($serviceUnits)),
            'serviceDependencies' => count($serviceDependencies),
        ],
    ];
}

function readExtensionInstallMetadata($extId)
{
    $extId = trim((string)$extId);
    if ($extId === '') {
        return null;
    }

    $metadataPath = '/var/www/extensions/installed/' . $extId . '/.ext-mgr/install-metadata.json';
    $raw = readJsonFile($metadataPath, null);
    if (!is_array($raw)) {
        return null;
    }

    $packages = is_array($raw['packages'] ?? null) ? $raw['packages'] : [];
    $services = is_array($raw['services'] ?? null) ? $raw['services'] : [];
    $links = is_array($raw['links'] ?? null) ? $raw['links'] : [];
    $scripts = is_array($raw['scripts'] ?? null) ? $raw['scripts'] : [];

    $packages['declared'] = normalizeScalarStringList($packages['declared'] ?? []);
    $packages['installedApt'] = normalizeScalarStringList($packages['installedApt'] ?? []);
    $packages['bundledFiles'] = normalizeScalarStringList($packages['bundledFiles'] ?? []);
    $packages['installedBundles'] = normalizeScalarStringList($packages['installedBundles'] ?? []);
    $services['discovered'] = normalizeScalarStringList($services['discovered'] ?? []);
    $services['installed'] = normalizeScalarStringList($services['installed'] ?? []);
    $services['dependenciesInjected'] = normalizeScalarStringList($services['dependenciesInjected'] ?? []);
    $links['packageRuntimeLinks'] = normalizeScalarStringList($links['packageRuntimeLinks'] ?? []);

    $raw['packages'] = $packages;
    $raw['services'] = $services;
    $raw['links'] = $links;
    $raw['scripts'] = [
        'install' => !empty($scripts['install']),
        'repair' => !empty($scripts['repair']),
        'uninstall' => !empty($scripts['uninstall']),
    ];
    $raw['counts'] = [
        'manifestPackages' => count($packages['declared']),
        'installedApt' => count($packages['installedApt']),
        'bundledFiles' => count($packages['bundledFiles']),
        'installedBundles' => count($packages['installedBundles']),
        'servicesDiscovered' => count($services['discovered']),
        'servicesInstalled' => count($services['installed']),
        'dependenciesInjected' => count($services['dependenciesInjected']),
    ];
    $raw['metadataPath'] = $metadataPath;

    return $raw;
}

function loadExtensionInfo($extId, $entryPath, $fallbackName, $fallbackVersion)
{
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

function normalizeRegistry($registry)
{
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
            $ext['menuVisibility']['system'] = false;
        }

        $ext['pinned'] = (bool)$ext['pinned'];
        $ext['enabled'] = (bool)$ext['enabled'];
        $ext['settingsCardOnly'] = (bool)$ext['settingsCardOnly'];
        $ext['state'] = $ext['enabled'] ? 'active' : 'inactive';
        $ext['menuVisibility']['m'] = (bool)$ext['menuVisibility']['m'];
        $ext['menuVisibility']['library'] = (bool)$ext['menuVisibility']['library'];
        $ext['menuVisibility']['system'] = false;
        $ext['extensionInfo'] = loadExtensionInfo(
            (string)$ext['id'],
            (string)$ext['entry'],
            (string)$ext['name'],
            (string)$ext['version']
        );
        $ext['installMetadata'] = readExtensionInstallMetadata((string)$ext['id']);

        // Keep flat compatibility fields for downstream scripts.
        $ext['showInMMenu'] = $ext['menuVisibility']['m'];
        $ext['showInLibrary'] = $ext['menuVisibility']['library'];
    }
    unset($ext);

    return $registry;
}

function sanitizeRegistryForPersist($registry)
{
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
        unset($ext['installMetadata']);
    }
    unset($ext);

    return $registry;
}

function applyImportedExtensionDefaults($registryPath, $extId)
{
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
            $ext['menuVisibility'] = ['m' => true, 'library' => true, 'system' => false];
        }
        $ext['menuVisibility']['m'] = true;
        $ext['menuVisibility']['library'] = true;
        $ext['menuVisibility']['system'] = false;
        $ext['showInMMenu'] = true;
        $ext['showInLibrary'] = true;
        $ext['settingsCardOnly'] = true;

        $updated = true;
        break;
    }
    unset($ext);

    if (!$updated) {
        return false;
    }

    return writeJsonFile($registryPath, sanitizeRegistryForPersist($registry));
}

function responseData($registryPath, $metaPath, $versionPath, $releasePath)
{
    global $extensionsCachePath, $extensionsBackupPath, $extensionsLogsPath, $extMgrLogsPath;

    $registry = normalizeRegistry(readRegistry($registryPath));
    ensureExtMgrLogLayout();
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
    $serviceHealth = readExtMgrServiceHealth();
    $watchdogHealth = readExtMgrWatchdogHealth();

    return [
        'extensions' => $registry['extensions'],
        'meta' => $meta,
        'releasePolicy' => $policy,
        'guidance' => $guidance,
        'health' => [
            'apiService' => 'online | extmgr=' . $serviceHealth['status'] . ' | watchdog=' . $watchdogHealth['status'],
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
            'extMgrService' => $serviceHealth,
            'extMgrWatchdog' => $watchdogHealth,
            'extensionLogsRoot' => $extensionsLogsPath,
            'extMgrLogsRoot' => $extMgrLogsPath,
        ],
        'maintenance' => buildMaintenanceStatus($extensionsCachePath, $extensionsBackupPath),
    ];
}

function syncRegistryWithFilesystem($registryPath, $pruneMissing = false)
{
    global $extensionsInstalledPath;

    $registry = normalizeRegistry(readRegistry($registryPath));
    $next = [];
    $summary = [
        'total' => 0,
        'installed' => 0,
        'missing' => 0,
        'pruned' => 0,
        'discovered' => 0,
    ];

    $knownIds = [];

    foreach ($registry['extensions'] as $ext) {
        if (!is_array($ext)) {
            continue;
        }

        $summary['total']++;
        $id = (string)($ext['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $knownIds[$id] = true;

        $installedDir = '/var/www/extensions/installed/' . $id;
        $canonicalLink = '/var/www/' . $id . '.php';
        $dirPresent = is_dir($installedDir);
        $routePresent = (is_link($canonicalLink) || file_exists($canonicalLink));

        // Treat extension as installed based on installed directory presence.
        // Missing canonical route should not force-reset enabled state.
        $ext['installed'] = $dirPresent;
        $ext['routeInstalled'] = $routePresent;

        if (!$dirPresent) {
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

    // Merge installed extension folders that are not yet tracked in registry.
    $entries = is_dir($extensionsInstalledPath) ? @scandir($extensionsInstalledPath) : null;
    if (is_array($entries)) {
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (!isValidExtensionId($entry) || $entry === 'ext-mgr') {
                continue;
            }

            $fullPath = $extensionsInstalledPath . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($fullPath)) {
                continue;
            }
            if (isset($knownIds[$entry])) {
                continue;
            }

            $next[] = [
                'id' => $entry,
                'name' => ucwords(str_replace(['-', '_', '.'], ' ', $entry)),
                'entry' => '/' . $entry . '.php',
                'path' => '/' . $entry . '.php',
                'pinned' => false,
                'version' => 'unknown',
                'versionSource' => 'filesystem-discovery',
                'enabled' => true,
                'state' => 'active',
                'settingsCardOnly' => false,
                'menuVisibility' => [
                    'm' => true,
                    'library' => true,
                    'system' => false,
                ],
                'showInMMenu' => true,
                'showInLibrary' => true,
                'installed' => true,
                'routeInstalled' => (is_link('/var/www/' . $entry . '.php') || file_exists('/var/www/' . $entry . '.php')),
            ];
            $knownIds[$entry] = true;
            $summary['discovered']++;
            $summary['installed']++;
        }
    }

    $registry['extensions'] = $next;
    $registry['generated_at'] = date('c');
    writeJsonFile($registryPath, sanitizeRegistryForPersist($registry));

    return $summary;
}

function isValidExtensionId($id)
{
    return is_string($id) && preg_match('/^[a-zA-Z0-9._-]+$/', $id) === 1;
}

function isSafeRelativeSubPath($path)
{
    if (!is_string($path)) {
        return false;
    }
    $clean = trim(str_replace('\\', '/', $path));
    if ($clean === '' || substr($clean, 0, 1) === '/' || strpos($clean, '..') !== false) {
        return false;
    }
    return true;
}

function resolveExtensionEntryFile($extId, $entryPath, &$error)
{
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

function repairExtensionSymlink($extId, $entryPath, &$error)
{
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

function runPrivilegedSymlinkRepair($extId, $entryPath, &$error)
{
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

function runShellCommands($commands, &$outputText)
{
    $outputText = '';
    if (!isPhpFunctionEnabled('exec')) {
        return false;
    }

    foreach ($commands as $command) {
        $output = [];
        $exitCode = 0;
        @exec($command, $output, $exitCode);
        $outputText = trim(implode("\n", $output));
        if ($exitCode === 0) {
            return true;
        }
    }

    return false;
}

function removePathWithFallback($path, &$outputNote)
{
    $outputNote = '';
    $target = trim((string)$path);
    if ($target === '' || !file_exists($target)) {
        return true;
    }

    removePathRecursive($target);
    clearstatcache(true, $target);
    if (!file_exists($target)) {
        return true;
    }

    if (!isPhpFunctionEnabled('exec')) {
        $outputNote = 'exec() disabled';
        return false;
    }

    $isDir = is_dir($target) && !is_link($target);
    $baseCmd = $isDir ? 'rm -rf -- ' : 'rm -f -- ';
    $cmd = $baseCmd . escapeshellarg($target) . ' 2>&1';
    $cmdOutput = '';
    $ok = runShellCommands([
        $cmd,
        'sudo -n ' . $cmd,
    ], $cmdOutput);

    clearstatcache(true, $target);
    if (!$ok || file_exists($target)) {
        $outputNote = $cmdOutput;
        return false;
    }

    return true;
}

function getInstalledMetadataByExtensionId($excludeExtId = '')
{
    $result = [];
    $pattern = '/var/www/extensions/installed/*/.ext-mgr/install-metadata.json';
    $matches = glob($pattern) ?: [];
    foreach ($matches as $metadataPath) {
        $extDir = dirname(dirname($metadataPath));
        $extId = basename($extDir);
        if ($extId === '' || $extId === $excludeExtId) {
            continue;
        }
        if (!isValidExtensionId($extId)) {
            continue;
        }
        $metadata = readExtensionInstallMetadata($extId);
        if (is_array($metadata)) {
            $result[$extId] = $metadata;
        }
    }
    return $result;
}

function collectSharedPackageRefs($excludeExtId)
{
    $shared = [];
    $allMetadata = getInstalledMetadataByExtensionId($excludeExtId);
    foreach ($allMetadata as $metadata) {
        $packages = is_array($metadata['packages'] ?? null) ? $metadata['packages'] : [];
        foreach (['installedApt', 'declared'] as $key) {
            $list = normalizeScalarStringList($packages[$key] ?? []);
            foreach ($list as $pkg) {
                $shared[$pkg] = true;
            }
        }
    }
    return array_keys($shared);
}

function collectBundledDebPackageNames($installedDir, $metadata)
{
    $packages = [];
    if (!is_array($metadata)) {
        return $packages;
    }

    $packageData = is_array($metadata['packages'] ?? null) ? $metadata['packages'] : [];
    $bundles = normalizeScalarStringList($packageData['installedBundles'] ?? []);
    if (count($bundles) === 0 || !isPhpFunctionEnabled('exec')) {
        return $packages;
    }

    foreach ($bundles as $relativePath) {
        if (!isSafeRelativeSubPath($relativePath)) {
            continue;
        }
        if (substr($relativePath, -4) !== '.deb') {
            continue;
        }

        $fullPath = rtrim($installedDir, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($fullPath)) {
            continue;
        }

        $output = [];
        $exitCode = 1;
        $cmd = 'dpkg-deb -f ' . escapeshellarg($fullPath) . ' Package 2>/dev/null';
        @exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            continue;
        }

        $pkgName = trim((string)($output[0] ?? ''));
        if ($pkgName !== '') {
            $packages[$pkgName] = true;
        }
    }

    return array_keys($packages);
}

function removeExtensionRuntimeLinks($extId, $metadata, &$warnings)
{
    $removed = [];
    $linkCandidates = [
        '/var/www/extensions/sys/.ext-mgr/packages/' . $extId,
        '/var/www/extensions/installed/' . $extId . '/.ext-mgr/packages-runtime',
    ];

    $links = is_array($metadata['links'] ?? null) ? $metadata['links'] : [];
    foreach (normalizeScalarStringList($links['packageRuntimeLinks'] ?? []) as $path) {
        if (strpos($path, '/var/www/') !== 0) {
            continue;
        }
        $linkCandidates[] = $path;
    }

    $seen = [];
    foreach ($linkCandidates as $candidate) {
        $path = trim((string)$candidate);
        if ($path === '' || isset($seen[$path])) {
            continue;
        }
        $seen[$path] = true;

        if (!is_link($path)) {
            continue;
        }
        if (@unlink($path)) {
            $removed[] = $path;
        } else {
            $warnings[] = 'Failed to remove runtime symlink: ' . $path;
        }
    }

    return $removed;
}

function clearExtensionAcl($installedDir, $metadata, &$warnings)
{
    $cleared = [];

    if (!isPhpFunctionEnabled('exec')) {
        return $cleared;
    }

    $paths = [];
    if (is_dir($installedDir)) {
        $paths[] = $installedDir;
    }

    $links = is_array($metadata['links'] ?? null) ? $metadata['links'] : [];
    foreach (normalizeScalarStringList($links['packageRuntimeLinks'] ?? []) as $path) {
        if (strpos($path, '/var/www/') !== 0) {
            continue;
        }
        if (is_dir($path) || is_file($path) || is_link($path)) {
            $paths[] = $path;
        }
    }

    $unique = array_values(array_unique($paths));
    if (count($unique) === 0) {
        return $cleared;
    }

    foreach ($unique as $path) {
        $output = '';
        $ok = runShellCommands([
            'setfacl -bR ' . escapeshellarg($path) . ' 2>&1',
            'sudo -n setfacl -bR ' . escapeshellarg($path) . ' 2>&1',
        ], $output);

        if ($ok) {
            $cleared[] = $path;
            continue;
        }

        if (is_link($path)) {
            $ok = runShellCommands([
                'setfacl -h -b ' . escapeshellarg($path) . ' 2>&1',
                'sudo -n setfacl -h -b ' . escapeshellarg($path) . ' 2>&1',
            ], $output);
            if ($ok) {
                $cleared[] = $path;
                continue;
            }
        }

        if ($output !== '') {
            $warnings[] = 'ACL cleanup failed for ' . $path . ': ' . $output;
        } else {
            $warnings[] = 'ACL cleanup failed for ' . $path . '.';
        }
    }

    return $cleared;
}

function removeExtensionServiceUnits($extId, $metadata, &$warnings)
{
    $removed = [];
    $units = [$extId . '.service'];

    $services = is_array($metadata['services'] ?? null) ? $metadata['services'] : [];
    foreach (['installed', 'discovered'] as $key) {
        foreach (normalizeScalarStringList($services[$key] ?? []) as $serviceEntry) {
            $unit = basename((string)$serviceEntry);
            if (substr($unit, -8) === '.service') {
                $units[] = $unit;
            }
        }
    }

    $units = array_values(array_unique($units));
    foreach ($units as $unit) {
        if (!preg_match('/^[a-zA-Z0-9._@-]+\.service$/', $unit)) {
            continue;
        }

        $output = '';
        runShellCommands([
            'systemctl disable --now ' . escapeshellarg($unit) . ' 2>&1',
            'sudo -n systemctl disable --now ' . escapeshellarg($unit) . ' 2>&1',
        ], $output);

        $unitPath = '/etc/systemd/system/' . $unit;
        if (file_exists($unitPath) || is_link($unitPath)) {
            if (@unlink($unitPath)) {
                $removed[] = $unit;
            } else {
                $outputRm = '';
                $ok = runShellCommands([
                    'rm -f ' . escapeshellarg($unitPath) . ' 2>&1',
                    'sudo -n rm -f ' . escapeshellarg($unitPath) . ' 2>&1',
                ], $outputRm);
                if ($ok) {
                    $removed[] = $unit;
                } else {
                    $warnings[] = 'Failed removing service unit ' . $unitPath . ($outputRm !== '' ? ': ' . $outputRm : '.');
                }
            }
        }
    }

    if (count($units) > 0) {
        $reloadOutput = '';
        runShellCommands([
            'systemctl daemon-reload 2>&1',
            'sudo -n systemctl daemon-reload 2>&1',
        ], $reloadOutput);
    }

    return array_values(array_unique($removed));
}

function runExtensionUninstallScript($extId, $installedDir, $metadata, &$warnings)
{
    $scripts = is_array($metadata['scripts'] ?? null) ? $metadata['scripts'] : [];
    if (empty($scripts['uninstall'])) {
        return false;
    }

    $scriptPath = rtrim($installedDir, '/\\') . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'uninstall.sh';
    if (!is_file($scriptPath)) {
        $warnings[] = 'Metadata declares uninstall script but file is missing: ' . $scriptPath;
        return false;
    }

    if (!isPhpFunctionEnabled('exec')) {
        $warnings[] = 'exec() disabled; cannot run extension uninstall script for ' . $extId . '.';
        return false;
    }

    @chmod($scriptPath, 0755);
    $escaped = escapeshellarg($scriptPath);
    $output = '';
    $ok = runShellCommands([
        'bash ' . $escaped . ' 2>&1',
        'sudo -n bash ' . $escaped . ' 2>&1',
    ], $output);

    if (!$ok) {
        $warnings[] = 'Extension uninstall script failed for ' . $extId . ($output !== '' ? ': ' . $output : '.');
        return false;
    }

    return true;
}

function uninstallExtensionPackagesGracefully($extId, $installedDir, $metadata, &$warnings)
{
    $packagesMeta = is_array($metadata['packages'] ?? null) ? $metadata['packages'] : [];
    $candidatePackages = [];

    foreach (normalizeScalarStringList($packagesMeta['installedApt'] ?? []) as $pkg) {
        $candidatePackages[$pkg] = true;
    }
    foreach (collectBundledDebPackageNames($installedDir, $metadata) as $pkg) {
        $candidatePackages[$pkg] = true;
    }

    $candidateList = array_keys($candidatePackages);
    $shared = collectSharedPackageRefs($extId);
    $sharedMap = [];
    foreach ($shared as $pkg) {
        $sharedMap[$pkg] = true;
    }

    $removable = [];
    $skippedShared = [];
    foreach ($candidateList as $pkg) {
        if (isset($sharedMap[$pkg])) {
            $skippedShared[] = $pkg;
        } else {
            $removable[] = $pkg;
        }
    }

    if (count($removable) === 0) {
        return [
            'removed' => [],
            'skippedShared' => array_values(array_unique($skippedShared)),
        ];
    }

    if (!isPhpFunctionEnabled('exec')) {
        $warnings[] = 'exec() disabled; cannot uninstall apt packages: ' . implode(', ', $removable);
        return [
            'removed' => [],
            'skippedShared' => array_values(array_unique($skippedShared)),
        ];
    }

    $args = implode(' ', array_map('escapeshellarg', $removable));
    $cmd = 'DEBIAN_FRONTEND=noninteractive apt-get remove -y --autoremove ' . $args . ' 2>&1';
    $output = '';
    $ok = runShellCommands([
        $cmd,
        'sudo -n ' . $cmd,
    ], $output);

    if (!$ok) {
        $warnings[] = 'Package uninstall failed for ' . $extId . ($output !== '' ? ': ' . $output : '.');
        return [
            'removed' => [],
            'skippedShared' => array_values(array_unique($skippedShared)),
        ];
    }

    return [
        'removed' => array_values(array_unique($removable)),
        'skippedShared' => array_values(array_unique($skippedShared)),
    ];
}

function removeExtensionById($extId, $registryPath, $backupRoot, &$error)
{
    $error = '';

    if (!isValidExtensionId($extId)) {
        $error = 'Invalid extension id.';
        return null;
    }

    if ($extId === 'ext-mgr') {
        $error = 'Removing ext-mgr itself is not supported from this action.';
        return null;
    }

    appendExtMgrLog('install', 'remove_extension start id=' . $extId);
    appendExtensionLog($extId, 'install', 'remove_extension start');

    $warnings = [];

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

    $removedFromRegistry = false;
    if ($found) {
        $registry['extensions'] = $nextExtensions;
        $registry['generated_at'] = date('c');
        if (!writeJsonFile($registryPath, sanitizeRegistryForPersist($registry))) {
            $error = formatWriteFailure($registryPath, 'registry');
            return null;
        }

        // Verify persistence: entry must be absent after uninstall registry write.
        $verifyRegistry = normalizeRegistry(readRegistry($registryPath));
        $stillPresent = false;
        foreach ((array)$verifyRegistry['extensions'] as $verifyExt) {
            if ((string)($verifyExt['id'] ?? '') === $extId) {
                $stillPresent = true;
                break;
            }
        }
        if ($stillPresent) {
            $error = 'Failed to remove extension from registry persistently: ' . $extId;
            return null;
        }
        $removedFromRegistry = true;
    } else {
        $warnings[] = 'Extension not found in registry; continuing with filesystem cleanup for ' . $extId . '.';
    }

    $installedDir = '/var/www/extensions/installed/' . $extId;
    $linkPath = '/var/www/' . $extId . '.php';
    $legacyLinkPath = '/var/www/extensions/' . $extId . '.php';
    $globalLogsDir = '/var/www/extensions/sys/logs/extensionslogs/' . $extId;
    $legacyInstalledLogsDir = '/var/www/extensions/logs/' . $extId;
    $runtimeCacheDir = '/var/www/extensions/cache/' . $extId;

    $installMetadata = readExtensionInstallMetadata($extId);
    $uninstallSummary = [
        'metadataFound' => is_array($installMetadata),
        'ranExtensionUninstallScript' => false,
        'removedRuntimeLinks' => [],
        'clearedAclPaths' => [],
        'removedServiceUnits' => [],
        'removedPackages' => [],
        'skippedSharedPackages' => [],
        'removedPaths' => [],
        'failedPaths' => [],
    ];

    if (is_array($installMetadata)) {
        $uninstallSummary['ranExtensionUninstallScript'] = runExtensionUninstallScript($extId, $installedDir, $installMetadata, $warnings);
        $uninstallSummary['removedRuntimeLinks'] = removeExtensionRuntimeLinks($extId, $installMetadata, $warnings);
        $uninstallSummary['clearedAclPaths'] = clearExtensionAcl($installedDir, $installMetadata, $warnings);
        $uninstallSummary['removedServiceUnits'] = removeExtensionServiceUnits($extId, $installMetadata, $warnings);
        $pkgResult = uninstallExtensionPackagesGracefully($extId, $installedDir, $installMetadata, $warnings);
        $uninstallSummary['removedPackages'] = normalizeScalarStringList($pkgResult['removed'] ?? []);
        $uninstallSummary['skippedSharedPackages'] = normalizeScalarStringList($pkgResult['skippedShared'] ?? []);
    }

    $pathsToRemove = [
        $installedDir,
        $linkPath,
        $legacyLinkPath,
        $globalLogsDir,
        $legacyInstalledLogsDir,
        $runtimeCacheDir,
    ];

    foreach ($pathsToRemove as $path) {
        $path = trim((string)$path);
        if ($path === '' || !file_exists($path)) {
            continue;
        }

        $removeNote = '';
        $removed = removePathWithFallback($path, $removeNote);

        if ($removed) {
            $uninstallSummary['removedPaths'][] = $path;
        } else {
            $uninstallSummary['failedPaths'][] = $path;
            $warnings[] = 'Failed to remove path: ' . $path . ($removeNote !== '' ? ' (' . $removeNote . ')' : '');
        }
    }

    $removedInstallDir = !file_exists($installedDir);
    $removedRoute = !file_exists($linkPath) && !file_exists($legacyLinkPath);

    if (count($warnings) > 0) {
        $warningText = trim(implode(' | ', array_values(array_unique($warnings))));
        if ($warningText !== '') {
            if ($error !== '') {
                $error .= ' | ' . $warningText;
            } else {
                $error = $warningText;
            }
        }
    }

    $stepSummary = [
        'metadata=' . ($uninstallSummary['metadataFound'] ? 'yes' : 'no'),
        'script=' . ($uninstallSummary['ranExtensionUninstallScript'] ? 'yes' : 'no'),
        'runtimeLinks=' . count((array)$uninstallSummary['removedRuntimeLinks']),
        'aclPaths=' . count((array)$uninstallSummary['clearedAclPaths']),
        'serviceUnits=' . count((array)$uninstallSummary['removedServiceUnits']),
        'removedPackages=' . count((array)$uninstallSummary['removedPackages']),
        'skippedSharedPackages=' . count((array)$uninstallSummary['skippedSharedPackages']),
        'removedPaths=' . count((array)$uninstallSummary['removedPaths']),
        'failedPaths=' . count((array)$uninstallSummary['failedPaths']),
        'removedInstallDir=' . ($removedInstallDir ? 'yes' : 'no'),
        'removedRoute=' . ($removedRoute ? 'yes' : 'no'),
    ];
    appendExtMgrLog('install', 'remove_extension summary id=' . $extId . ' ' . implode(' | ', $stepSummary));
    appendExtensionLog($extId, 'install', 'remove_extension summary: ' . implode(' | ', $stepSummary));

    if (count((array)$uninstallSummary['removedPaths']) > 0) {
        $removedPathsText = implode(' | ', array_values(array_unique((array)$uninstallSummary['removedPaths'])));
        appendExtMgrLog('install', 'remove_extension removed-paths id=' . $extId . ' paths=' . $removedPathsText);
        appendExtensionLog($extId, 'install', 'remove_extension removed-paths: ' . $removedPathsText);
    }
    if (count((array)$uninstallSummary['failedPaths']) > 0) {
        $failedPathsText = implode(' | ', array_values(array_unique((array)$uninstallSummary['failedPaths'])));
        appendExtMgrLog('error', 'remove_extension failed-paths id=' . $extId . ' paths=' . $failedPathsText);
        appendExtensionLog($extId, 'error', 'remove_extension failed-paths: ' . $failedPathsText);
    }

    if ($error !== '') {
        appendExtMgrLog('error', 'remove_extension warnings id=' . $extId . ' warning=' . $error);
        appendExtensionLog($extId, 'error', 'remove_extension warnings: ' . $error);
    }

    return [
        'id' => $extId,
        'removedFromRegistry' => $removedFromRegistry,
        'removedInstallDir' => $removedInstallDir,
        'removedRoute' => $removedRoute,
        'backupPath' => null,
        'uninstall' => $uninstallSummary,
        'warning' => $error !== '' ? $error : null,
    ];
}

function clearExtensionsFolderGracefully($registryPath, $backupRoot, $extensionsInstalledPath, &$error)
{
    $error = '';

    $removedIds = [];
    $failedIds = [];
    $removedPaths = [];
    $failedPaths = [];

    $registry = normalizeRegistry(readRegistry($registryPath));
    $targetIds = [];

    foreach ((array)$registry['extensions'] as $ext) {
        $id = trim((string)($ext['id'] ?? ''));
        if ($id === '' || !isValidExtensionId($id) || $id === 'ext-mgr') {
            continue;
        }
        $targetIds[$id] = true;
    }

    if (is_dir($extensionsInstalledPath)) {
        $entries = @scandir($extensionsInstalledPath);
        if (is_array($entries)) {
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $fullPath = $extensionsInstalledPath . DIRECTORY_SEPARATOR . $entry;
                if (!is_dir($fullPath)) {
                    continue;
                }
                if (!isValidExtensionId($entry) || $entry === 'ext-mgr') {
                    continue;
                }
                $targetIds[$entry] = true;
            }
        }
    }

    $ids = array_values(array_keys($targetIds));
    sort($ids, SORT_STRING);

    appendExtMgrLog('install', 'clear_extensions_folder start total=' . count($ids));

    foreach ($ids as $id) {
        $removeError = '';
        $result = removeExtensionById($id, $registryPath, $backupRoot, $removeError);
        if (is_array($result)) {
            $removedIds[] = $id;
            $uninstall = is_array($result['uninstall'] ?? null) ? $result['uninstall'] : [];
            foreach ((array)($uninstall['removedPaths'] ?? []) as $path) {
                $removedPaths[] = (string)$path;
            }
            foreach ((array)($uninstall['failedPaths'] ?? []) as $path) {
                $failedPaths[] = (string)$path;
            }
            continue;
        }

        $failedIds[] = $id;
        if ($removeError !== '') {
            $error .= ($error !== '' ? ' | ' : '') . $id . ': ' . $removeError;
        }
    }

    syncRegistryWithFilesystem($registryPath, true);

    $removedIds = array_values(array_unique($removedIds));
    $failedIds = array_values(array_unique($failedIds));
    $removedPaths = array_values(array_unique(array_filter($removedPaths, static function ($p) {
        return trim((string)$p) !== '';
    })));
    $failedPaths = array_values(array_unique(array_filter($failedPaths, static function ($p) {
        return trim((string)$p) !== '';
    })));

    appendExtMgrLog(
        'install',
        'clear_extensions_folder completed total=' . count($ids)
            . ' removed=' . count($removedIds)
            . ' failed=' . count($failedIds)
            . ' removedPaths=' . count($removedPaths)
            . ' failedPaths=' . count($failedPaths)
    );

    if (count($failedIds) > 0 || count($failedPaths) > 0) {
        appendExtMgrLog(
            'error',
            'clear_extensions_folder warnings failedIds=' . implode(',', $failedIds)
                . ' failedPaths=' . implode(' | ', $failedPaths)
                . ($error !== '' ? ' detail=' . $error : '')
        );
    }

    return [
        'totalTargets' => count($ids),
        'removedIds' => $removedIds,
        'failedIds' => $failedIds,
        'removedPaths' => $removedPaths,
        'failedPaths' => $failedPaths,
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

    $dryRunRaw = (string)($_REQUEST['dry_run'] ?? '0');
    $dryRun = ($dryRunRaw === '1' || strtolower($dryRunRaw) === 'true' || strtolower($dryRunRaw) === 'yes');
    $allowOverwriteRaw = (string)($_REQUEST['allow_overwrite'] ?? '0');
    $allowOverwrite = ($allowOverwriteRaw === '1' || strtolower($allowOverwriteRaw) === 'true' || strtolower($allowOverwriteRaw) === 'yes');

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

    $manifestData = readJsonFile($sourceDir . DIRECTORY_SEPARATOR . 'manifest.json', []);
    $manifestId = strtolower(trim((string)($manifestData['id'] ?? '')));
    $explicitManifestId = (preg_match('/^[a-z0-9._-]+$/', $manifestId) === 1 && !isPlaceholderExtensionId($manifestId));

    // Prevent stale registry entries from blocking replacement imports after manual folder cleanup.
    syncRegistryWithFilesystem($registryPath, true);

    $importedId = $explicitManifestId
        ? $manifestId
        : generateManagedExtensionId($registryPath);

    if ($explicitManifestId && extensionIdExists($importedId, $registryPath) && !$allowOverwrite) {
        removePathRecursive($workDir);
        http_response_code(409);
        echo json_encode([
            'ok' => false,
            'error' => 'Extension id already exists: ' . $importedId . '. Use a placeholder id (auto-generate) or set allow_overwrite=1 for an intentional replacement.',
            'data' => [
                'conflictId' => $importedId,
                'requiresConfirm' => true,
            ],
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($explicitManifestId && extensionIdExists($importedId, $registryPath) && $allowOverwrite) {
        appendExtMgrLog('system', 'import_extension_upload overwrite-approved id=' . $importedId);
    }

    if ($manifestId === '' || $manifestId !== $importedId) {
        $rewriteError = '';
        $updatedManifest = updateImportedManifestWithManagedId($sourceDir, $manifestData, $importedId, $rewriteError);
        if (!is_array($updatedManifest)) {
            removePathRecursive($workDir);
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $rewriteError !== '' ? $rewriteError : 'Failed to prepare managed extension id.']);
            exit;
        }
        $manifestData = $updatedManifest;
    }

    $review = buildImportPackageReview($sourceDir);
    appendExtMgrLog('install', 'import_extension_upload start id=' . $importedId . ' dryRun=' . ($dryRun ? 'true' : 'false'));
    if (isValidExtensionId($importedId)) {
        appendExtensionLog($importedId, 'install', 'import start dryRun=' . ($dryRun ? 'true' : 'false'));
    }

    $wizardPath = $baseDir . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'ext-mgr-import-wizard.sh';
    $execError = '';
    $wizardOutput = '';
    if (!runImportWizard($wizardPath, $sourceDir, $dryRun, $execError, $wizardOutput)) {
        appendExtMgrLog('install', 'import_extension_upload failed id=' . $importedId . ' error=' . $execError);
        appendExtMgrLog('error', 'import_extension_upload failed id=' . $importedId . ' error=' . $execError);
        if ($wizardOutput !== '') {
            appendExtMgrLog('install', 'import_extension_upload wizard-output id=' . $importedId . ' output=' . $wizardOutput);
            appendExtMgrLog('error', 'import_extension_upload wizard-output id=' . $importedId . ' output=' . $wizardOutput);
        }
        if (isValidExtensionId($importedId)) {
            appendExtensionLog($importedId, 'install', 'import failed: ' . $execError);
            appendExtensionLog($importedId, 'error', 'import failed: ' . $execError);
            if ($wizardOutput !== '') {
                appendExtensionLog($importedId, 'install', 'wizard output: ' . $wizardOutput);
                appendExtensionLog($importedId, 'error', 'wizard output: ' . $wizardOutput);
            }
        }
        removePathRecursive($workDir);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $execError]);
        exit;
    }

    appendExtMgrLog('install', 'import_extension_upload success id=' . $importedId . ' dryRun=' . ($dryRun ? 'true' : 'false'));
    if (isValidExtensionId($importedId)) {
        appendExtensionLog($importedId, 'install', 'import success dryRun=' . ($dryRun ? 'true' : 'false'));
    }

    if (!$dryRun) {
        applyImportedExtensionDefaults($registryPath, $importedId);
        syncRegistryWithFilesystem($registryPath, false);
    }
    $state = responseData($registryPath, $metaPath, $versionPath, $releasePath);

    removePathRecursive($workDir);

    echo json_encode([
        'ok' => true,
        'data' => [
            'extensionId' => $importedId,
            'dryRun' => $dryRun,
            'review' => $review,
            'wizardOutput' => $wizardOutput,
            'state' => $state,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'list_extension_logs') {
    $id = trim((string)($_REQUEST['id'] ?? ''));
    if ($id === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing id']);
        exit;
    }

    if ($id !== 'ext-mgr' && !isValidExtensionId($id)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid extension id']);
        exit;
    }

    $logs = availableLogsForTarget($id);

    echo json_encode([
        'ok' => true,
        'data' => [
            'id' => $id,
            'logs' => $logs,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'read_extension_log') {
    $id = trim((string)($_REQUEST['id'] ?? ''));
    $key = trim((string)($_REQUEST['key'] ?? ''));
    $lines = (int)($_REQUEST['lines'] ?? 120);

    if ($id === '' || $key === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing id or key']);
        exit;
    }

    if ($key === 'all') {
        $content = buildCombinedLogContent($id, $lines);
        $row = buildVirtualLogRow('all', 'All Logs (combined)', 'combined');

        echo json_encode([
            'ok' => true,
            'data' => [
                'id' => $id,
                'key' => $key,
                'content' => $content,
                'log' => $row,
            ],
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $resolveError = '';
    $path = resolveLogPathForRead($id, $key, $resolveError);
    if (!is_string($path) || $path === '') {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => $resolveError !== '' ? $resolveError : 'Log file not found']);
        exit;
    }

    if (!file_exists($path)) {
        @file_put_contents($path, '');
    }

    $content = tailFileContent($path, $lines);
    $row = buildLogRow($key, $key, $path, 'runtime');

    echo json_encode([
        'ok' => true,
        'data' => [
            'id' => $id,
            'key' => $key,
            'content' => $content,
            'log' => $row,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'download_extension_log') {
    $id = trim((string)($_REQUEST['id'] ?? ''));
    $key = trim((string)($_REQUEST['key'] ?? ''));
    if ($id === '' || $key === '') {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Missing id or key']);
        exit;
    }

    if ($key === 'all') {
        $content = buildCombinedLogContent($id, 200);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $id . '-all-logs.log');
        if (!is_string($safeName) || trim($safeName) === '') {
            $safeName = 'extension-all-logs.log';
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $safeName . '"');
        header('Cache-Control: no-store');
        echo $content;
        exit;
    }

    $resolveError = '';
    $path = resolveLogPathForRead($id, $key, $resolveError);
    if (!is_string($path) || $path === '') {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $resolveError !== '' ? $resolveError : 'Log file not found']);
        exit;
    }

    if (!file_exists($path)) {
        @file_put_contents($path, '');
    }

    $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $id . '-' . $key . '.log');
    if (!is_string($safeName) || trim($safeName) === '') {
        $safeName = 'extension-log.log';
    }

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: inline; filename="' . $safeName . '"');
    header('Cache-Control: no-store');
    readfile($path);
    exit;
}

if ($action === 'analyze_logs') {
    $id = trim((string)($_REQUEST['id'] ?? ''));
    if ($id !== '' && $id !== 'ext-mgr' && !isValidExtensionId($id)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid extension id']);
        exit;
    }

    $payload = buildLogAnalysisPayload($registryPath, $extensionsLogsPath, $id);
    echo json_encode([
        'ok' => true,
        'data' => $payload,
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
    $summary = syncRegistryWithFilesystem($registryPath, true);
    $state = responseData($registryPath, $metaPath, $versionPath, $releasePath);
    echo json_encode([
        'ok' => true,
        'data' => [
            'state' => $state,
            'summary' => $summary,
            'hook' => [
                'status' => 'merged',
                'description' => 'Sync Extensions now shares the same prune-and-merge flow as Sync Registry.',
                'lastError' => null,
                'candidate' => null,
                'nextSteps' => [
                    'Use either Sync Registry or Sync Extensions; both run the same reconciliation.',
                    'Missing registry entries are pruned when extension folders are gone.',
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
        appendExtMgrLog('error', 'remove_extension failed id=' . $id . ' error=' . ($removeError !== '' ? $removeError : 'unknown error'));
        if (isValidExtensionId($id)) {
            appendExtensionLog($id, 'error', 'remove_extension failed: ' . ($removeError !== '' ? $removeError : 'unknown error'));
        }
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $removeError !== '' ? $removeError : 'Failed to remove extension']);
        exit;
    }

    $u = is_array($result['uninstall'] ?? null) ? $result['uninstall'] : [];
    $actionSummary = [
        'script=' . (!empty($u['ranExtensionUninstallScript']) ? 'yes' : 'no'),
        'runtimeLinks=' . count((array)($u['removedRuntimeLinks'] ?? [])),
        'aclPaths=' . count((array)($u['clearedAclPaths'] ?? [])),
        'serviceUnits=' . count((array)($u['removedServiceUnits'] ?? [])),
        'removedPackages=' . count((array)($u['removedPackages'] ?? [])),
        'skippedSharedPackages=' . count((array)($u['skippedSharedPackages'] ?? [])),
    ];
    appendExtMgrLog('install', 'remove_extension completed id=' . $id . ' ' . implode(' | ', $actionSummary));

    echo json_encode([
        'ok' => true,
        'data' => $result,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'clear_extensions_folder') {
    $clearError = '';
    $result = clearExtensionsFolderGracefully($registryPath, $extensionsBackupPath, $extensionsInstalledPath, $clearError);
    if (!is_array($result)) {
        appendExtMgrLog('error', 'clear_extensions_folder failed error=' . ($clearError !== '' ? $clearError : 'unknown error'));
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $clearError !== '' ? $clearError : 'Failed to clear extensions folder']);
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
    $defaultManagerVisibility = defaultMeta()['managerVisibility'];
    if (!isset($meta['managerVisibility']) || !is_array($meta['managerVisibility'])) {
        $meta['managerVisibility'] = $defaultManagerVisibility;
    }

    $meta['managerVisibility'][$area] = $visible;
    foreach ($allowed as $entry) {
        if (!array_key_exists($entry, $meta['managerVisibility'])) {
            $meta['managerVisibility'][$entry] = (bool)($defaultManagerVisibility[$entry] ?? true);
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
            if (empty($ext['enabled'])) {
                http_response_code(409);
                echo json_encode(['ok' => false, 'error' => 'Extension is inactive. Enable it before changing menu visibility.']);
                exit;
            }
            if (!isset($ext['menuVisibility']) || !is_array($ext['menuVisibility'])) {
                $ext['menuVisibility'] = ['m' => true, 'library' => true, 'system' => false];
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
            $ext['menuVisibility']['system'] = false;
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
