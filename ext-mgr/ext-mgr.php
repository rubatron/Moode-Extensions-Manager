<?php
header('Content-Type: text/html; charset=utf-8');

$init = [
    'apiUrl' => '/ext-mgr-api.php',
    'apiUrls' => ['/ext-mgr-api.php', '/extensions/api/ext-mgr-api.php'],
    'tooltipUrl' => '/extensions/sys/content/tooltips.md',
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

    <body data-standalone="true">
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

            <div id="status" class="extmgr-status"></div>

            <section class="extmgr-panel" id="section-installed">
                <h2 class="extmgr-static-heading">Installed Extensions Settings</h2>
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
            </section>

            <section class="extmgr-panel" id="section-manager-options">
                <h2 class="extmgr-static-heading">Extension Manager Options</h2>

                <div class="extmgr-submenu is-open" id="submenu-manager-actions">
                    <button class="extmgr-submenu-header" type="button" data-submenu-toggle aria-expanded="false">
                        <span>Manager Actions</span>
                        <i class="fa-solid fa-angle-down" aria-hidden="true"></i>
                    </button>
                    <div class="extmgr-submenu-body">
                        <div class="extmgr-actions extmgr-options-buttons">
                            <button id="refresh-btn" class="btn btn-primary btn-small" type="button"><i class="fa-solid fa-sharp fa-arrows-rotate"></i> Refresh List</button>
                            <button id="sync-registry-btn" class="btn btn-primary btn-small" type="button"><i class="fa-solid fa-sharp fa-database"></i> Sync Registry</button>
                            <button id="system-update-btn" class="btn btn-primary btn-small" type="button"><i class="fa-solid fa-sharp fa-rotate"></i> Sync Extensions</button>
                        </div>
                    </div>
                </div>

                <div class="extmgr-submenu is-open" id="submenu-manager-import">
                    <button class="extmgr-submenu-header" type="button" data-submenu-toggle aria-expanded="false">
                        <span>Import Extension Wizard</span>
                        <i class="fa-solid fa-angle-down" aria-hidden="true"></i>
                    </button>
                    <div class="extmgr-submenu-body">
                        <p class="config-help-static extmgr-help">Upload a prepared extension package (.zip), review scan results, adjust metadata, then install.</p>

                        <!-- Import Wizard Container -->
                        <div class="extmgr-import-wizard" id="import-wizard">
                            <!-- Stepper Progress -->
                            <div class="extmgr-wizard-stepper" id="import-wizard-stepper" aria-label="Import wizard steps">
                                <button type="button" class="extmgr-wizard-step is-active" data-step="upload" data-step-num="1">
                                    <span class="extmgr-step-num">1</span>
                                    <span class="extmgr-step-label">Upload</span>
                                </button>
                                <button type="button" class="extmgr-wizard-step" data-step="metadata" data-step-num="2">
                                    <span class="extmgr-step-num">2</span>
                                    <span class="extmgr-step-label">Metadata</span>
                                </button>
                                <button type="button" class="extmgr-wizard-step" data-step="menu" data-step-num="3">
                                    <span class="extmgr-step-num">3</span>
                                    <span class="extmgr-step-label">Menu</span>
                                </button>
                                <button type="button" class="extmgr-wizard-step" data-step="service" data-step-num="4">
                                    <span class="extmgr-step-num">4</span>
                                    <span class="extmgr-step-label">Service</span>
                                </button>
                                <button type="button" class="extmgr-wizard-step" data-step="packages" data-step-num="5">
                                    <span class="extmgr-step-num">5</span>
                                    <span class="extmgr-step-label">Packages</span>
                                </button>
                                <button type="button" class="extmgr-wizard-step" data-step="bootconfig" data-step-num="6">
                                    <span class="extmgr-step-num">6</span>
                                    <span class="extmgr-step-label">Boot</span>
                                </button>
                                <button type="button" class="extmgr-wizard-step" data-step="review" data-step-num="7">
                                    <span class="extmgr-step-num">7</span>
                                    <span class="extmgr-step-label">Review</span>
                                </button>
                            </div>

                            <!-- Wizard Panels Container -->
                            <div class="extmgr-wizard-panels">
                                <section class="extmgr-wizard-panel is-active" data-panel="upload">
                                    <h3 class="extmgr-panel-title"><i class="fa-solid fa-upload"></i> Upload Extension</h3>
                                    <p class="extmgr-panel-desc">Select a .zip package to upload and scan for installation.</p>
                                    <div class="extmgr-import-controls">
                                        <input id="import-extension-file" class="extmgr-file-input" type="file" accept=".zip" aria-label="Upload extension package zip">
                                        <div class="extmgr-import-primary-row">
                                            <label for="import-extension-file" id="import-extension-file-trigger" class="btn btn-primary btn-small">Choose File</label>
                                            <span id="import-extension-file-name" class="extmgr-file-name">No file chosen</span>
                                            <button id="import-extension-btn" class="btn btn-primary btn-small" type="button"><i class="fa-solid fa-sharp fa-upload"></i> Upload + Scan</button>
                                        </div>
                                        <div class="extmgr-import-secondary-row">
                                            <a id="download-template-btn" class="btn btn-primary btn-small" href="/ext-mgr-api.php?action=download_extension_template"><i class="fa-solid fa-sharp fa-file-zipper"></i> Download Template Kit</a>
                                        </div>
                                    </div>
                                    <div class="extmgr-wizard-nav">
                                        <span></span>
                                        <button type="button" class="btn btn-primary btn-small extmgr-wizard-next" data-wizard-next><i class="fa-solid fa-arrow-right"></i> Next: Metadata</button>
                                    </div>
                                </section>

                                <section class="extmgr-wizard-panel" data-panel="metadata">
                                    <h3 class="extmgr-panel-title"><i class="fa-solid fa-tags"></i> Metadata</h3>
                                    <p class="extmgr-panel-desc">Configure extension name, version, and type classification.</p>
                                    <div class="extmgr-form-grid">
                                        <label>Name<input id="wizard-name" class="extmgr-input" type="text" placeholder="Extension name"></label>
                                        <label>Version<input id="wizard-version" class="extmgr-input" type="text" placeholder="1.0.0"></label>
                                        <label>Type
                                            <select id="wizard-type" class="extmgr-input">
                                                <option value="functionality">functionality</option>
                                                <option value="hardware">hardware</option>
                                                <option value="streaming_service">streaming_service</option>
                                                <option value="theme">theme</option>
                                                <option value="page">page</option>
                                                <option value="other">other</option>
                                            </select>
                                        </label>
                                    </div>
                                    <div class="extmgr-wizard-nav">
                                        <button type="button" class="btn btn-small extmgr-wizard-prev" data-wizard-prev><i class="fa-solid fa-arrow-left"></i> Back</button>
                                        <button type="button" class="btn btn-primary btn-small extmgr-wizard-next" data-wizard-next><i class="fa-solid fa-arrow-right"></i> Next: Menu</button>
                                    </div>
                                </section>

                                <section class="extmgr-wizard-panel" data-panel="menu">
                                    <h3 class="extmgr-panel-title"><i class="fa-solid fa-bars"></i> Menu Visibility</h3>
                                    <p class="extmgr-panel-desc">Select where the extension appears in moOde menus.</p>
                                    <div class="extmgr-form-inline extmgr-menu-options">
                                        <label class="extmgr-checkbox-card"><input id="wizard-menu-m" type="checkbox"><span class="extmgr-checkbox-label"><i class="fa-solid fa-m"></i> M menu</span></label>
                                        <label class="extmgr-checkbox-card"><input id="wizard-menu-library" type="checkbox"><span class="extmgr-checkbox-label"><i class="fa-solid fa-book"></i> Library menu</span></label>
                                        <label class="extmgr-checkbox-card"><input id="wizard-menu-system" type="checkbox"><span class="extmgr-checkbox-label"><i class="fa-solid fa-cog"></i> System/configure</span></label>
                                        <label class="extmgr-checkbox-card"><input id="wizard-settings-only" type="checkbox"><span class="extmgr-checkbox-label"><i class="fa-solid fa-sliders"></i> Settings-card only</span></label>
                                    </div>
                                    <div class="extmgr-wizard-nav">
                                        <button type="button" class="btn btn-small extmgr-wizard-prev" data-wizard-prev><i class="fa-solid fa-arrow-left"></i> Back</button>
                                        <button type="button" class="btn btn-primary btn-small extmgr-wizard-next" data-wizard-next><i class="fa-solid fa-arrow-right"></i> Next: Service</button>
                                    </div>
                                </section>

                                <section class="extmgr-wizard-panel" data-panel="service">
                                    <h3 class="extmgr-panel-title"><i class="fa-solid fa-server"></i> Service Configuration</h3>
                                    <p class="extmgr-panel-desc">Optional: Configure systemd service and dependencies.</p>
                                    <div class="extmgr-form-grid">
                                        <label>Service name<input id="wizard-service-name" class="extmgr-input" type="text" placeholder="my-extension.service"></label>
                                        <label class="extmgr-form-grid-wide">Dependencies (one per line)
                                            <textarea id="wizard-dependencies" class="extmgr-input" rows="3" placeholder="moode-extmgr.service"></textarea>
                                        </label>
                                    </div>
                                    <div class="extmgr-wizard-nav">
                                        <button type="button" class="btn btn-small extmgr-wizard-prev" data-wizard-prev><i class="fa-solid fa-arrow-left"></i> Back</button>
                                        <button type="button" class="btn btn-primary btn-small extmgr-wizard-next" data-wizard-next><i class="fa-solid fa-arrow-right"></i> Next: Packages</button>
                                    </div>
                                </section>

                                <section class="extmgr-wizard-panel" data-panel="packages">
                                    <h3 class="extmgr-panel-title"><i class="fa-solid fa-box"></i> Package Dependencies</h3>
                                    <p class="extmgr-panel-desc">List APT packages required by this extension.</p>
                                    <label>APT packages (one per line)
                                        <textarea id="wizard-apt-packages" class="extmgr-input" rows="4" placeholder="curl&#10;ffmpeg"></textarea>
                                    </label>
                                    <div class="extmgr-wizard-nav">
                                        <button type="button" class="btn btn-small extmgr-wizard-prev" data-wizard-prev><i class="fa-solid fa-arrow-left"></i> Back</button>
                                        <button type="button" class="btn btn-primary btn-small extmgr-wizard-next" data-wizard-next><i class="fa-solid fa-arrow-right"></i> Next: Boot Config</button>
                                    </div>
                                </section>

                                <section class="extmgr-wizard-panel" data-panel="bootconfig">
                                    <h3 class="extmgr-panel-title"><i class="fa-solid fa-microchip"></i> Boot Configuration</h3>
                                    <p class="extmgr-panel-desc">Hardware configuration for /boot/config.txt (SPI, I2C, GPIO, overlays).</p>
                                    <div id="wizard-bootconfig-warning" class="extmgr-note extmgr-note-warning" style="display:none;">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                        <span>Boot config detected - <strong>reboot required</strong> after installation.</span>
                                    </div>
                                    <label>Boot config lines (one per line)
                                        <textarea id="wizard-boot-config" class="extmgr-input" rows="5" placeholder="dtparam=spi=on&#10;dtparam=i2c_arm=on&#10;dtoverlay=hifiberry-dac"></textarea>
                                    </label>
                                    <p class="config-help-static extmgr-help" style="margin-top:0.5rem;">
                                        Common entries: <code>dtparam=spi=on</code>, <code>dtparam=i2c_arm=on</code>, <code>dtoverlay=...</code>, <code>gpio=...</code>
                                    </p>
                                    <div class="extmgr-wizard-nav">
                                        <button type="button" class="btn btn-small extmgr-wizard-prev" data-wizard-prev><i class="fa-solid fa-arrow-left"></i> Back</button>
                                        <button type="button" class="btn btn-primary btn-small extmgr-wizard-next" data-wizard-next><i class="fa-solid fa-arrow-right"></i> Next: Review</button>
                                    </div>
                                </section>

                                <section class="extmgr-wizard-panel" data-panel="review">
                                    <h3 class="extmgr-panel-title"><i class="fa-solid fa-clipboard-check"></i> Review &amp; Install</h3>
                                    <p class="extmgr-panel-desc">Review configuration and install the extension.</p>
                                    <div id="wizard-scan-summary" class="extmgr-note">No scan data yet.</div>
                                    <pre id="wizard-review-json" class="extmgr-log-content">{}</pre>

                                    <!-- Install Progress -->
                                    <div id="wizard-install-progress" class="extmgr-install-progress" style="display:none;">
                                        <div class="extmgr-progress-bar">
                                            <div id="wizard-progress-fill" class="extmgr-progress-fill" style="width:0%"></div>
                                        </div>
                                        <div id="wizard-progress-status" class="extmgr-progress-status">Preparing...</div>
                                    </div>

                                    <!-- Congratulations -->
                                    <div id="wizard-install-success" class="extmgr-install-success" style="display:none;">
                                        <i class="fa-solid fa-circle-check"></i>
                                        <h4>Installation Complete!</h4>
                                        <p id="wizard-success-message">Extension has been successfully installed.</p>
                                        <button type="button" id="wizard-close-btn" class="btn btn-primary btn-small" style="margin-top:1rem;"><i class="fa-solid fa-check"></i> Done</button>
                                    </div>

                                    <div class="extmgr-wizard-nav">
                                        <button type="button" class="btn btn-small extmgr-wizard-prev" data-wizard-prev><i class="fa-solid fa-arrow-left"></i> Back</button>
                                        <button id="import-extension-install-btn" class="btn btn-primary btn-small" type="button" disabled><i class="fa-solid fa-sharp fa-circle-check"></i> Install Extension</button>
                                    </div>
                                </section>
                            </div><!-- /.extmgr-wizard-panels -->
                        </div><!-- /.extmgr-import-wizard -->

                        <div id="import-wizard-note" class="extmgr-note">Template includes: template.php, backend/, templates/, assets/, packages/, scripts/install.sh, scripts/repair.sh, scripts/uninstall.sh, data/, cache/ and generated install metadata.</div>
                    </div>
                </div>

                <div class="extmgr-submenu is-open" id="submenu-manager-visibility">
                    <button class="extmgr-submenu-header" type="button" data-submenu-toggle aria-expanded="false">
                        <span>Extension Manager Visibility</span>
                        <i class="fa-solid fa-angle-down" aria-hidden="true"></i>
                    </button>
                    <div class="extmgr-submenu-body">
                        <p class="config-help-static extmgr-help">Hide/unhide ext-mgr itself in moOde menu areas without disabling the API endpoint.</p>
                        <div class="extmgr-manager-visibility-grid" role="group" aria-label="Extension manager visibility options">
                            <div class="extmgr-manager-visibility-row">
                                <span class="extmgr-manager-visibility-label">Header menu</span>
                                <div class="extmgr-manager-visibility-control">
                                    <div id="manager-visibility-header-btn" class="toggle toggle-off">
                                        <label class="toggle-radio" for="manager-visibility-header-off">ON</label>
                                        <input type="radio" id="manager-visibility-header-on" name="manager-visibility-header" value="On">
                                        <label class="toggle-radio" for="manager-visibility-header-on">OFF</label>
                                        <input type="radio" id="manager-visibility-header-off" name="manager-visibility-header" value="Off" checked>
                                    </div>
                                    <span id="manager-visibility-header-state" class="extmgr-manager-visibility-state">Hidden</span>
                                </div>
                            </div>
                            <div class="extmgr-manager-visibility-row">
                                <span class="extmgr-manager-visibility-label">Library menu</span>
                                <div class="extmgr-manager-visibility-control">
                                    <div id="manager-visibility-library-btn" class="toggle toggle-off">
                                        <label class="toggle-radio" for="manager-visibility-library-off">ON</label>
                                        <input type="radio" id="manager-visibility-library-on" name="manager-visibility-library" value="On">
                                        <label class="toggle-radio" for="manager-visibility-library-on">OFF</label>
                                        <input type="radio" id="manager-visibility-library-off" name="manager-visibility-library" value="Off" checked>
                                    </div>
                                    <span id="manager-visibility-library-state" class="extmgr-manager-visibility-state">Hidden</span>
                                </div>
                            </div>
                            <div class="extmgr-manager-visibility-row">
                                <span class="extmgr-manager-visibility-label extmgr-manager-visibility-label-m"><span class="extmgr-mbrand-badge" aria-hidden="true">m</span><span class="extmgr-mbrand-text">Menu</span></span>
                                <div class="extmgr-manager-visibility-control">
                                    <div id="manager-visibility-m-btn" class="toggle toggle-off">
                                        <label class="toggle-radio" for="manager-visibility-m-off">ON</label>
                                        <input type="radio" id="manager-visibility-m-on" name="manager-visibility-m" value="On">
                                        <label class="toggle-radio" for="manager-visibility-m-on">OFF</label>
                                        <input type="radio" id="manager-visibility-m-off" name="manager-visibility-m" value="Off" checked>
                                    </div>
                                    <span id="manager-visibility-m-state" class="extmgr-manager-visibility-state">Hidden</span>
                                </div>
                            </div>
                            <div class="extmgr-manager-visibility-row">
                                <span class="extmgr-manager-visibility-label extmgr-manager-visibility-label-m"><span class="extmgr-mbrand-badge" aria-hidden="true">m</span><span class="extmgr-mbrand-text">Configuration Tile</span></span>
                                <div class="extmgr-manager-visibility-control">
                                    <div id="manager-visibility-system-btn" class="toggle toggle-off">
                                        <label class="toggle-radio" for="manager-visibility-system-off">ON</label>
                                        <input type="radio" id="manager-visibility-system-on" name="manager-visibility-system" value="On">
                                        <label class="toggle-radio" for="manager-visibility-system-on">OFF</label>
                                        <input type="radio" id="manager-visibility-system-off" name="manager-visibility-system" value="Off" checked>
                                    </div>
                                    <span id="manager-visibility-system-state" class="extmgr-manager-visibility-state">Hidden</span>
                                </div>
                            </div>
                        </div>
                        <div id="manager-visibility-note" class="extmgr-note">Changes apply on next menu render and page refresh.</div>
                    </div>
                </div>
            </section>

            <section class="extmgr-panel extmgr-section" id="section-system">
                <button class="extmgr-menu-header" type="button" data-menu-toggle aria-expanded="false">
                    <span>System</span>
                    <i class="fa-solid fa-angle-down" aria-hidden="true"></i>
                </button>
                <div class="extmgr-section-body">
                    <div class="extmgr-submenu" id="submenu-update">
                        <button class="extmgr-submenu-header" type="button" data-submenu-toggle aria-expanded="false">
                            <span>Information</span>
                            <i class="fa-solid fa-angle-down" aria-hidden="true"></i>
                        </button>
                        <div class="extmgr-submenu-body">
                            <div class="extmgr-text-list">
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Version</span><span id="meta-version" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Author</span><span id="meta-creator" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">License</span><span id="meta-license" class="extmgr-text-value">-</span></div>
                            </div>

                            <div class="extmgr-actions">
                                <button id="check-update-btn" class="btn btn-primary btn-small" type="button"><i class="fa-solid fa-sharp fa-cloud-arrow-down"></i> Check Update</button>
                                <button id="run-update-btn" class="btn btn-primary btn-small" type="button"><i class="fa-solid fa-sharp fa-rotate"></i> Run Update</button>
                            </div>
                            <div id="update-note" class="extmgr-note">No update info yet.</div>

                            <div class="extmgr-submenu" id="submenu-advanced-update">
                                <button class="extmgr-submenu-header" type="button" data-submenu-toggle aria-expanded="false">
                                    <span>Advanced Update</span>
                                    <i class="fa-solid fa-angle-down" aria-hidden="true"></i>
                                </button>
                                <div class="extmgr-submenu-body">
                                    <div class="extmgr-advanced-grid">
                                        <label>Source Mode</label>
                                        <div id="advanced-mode-group" class="extmgr-mode-group" role="group" aria-label="Advanced update source mode">
                                            <button type="button" class="extmgr-mode-btn" data-advanced-mode="release">Release</button>
                                            <button type="button" class="extmgr-mode-btn" data-advanced-mode="main">Main</button>
                                            <button type="button" class="extmgr-mode-btn" data-advanced-mode="dev">Dev</button>
                                            <button type="button" class="extmgr-mode-btn" data-advanced-mode="custom">Custom URL</button>
                                        </div>

                                        <div id="advanced-custom-wrap" class="extmgr-custom-url-row" aria-live="polite">
                                            <label for="advanced-custom-url">Custom URL</label>
                                            <input id="advanced-custom-url" type="url" placeholder="https://example.com/ext-mgr" aria-label="Custom update source URL">
                                        </div>
                                    </div>
                                    <div class="extmgr-advanced-source">
                                        <span class="extmgr-advanced-source-label">Source</span>
                                        <span id="advanced-source-link" class="extmgr-advanced-source-link">-</span>
                                        <div class="extmgr-advanced-source-icons">
                                            <a id="open-advanced-source-btn" class="extmgr-icon-btn" href="#" target="_blank" rel="noopener noreferrer" aria-label="Open source link" title="Open source link">
                                                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                            </a>
                                            <button id="copy-advanced-source-btn" class="extmgr-icon-btn" type="button" aria-label="Copy source link" title="Copy source link">
                                                <i class="fa-solid fa-copy" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="extmgr-actions">
                                        <button id="save-advanced-update-btn" class="btn btn-primary btn-small" type="button"><i class="fa-solid fa-sharp fa-floppy-disk"></i> Save Advanced Update</button>
                                        <span id="advanced-update-note" class="extmgr-note">Modes: main, dev branch, custom URL.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="extmgr-submenu" id="submenu-api-status">
                        <button class="extmgr-submenu-header" type="button" data-submenu-toggle aria-expanded="false">
                            <span>API Status</span>
                            <i class="fa-solid fa-angle-down" aria-hidden="true"></i>
                        </button>
                        <div class="extmgr-submenu-body">
                            <div class="extmgr-text-list">
                                <div class="extmgr-text-row"><span class="extmgr-text-label">API Service</span><span id="api-service" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Registry</span><span id="registry-health" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Extensions</span><span id="extension-count" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Active</span><span id="active-count" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Inactive</span><span id="inactive-count" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">M Menu Visible</span><span id="m-visible-count" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Library Visible</span><span id="library-visible-count" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">System Context Visible</span><span id="system-visible-count" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Settings Card Mode</span><span id="settings-card-count" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">ext-mgr RAM %</span><span id="service-mem-pct" class="extmgr-text-value">-</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="extmgr-submenu" id="submenu-system-resources">
                        <button class="extmgr-submenu-header" type="button" data-submenu-toggle aria-expanded="false">
                            <span>System Resources</span>
                            <i class="fa-solid fa-angle-down" aria-hidden="true"></i>
                        </button>
                        <div class="extmgr-submenu-body">
                            <div class="extmgr-text-list">
                                <div class="extmgr-text-row"><span class="extmgr-text-label">CPU Usage (sampled)</span><span id="resource-cpu-usage" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Load Average (1/5/15)</span><span id="resource-load-avg" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Memory Used</span><span id="resource-memory-used" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Memory Available</span><span id="resource-memory-available" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Disk / (used / total)</span><span id="resource-disk-root" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Disk /var/www/extensions</span><span id="resource-disk-extensions" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">ext-mgr Process Memory</span><span id="resource-extmgr-mem" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Extensions Runtime Memory</span><span id="resource-extensions-mem" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Extensions Storage Footprint</span><span id="resource-extensions-storage" class="extmgr-text-value">-</span></div>
                            </div>
                            <div id="resource-extension-top" class="extmgr-note">Top extension memory consumers: loading...</div>
                            <div id="resource-requirements-note" class="extmgr-note">Requirements: collecting...</div>
                            <div class="extmgr-actions">
                                <button id="refresh-resources-btn" class="btn btn-primary btn-small" type="button"><i class="fa-solid fa-sharp fa-gauge"></i> Refresh Resources</button>
                            </div>
                        </div>
                    </div>

                    <div class="extmgr-submenu" id="submenu-maintenance-storage">
                        <button class="extmgr-submenu-header" type="button" data-submenu-toggle aria-expanded="false">
                            <span>Maintenance and Storage</span>
                            <i class="fa-solid fa-angle-down" aria-hidden="true"></i>
                        </button>
                        <div class="extmgr-submenu-body">
                            <div class="extmgr-text-list">
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Cache Directory</span><span id="cache-dir-path" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Cache Usage</span><span id="cache-dir-usage" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Backup Directory</span><span id="backup-dir-path" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Backup Snapshots</span><span id="backup-dir-count" class="extmgr-text-value">-</span></div>
                                <div class="extmgr-text-row"><span class="extmgr-text-label">Latest Backup</span><span id="backup-latest" class="extmgr-text-value">-</span></div>
                            </div>
                            <div class="extmgr-actions">
                                <button id="create-backup-btn" class="btn btn-primary btn-small" type="button"><i class="fa-solid fa-sharp fa-box-archive"></i> Create Backup Snapshot</button>
                                <button id="clear-cache-btn" class="btn btn-small btn-danger" type="button"><i class="fa-solid fa-sharp fa-trash"></i> Clear Cache Folder</button>
                            </div>
                            <div id="maintenance-storage-note" class="extmgr-note">Cache path: /var/www/extensions/cache. Backups are stored in /var/www/extensions/sys/backup.</div>
                        </div>
                    </div>

                    <div class="extmgr-submenu" id="submenu-variables">
                        <button class="extmgr-submenu-header" type="button" data-submenu-toggle aria-expanded="false">
                            <span>Variables Manager</span>
                            <i class="fa-solid fa-angle-down" aria-hidden="true"></i>
                        </button>
                        <div class="extmgr-submenu-body">
                            <div id="variables-manager-container" class="extmgr-variables-manager">
                                <!-- Wizard Stepper -->
                                <div id="variables-wizard-stepper" class="extmgr-wizard-stepper">
                                    <span class="extmgr-wizard-step is-active" data-step="scope">1. Scope</span>
                                    <span class="extmgr-wizard-step" data-step="select-extension">2. Extension</span>
                                    <span class="extmgr-wizard-step" data-step="edit">3. Edit</span>
                                </div>

                                <!-- Step 1: Scope Selection -->
                                <div class="extmgr-var-panel" data-var-panel="scope">
                                    <p class="extmgr-var-help">Choose which scope to manage:</p>
                                    <div class="extmgr-var-scope-btns">
                                        <button type="button" class="btn btn-primary" data-var-scope="system">
                                            <i class="fa-solid fa-server"></i> System Variables
                                        </button>
                                        <button type="button" class="btn btn-primary" data-var-scope="extension">
                                            <i class="fa-solid fa-puzzle-piece"></i> Extension Variables
                                        </button>
                                    </div>
                                    <p class="extmgr-note">System variables apply globally. Extension variables are per-extension config.</p>
                                </div>

                                <!-- Step 2: Extension Selection (only for extension scope) -->
                                <div class="extmgr-var-panel" data-var-panel="select-extension" style="display:none;">
                                    <p class="extmgr-var-help">Select an extension:</p>
                                    <div id="var-extension-list" class="extmgr-var-ext-list">
                                        <p class="extmgr-note">Loading extensions...</p>
                                    </div>
                                    <div class="extmgr-var-nav">
                                        <button type="button" class="btn btn-small" onclick="ExtMgrVariables.backToScope()">
                                            <i class="fa-solid fa-arrow-left"></i> Back
                                        </button>
                                    </div>
                                </div>

                                <!-- Step 3: Variables Editor -->
                                <div class="extmgr-var-panel" data-var-panel="edit" style="display:none;">
                                    <h4 id="var-editor-title" class="extmgr-var-editor-title">Variables</h4>
                                    <div id="var-editor" class="extmgr-var-editor">
                                        <p class="extmgr-note">Loading variables...</p>
                                    </div>
                                    <div class="extmgr-var-nav">
                                        <button type="button" class="btn btn-small" onclick="ExtMgrVariables.backToScope()">
                                            <i class="fa-solid fa-arrow-left"></i> Back to Scope
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="extmgr-submenu" id="submenu-troubleshooting">
                        <button class="extmgr-submenu-header" type="button" data-submenu-toggle aria-expanded="false">
                            <span>Troubleshooting</span>
                            <i class="fa-solid fa-angle-down" aria-hidden="true"></i>
                        </button>
                        <div class="extmgr-submenu-body">
                            <p class="config-help-static extmgr-help">Use these tools to fix common ext-mgr installation issues.</p>
                            <div class="extmgr-actions extmgr-troubleshooting-actions">
                                <button id="repair-btn" class="btn btn-primary btn-small" type="button"><i class="fa-solid fa-sharp fa-wrench"></i> Repair Installation</button>
                                <button id="open-extmgr-logs-btn" class="btn btn-primary btn-small" type="button"><i class="fa-solid fa-sharp fa-file-lines"></i> Open ext-mgr Logs</button>
                                <button id="download-extmgr-logs-btn" class="btn btn-primary btn-small" type="button"><i class="fa-solid fa-sharp fa-download"></i> Download ext-mgr Logs</button>
                                <button id="clear-extensions-folder-btn" class="btn btn-small btn-danger" type="button"><i class="fa-solid fa-sharp fa-triangle-exclamation"></i> Clear Extensions Folder</button>
                            </div>
                            <p class="config-help-static extmgr-help" style="margin-top:0.8rem;">Boot configuration management for hardware extensions (SPI, I2C, overlays).</p>
                            <div class="extmgr-actions extmgr-troubleshooting-actions">
                                <button id="boot-config-init-btn" class="btn btn-primary btn-small" type="button"><i class="fa-solid fa-sharp fa-microchip"></i> Initialize Boot Config</button>
                                <button id="boot-config-status-btn" class="btn btn-small" type="button"><i class="fa-solid fa-sharp fa-circle-info"></i> Boot Config Status</button>
                                <button id="boot-config-list-btn" class="btn btn-small" type="button"><i class="fa-solid fa-sharp fa-list"></i> List Boot Fragments</button>
                            </div>
                            <p class="config-help-static extmgr-help" style="margin-top:0.8rem;">Debug information for developers.</p>
                            <div class="extmgr-actions extmgr-troubleshooting-actions">
                                <button id="show-registry-btn" class="btn btn-small" type="button"><i class="fa-solid fa-sharp fa-database"></i> Show Registry</button>
                                <button id="show-variables-btn" class="btn btn-small" type="button"><i class="fa-solid fa-sharp fa-code"></i> Show Variables</button>
                                <button id="show-services-btn" class="btn btn-small" type="button"><i class="fa-solid fa-sharp fa-server"></i> Show Services</button>
                                <button id="show-api-status-btn" class="btn btn-small" type="button"><i class="fa-solid fa-sharp fa-plug"></i> Show API Status</button>
                                <button id="show-database-btn" class="btn btn-small" type="button"><i class="fa-solid fa-sharp fa-hard-drive"></i> Test Database</button>
                                <button id="show-moode-api-btn" class="btn btn-small" type="button"><i class="fa-solid fa-sharp fa-music"></i> Test moOde API</button>
                            </div>
                            <div id="maintenance-log" class="extmgr-log">No maintenance actions executed.</div>
                        </div>
                    </div>

                    <div class="extmgr-submenu" id="submenu-docs">
                        <button class="extmgr-submenu-header" type="button" data-submenu-toggle aria-expanded="false">
                            <span>Developer Requirements and FAQ</span>
                            <i class="fa-solid fa-angle-down" aria-hidden="true"></i>
                        </button>
                        <div class="extmgr-submenu-body">
                            <div class="extmgr-md-meta">Editable content files: guidance.md, developer-requirements.md, faq.md</div>
                            <div id="guidance-doc" class="extmgr-md-block">Loading guidance...</div>
                            <div id="requirements-doc" class="extmgr-md-block">Loading developer requirements...</div>
                            <div id="faq-doc" class="extmgr-md-block">Loading FAQ...</div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script>
        window.__EXT_MGR_INIT__ = <?php echo json_encode($init, JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="/extensions/sys/assets/js/ext-mgr-logs.js"></script>
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
