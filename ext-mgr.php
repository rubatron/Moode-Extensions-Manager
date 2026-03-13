<?php
header('Content-Type: text/html; charset=utf-8');

$init = [
    'apiUrl' => '/ext-mgr-api.php',
];

$usingMoodeShell = false;
$section = 'extensions';

if (file_exists('/var/www/inc/common.php')) {
    require_once '/var/www/inc/common.php';
}
if (file_exists('/var/www/inc/session.php')) {
    require_once '/var/www/inc/session.php';
}

if (function_exists('sqlConnect') && function_exists('phpSession')) {
    @sqlConnect();
    @phpSession('open');
}

if (function_exists('storeBackLink')) {
    @storeBackLink($section, 'ext-mgr');
}

if (file_exists('/var/www/header.php')) {
    $usingMoodeShell = true;
    include '/var/www/header.php';
    echo '<link rel="stylesheet" href="/extensions/sys/assets/css/ext-mgr.css">' . "\n";
    echo '<script src="/extensions/sys/assets/js/ext-mgr-modal-fix.js" defer></script>' . "\n";
} else {
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Extensions Manager</title>
    <link rel="stylesheet" href="/extensions/sys/assets/css/ext-mgr.css">
</head>
<body>
    <?php
}
?>

<div id="container">
    <div class="container extmgr-page-shell">
        <div class="extmgr-page-title">
            <i class="fa-solid fa-sharp fa-puzzle-piece"></i>
            <h1 class="config-title">Extensions Manager</h1>
        </div>
        <p class="config-help-static extmgr-page-subtitle">Settings and troubleshooting control panel for extension maintenance.</p>

        <div id="status" class="extmgr-status">Loading...</div>

        <div class="extmgr-local-menu" role="navigation" aria-label="Sections">
            <button class="extmgr-local-menu-item is-active" type="button" data-target="section-installed">Installed Extensions</button>
            <button class="extmgr-local-menu-item" type="button" data-target="section-update">Update</button>
            <button class="extmgr-local-menu-item" type="button" data-target="section-system">System</button>
        </div>

        <fieldset class="extmgr-panel" id="section-installed">
            <legend>Installed Extensions</legend>
            <div class="config-help-static extmgr-help">Inactive extensions remain in registry but are marked unavailable in menu integrations.</div>
            <div class="extmgr-actions extmgr-list-controls">
                <select id="list-filter" aria-label="Filter extensions">
                    <option value="all">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select id="list-sort" aria-label="Sort extensions">
                    <option value="name">Sort: Name</option>
                    <option value="state">Sort: State</option>
                    <option value="visibility">Sort: Visibility</option>
                </select>
                <input id="list-search" type="search" placeholder="Search extension" aria-label="Search extension">
            </div>
            <div id="list-summary" class="extmgr-list-summary">-</div>
            <div id="list" class="extmgr-list" aria-live="polite"></div>
        </fieldset>

        <fieldset class="extmgr-panel" id="section-update">
            <legend>Update</legend>
            <div class="extmgr-text-list">
                <div class="extmgr-text-row"><span class="extmgr-text-label">Version</span><span id="meta-version" class="extmgr-text-value">-</span></div>
                <div class="extmgr-text-row"><span class="extmgr-text-label">Author</span><span id="meta-creator" class="extmgr-text-value">-</span></div>
                <div class="extmgr-text-row"><span class="extmgr-text-label">License</span><span id="meta-license" class="extmgr-text-value">-</span></div>
            </div>

            <div class="extmgr-actions">
                <button id="check-update-btn" class="btn btn-primary btn-small" type="button"><i class="fa-solid fa-sharp fa-cloud-arrow-down"></i> Check Update</button>
                <button id="run-update-btn" class="btn btn-small" type="button"><i class="fa-solid fa-sharp fa-rotate"></i> Run Update</button>
                <button id="system-update-btn" class="btn btn-small" type="button"><i class="fa-solid fa-sharp fa-sliders"></i> System Settings Hook</button>
                <button id="refresh-btn" class="btn btn-small" type="button"><i class="fa-solid fa-sharp fa-arrows-rotate"></i> Refresh List</button>
            </div>
            <div id="update-note" class="extmgr-note">No update info yet.</div>

            <details class="extmgr-collapse extmgr-collapse-inner">
                <summary>Advanced Update</summary>
                <div class="extmgr-advanced-grid">
                    <label for="advanced-track">Track</label>
                    <select id="advanced-track" aria-label="Advanced update track">
                        <option value="channel">Channel</option>
                        <option value="branch">Branch</option>
                    </select>

                    <label for="advanced-channel">Channel</label>
                    <select id="advanced-channel" aria-label="Advanced update channel">
                        <option value="dev">dev</option>
                        <option value="beta">beta</option>
                        <option value="stable">stable</option>
                    </select>

                    <label for="advanced-branch">Branch</label>
                    <select id="advanced-branch" aria-label="Advanced update branch">
                        <option value="main">main</option>
                        <option value="dev">dev</option>
                    </select>
                </div>
                <div class="extmgr-advanced-source">
                    <span class="extmgr-advanced-source-label">Source</span>
                    <a id="advanced-source-link" class="extmgr-advanced-source-link" href="#" target="_blank" rel="noopener noreferrer">-</a>
                    <button id="copy-advanced-source-btn" class="btn btn-small" type="button"><i class="fa-solid fa-sharp fa-link"></i> Copy Link</button>
                </div>
                <div class="extmgr-actions">
                    <button id="save-advanced-update-btn" class="btn btn-small" type="button"><i class="fa-solid fa-sharp fa-floppy-disk"></i> Save Advanced Update</button>
                    <span id="advanced-update-note" class="extmgr-note">Track branch is limited to main/dev. Use Copy Link for quick diagnostics.</span>
                </div>
            </details>
        </fieldset>

        <fieldset class="extmgr-panel" id="section-system">
            <legend>System</legend>
            <details id="system-root-details" class="extmgr-collapse" open>
            <summary>Open System Panels</summary>

            <details class="extmgr-collapse extmgr-submenu-section" open>
                <summary>API Status</summary>
                <div class="extmgr-text-list">
                    <div class="extmgr-text-row"><span class="extmgr-text-label">API Service</span><span id="api-service" class="extmgr-text-value">-</span></div>
                    <div class="extmgr-text-row"><span class="extmgr-text-label">Registry</span><span id="registry-health" class="extmgr-text-value">-</span></div>
                    <div class="extmgr-text-row"><span class="extmgr-text-label">Extensions</span><span id="extension-count" class="extmgr-text-value">-</span></div>
                    <div class="extmgr-text-row"><span class="extmgr-text-label">Active</span><span id="active-count" class="extmgr-text-value">-</span></div>
                    <div class="extmgr-text-row"><span class="extmgr-text-label">Inactive</span><span id="inactive-count" class="extmgr-text-value">-</span></div>
                    <div class="extmgr-text-row"><span class="extmgr-text-label">M Menu Visible</span><span id="m-visible-count" class="extmgr-text-value">-</span></div>
                    <div class="extmgr-text-row"><span class="extmgr-text-label">Library Visible</span><span id="library-visible-count" class="extmgr-text-value">-</span></div>
                    <div class="extmgr-text-row"><span class="extmgr-text-label">Settings Card Mode</span><span id="settings-card-count" class="extmgr-text-value">-</span></div>
                    <div class="extmgr-text-row"><span class="extmgr-text-label">ext-mgr RAM %</span><span id="service-mem-pct" class="extmgr-text-value">-</span></div>
                </div>
            </details>

            <details class="extmgr-collapse extmgr-submenu-section" open>
                <summary>Troubleshooting</summary>
                <p class="config-help-static extmgr-help">Use these tools to fix common ext-mgr installation issues.</p>
                <div class="extmgr-actions">
                    <button id="repair-btn" class="btn btn-small btn-danger" type="button"><i class="fa-solid fa-sharp fa-wrench"></i> Repair Installation</button>
                    <button id="sync-registry-btn" class="btn btn-small" type="button"><i class="fa-solid fa-sharp fa-rotate"></i> Sync Registry</button>
                </div>
                <div id="maintenance-log" class="extmgr-log">No maintenance actions executed.</div>
            </details>

            <details class="extmgr-collapse extmgr-submenu-section" open>
                <summary>Developer Requirements and FAQ</summary>
                <div class="extmgr-list-summary">Target release focus: ext-mgr 1.2. GitHub-backed update retrieval is active; signature verification policy is still configurable/planned.</div>
                <div class="config-help-static extmgr-help">Minimum requirements for ext-mgr import compatibility.</div>
                <div class="extmgr-list-summary">Required: manifest.json, info.json, extension entry PHP file, and optional service metadata under ext_mgr.service.</div>
                <div class="extmgr-faq-item">
                    <div class="extmgr-faq-q">How does disable work?</div>
                    <div class="extmgr-faq-a">Disable marks extension as inactive in registry and menu integrations can hide it or show it as unavailable.</div>
                </div>
                <div class="extmgr-faq-item">
                    <div class="extmgr-faq-q">What does repair do?</div>
                    <div class="extmgr-faq-a">Repair normalizes registry structure and refreshes maintenance timestamps and health metadata.</div>
                </div>
                <div class="extmgr-faq-item">
                    <div class="extmgr-faq-q">Can this manage service-based extensions?</div>
                    <div class="extmgr-faq-a">Yes, import wizard supports extension service metadata and enable/disable lifecycle hooks.</div>
                </div>
                <div class="extmgr-faq-item">
                    <div class="extmgr-faq-q">Can I choose where an extension is visible?</div>
                    <div class="extmgr-faq-a">Yes. Each extension has independent visibility toggles for M menu and Library menu.</div>
                </div>
            </details>
            </details>
        </fieldset>
    </div>
</div>

<script>
window.__EXT_MGR_INIT__ = <?php echo json_encode($init, JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="/extensions/sys/assets/js/ext-mgr.js"></script>

<?php
if ($usingMoodeShell) {
    if (file_exists('/var/www/footer.min.php')) {
        include '/var/www/footer.min.php';
    } elseif (file_exists('/var/www/footer.php')) {
        include '/var/www/footer.php';
    } else {
        include '/var/www/inc/footer.php';
    }
} else {
    ?>
</body>
</html>
    <?php
}
