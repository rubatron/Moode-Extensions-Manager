<?php
// ext-mgr page shell with settings-like layout and troubleshooting actions.
header('Content-Type: text/html; charset=utf-8');

$init = [
    'apiUrl' => '/ext-mgr-api.php',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Extensions Manager</title>
    <style>
        :root {
            --bg: #0f1116;
            --panel: #171b22;
            --line: #2a2f39;
            --text: #e7ebf2;
            --muted: #9da7b8;
            --accent: #ff8b1a;
            --danger: #d94b41;
            --ok: #39a860;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            padding: 18px;
        }
        .container { max-width: 1080px; margin: 0 auto; }
        h1 { margin: 0 0 6px; font-size: 28px; }
        .subtitle { margin: 0 0 16px; color: var(--muted); }
        .panel {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: linear-gradient(180deg, #1a1f28, #141922);
            margin-bottom: 12px;
        }
        .panel-header {
            padding: 12px 14px;
            border-bottom: 1px solid var(--line);
            font-weight: 600;
            color: #f5f7fb;
        }
        .panel-body { padding: 12px 14px; }
        .row {
            display: grid;
            grid-template-columns: repeat(3, minmax(140px, 1fr));
            gap: 12px;
        }
        .metric {
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.01);
        }
        .metric-label {
            color: var(--muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .metric-value { margin-top: 4px; }
        .actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .actions input,
        .actions select {
            border: 1px solid var(--line);
            background: #242b36;
            color: var(--text);
            border-radius: 6px;
            padding: 8px 10px;
        }
        button {
            border: 1px solid var(--line);
            background: #242b36;
            color: var(--text);
            border-radius: 6px;
            padding: 8px 12px;
            cursor: pointer;
        }
        button:hover { border-color: #3f4756; }
        .btn-accent { background: #49311a; border-color: #7b4a1f; color: #ffd2a8; }
        .btn-danger { background: #3d1f22; border-color: #6a2f36; color: #ffd1d1; }
        .status { margin-bottom: 12px; color: var(--muted); }
        .status.ok { color: var(--ok); }
        .status.error { color: #ff7f7f; }
        .update-note { color: var(--muted); margin-top: 8px; }
        .list-item {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            border-top: 1px solid var(--line);
            padding: 10px 0;
        }
        .list-item:first-child { border-top: none; padding-top: 0; }
        .list-name { font-weight: 600; }
        .list-sub { color: var(--muted); font-size: 13px; margin-top: 2px; }
        .list-top { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .badge {
            display: inline-block;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 11px;
            line-height: 1.6;
            border: 1px solid var(--line);
            color: var(--muted);
        }
        .badge.active { color: #b9ffd2; border-color: #2d5e3f; background: #1a3123; }
        .badge.inactive { color: #ffd2d2; border-color: #5f2d31; background: #2b171a; }
        .item-actions { display: flex; gap: 8px; align-items: center; }
        .btn-muted { background: #20242e; color: #d3dae7; }
        .list-summary { color: var(--muted); margin-bottom: 8px; font-size: 13px; }
        .faq-item { margin-bottom: 8px; }
        .faq-q { color: #dce5f7; font-weight: 600; }
        .faq-a { color: var(--muted); margin-top: 2px; }
        .log {
            margin-top: 10px;
            border: 1px solid var(--line);
            background: #10141b;
            border-radius: 6px;
            min-height: 72px;
            padding: 8px;
            color: #b7c0cf;
            font-family: Consolas, monospace;
            font-size: 12px;
            white-space: pre-wrap;
        }
        @media (max-width: 768px) {
            .row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Extensions Manager</h1>
        <p class="subtitle">Settings and troubleshooting control panel for extension maintenance.</p>

        <div id="status" class="status">Loading...</div>

        <section class="panel">
            <div class="panel-header">API Status</div>
            <div class="panel-body row">
                <div class="metric">
                    <div class="metric-label">API Service</div>
                    <div id="api-service" class="metric-value">-</div>
                </div>
                <div class="metric">
                    <div class="metric-label">Registry</div>
                    <div id="registry-health" class="metric-value">-</div>
                </div>
                <div class="metric">
                    <div class="metric-label">Extensions</div>
                    <div id="extension-count" class="metric-value">-</div>
                </div>
                <div class="metric">
                    <div class="metric-label">Active</div>
                    <div id="active-count" class="metric-value">-</div>
                </div>
                <div class="metric">
                    <div class="metric-label">Inactive</div>
                    <div id="inactive-count" class="metric-value">-</div>
                </div>
                <div class="metric">
                    <div class="metric-label">Pinned</div>
                    <div id="pinned-count" class="metric-value">-</div>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">Extension Info</div>
            <div class="panel-body row">
                <div class="metric"><div class="metric-label">Version</div><div id="meta-version" class="metric-value">-</div></div>
                <div class="metric"><div class="metric-label">Author</div><div id="meta-creator" class="metric-value">-</div></div>
                <div class="metric"><div class="metric-label">License</div><div id="meta-license" class="metric-value">-</div></div>
            </div>
            <div class="panel-body" style="padding-top:0;">
                <div class="actions">
                    <button id="check-update-btn" class="btn-accent" type="button">Check Update</button>
                    <button id="run-update-btn" class="btn-accent" type="button">Run Update</button>
                    <button id="system-update-btn" type="button">System Settings Hook (placeholder)</button>
                    <button id="refresh-btn" type="button">Refresh List</button>
                </div>
                <div id="update-note" class="update-note">No update info yet.</div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">Troubleshooting</div>
            <div class="panel-body">
                <p class="subtitle" style="margin:0 0 8px;">Use these tools to fix common ext-mgr installation issues.</p>
                <div class="actions">
                    <button id="repair-btn" class="btn-danger" type="button">Repair Installation</button>
                </div>
                <div id="maintenance-log" class="log">No maintenance actions executed.</div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">Installed Extensions</div>
            <div class="panel-body" style="padding-bottom:0; color:var(--muted);">Inactive extensions remain in registry but are marked unavailable in menu integrations.</div>
            <div class="panel-body" style="padding-top:10px; padding-bottom:0;">
                <div class="actions">
                    <select id="list-filter" aria-label="Filter extensions">
                        <option value="all">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="pinned">Pinned</option>
                    </select>
                    <select id="list-sort" aria-label="Sort extensions">
                        <option value="name">Sort: Name</option>
                        <option value="state">Sort: State</option>
                        <option value="pinned">Sort: Pinned first</option>
                    </select>
                    <input id="list-search" type="search" placeholder="Search extension" aria-label="Search extension">
                </div>
            </div>
            <div id="list-summary" class="panel-body list-summary" style="padding-top:8px; padding-bottom:0;">-</div>
            <div id="list" class="panel-body" aria-live="polite"></div>
        </section>

        <section class="panel">
            <div class="panel-header">Developer Requirements and FAQ</div>
            <div class="panel-body">
                <div class="list-summary">Target release focus: <code>ext-mgr 1.2</code>. GitHub-backed update retrieval is active; signature verification policy is still configurable/planned.</div>
                <div class="subtitle" style="margin-bottom:10px;">Minimum requirements for ext-mgr import compatibility.</div>
                <div class="list-summary">Required: <code>manifest.json</code>, <code>info.json</code>, extension entry PHP file, and optional service metadata under <code>ext_mgr.service</code>.</div>
                <div class="faq-item">
                    <div class="faq-q">How does disable work?</div>
                    <div class="faq-a">Disable marks extension as inactive in registry and menu integrations can hide it or show it as unavailable.</div>
                </div>
                <div class="faq-item">
                    <div class="faq-q">What does repair do?</div>
                    <div class="faq-a">Repair normalizes registry structure and refreshes maintenance metadata.</div>
                </div>
                <div class="faq-item">
                    <div class="faq-q">Can this manage service-based extensions?</div>
                    <div class="faq-a">Yes, import wizard supports extension service metadata and enable/disable lifecycle hooks.</div>
                </div>
                <div class="faq-item">
                    <div class="faq-q">Can I choose where an extension is visible?</div>
                    <div class="faq-a">Yes. Each extension now has independent visibility toggles for M menu and Library menu.</div>
                </div>
            </div>
        </section>
    </div>

    <script>
        window.__EXT_MGR_INIT__ = <?php echo json_encode($init, JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="assets/js/ext-mgr.js"></script>
</body>
</html>
