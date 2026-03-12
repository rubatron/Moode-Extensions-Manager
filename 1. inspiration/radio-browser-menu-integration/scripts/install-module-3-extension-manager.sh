#!/usr/bin/env bash
set -euo pipefail

TEMPLATE_FILE="/var/www/templates/indextpl.min.html"
EXT_DIR="/var/www/extensions"
INSTALLED_DIR="/var/www/extensions/installed"
STAMP="$(date +%Y%m%d-%H%M%S)"

if [[ "${EUID}" -eq 0 ]]; then
    SUDO=""
else
    SUDO="sudo"
fi

require_file() {
    local f="$1"
    if [[ ! -f "$f" ]]; then
        echo "ERROR: missing required file: $f" >&2
        exit 1
    fi
}

require_dir() {
    local d="$1"
    if [[ ! -d "$d" ]]; then
        echo "ERROR: missing required directory: $d" >&2
        exit 1
    fi
}

require_file "$TEMPLATE_FILE"
require_dir "$EXT_DIR"
require_dir "$INSTALLED_DIR"

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

echo "[1/8] Backing up template..."
$SUDO cp -a "$TEMPLATE_FILE" "${TEMPLATE_FILE}.bak-module3-${STAMP}"

echo "[2/8] Writing registry helper..."
cat > "$TMP_DIR/extensions-registry.php" <<'PHP'
<?php

function normalizeExtensionName(string $id): string {
    return ucwords(str_replace(['-', '_'], ' ', $id));
}

function resolveExtensionEntry(string $id): string {
    $extBase = "/var/www/extensions/installed/{$id}";

    // Prefer root-level entry points (symlinks) when present.
    if (file_exists("/var/www/{$id}.php")) {
        return "/{$id}.php";
    }

    $candidates = [
        '/index.php',
        '/main.php',
        "/{$id}.php"
    ];

    foreach ($candidates as $candidate) {
        if (file_exists($extBase . $candidate)) {
            return "/extensions/installed/{$id}{$candidate}";
        }
    }

    return "/extensions/installed/{$id}/";
}

function discoverInstalledExtensions(string $installedDir = '/var/www/extensions/installed'): array {
    $result = [];

    if (!is_dir($installedDir)) {
        return $result;
    }

    $entries = scandir($installedDir) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $full = $installedDir . '/' . $entry;
        if (!is_dir($full)) {
            continue;
        }

        $result[] = [
            'id' => $entry,
            'name' => normalizeExtensionName($entry),
            'entry' => resolveExtensionEntry($entry),
            'source' => $full,
            'enabled' => true
        ];
    }

    usort($result, function ($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    return $result;
}

function loadPinnedMap(string $path = '/var/www/extensions/registry.json'): array {
    if (!file_exists($path)) {
        return [];
    }

    $raw = @file_get_contents($path);
    $data = json_decode((string)$raw, true);
    if (!is_array($data) || !is_array($data['extensions'] ?? null)) {
        return [];
    }

    $map = [];
    foreach ($data['extensions'] as $ext) {
        $id = (string)($ext['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $map[$id] = !empty($ext['pinned']);
    }

    return $map;
}

function buildRegistryPayload(string $path = '/var/www/extensions/registry.json'): array {
    $pinnedMap = loadPinnedMap($path);
    $extensions = discoverInstalledExtensions();

    foreach ($extensions as &$ext) {
        $id = (string)($ext['id'] ?? '');
        $ext['pinned'] = !empty($pinnedMap[$id]);
    }
    unset($ext);

    return [
        'generated_at' => date('c'),
        'extensions' => $extensions
    ];
}

function saveExtensionRegistry(string $path = '/var/www/extensions/registry.json'): array {
    $payload = buildRegistryPayload($path);
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return $payload;
}
PHP

$SUDO install -o www-data -g www-data -m 0644 "$TMP_DIR/extensions-registry.php" "$EXT_DIR/extensions-registry.php"

echo "[3/8] Writing extensions manager page..."
cat > "$TMP_DIR/ext-mgr.php" <<'PHP'
<?php
require_once '/var/www/inc/common.php';
require_once '/var/www/inc/sql.php';
require_once '/var/www/extensions/extensions-registry.php';

$registryPath = '/var/www/extensions/registry.json';
if (!file_exists($registryPath)) {
    saveExtensionRegistry($registryPath);
}

$data = json_decode(@file_get_contents($registryPath), true);
$extensions = is_array($data['extensions'] ?? null) ? $data['extensions'] : [];
$total = count($extensions);
$pinned = 0;
foreach ($extensions as $row) {
    if (!empty($row['pinned'])) {
        $pinned++;
    }
}

$section = 'extensions';
$extmeta = [];
$page = '/ext-mgr.php';

$headerFile = '/var/www/header.php';
if (file_exists($headerFile)) {
    include $headerFile;
} else {
    include '/var/www/inc/header.php';
}

echo '<script src="/extensions/ext-mgr-modal-fix.js" defer></script>' . "\n";
?>

<div id="container">
<div class="container">
<div class="row">

<div class="col-lg-12">
    <style>
        .extmgr-header { display: flex; align-items: center; gap: 10px; margin: 4px 0 6px; }
        .extmgr-header i { opacity: .8; }
        .extmgr-intro { margin: 0 0 14px; }
        .extmgr-overview .control-group { margin-bottom: 8px; }
        .extmgr-overview .control-label { width: 130px; }
        .extmgr-overview .controls { margin-left: 150px; padding-top: 2px; }
        .extmgr-overview .config-help-static { color: #ddd; }
        .extmgr-item { border-top: 1px solid rgba(128,128,128,0.25); padding-top: 8px; margin-top: 8px; }
        .extmgr-item:first-child { border-top: 0; padding-top: 0; margin-top: 0; }
        .extmgr-id { font-family: monospace; font-size: 12px; opacity: .8; }
        .extmgr-entry { display: inline-block; margin-top: 2px; }
        .extmgr-toggle-wrap { margin-top: 8px; display: flex; align-items: center; gap: 10px; }
        .extmgr-state { opacity: .75; font-size: 12px; }
    </style>

    <div class="extmgr-header">
        <i class="fa-solid fa-sharp fa-puzzle-piece"></i>
        <h1 class="config-title" style="margin:0;">Extension Manager</h1>
    </div>
    <p class="config-help-static extmgr-intro">Configure extensions in a familiar moOde settings style. Pinning keeps the Extensions panel visible when the Library menu opens.</p>

    <fieldset class="extmgr-overview">
        <legend>Overview</legend>
        <div class="control-group">
            <label class="control-label">Discovered</label>
            <div class="controls"><span class="config-help-static"><?php echo (int)$total; ?> extension(s)</span></div>
        </div>
        <div class="control-group">
            <label class="control-label">Pinned</label>
            <div class="controls"><span class="config-help-static"><?php echo (int)$pinned; ?> extension(s)</span></div>
        </div>
        <div class="control-group">
            <label class="control-label">Actions</label>
            <div class="controls">
                <a class="btn btn-primary btn-small" href="/ext-mgr-refresh.php"><i class="fa-solid fa-sharp fa-rotate"></i> Refresh extension list</a>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Extensions</legend>
        <?php if (empty($extensions)): ?>
            <span class="config-help-static"><em>No extensions discovered.</em></span>
        <?php else: ?>
            <?php foreach ($extensions as $ext): ?>
                <?php
                    $extId = (string)($ext['id'] ?? '');
                    $safeToggleId = preg_replace('/[^a-z0-9-]/i', '-', $extId);
                    $isPinned = !empty($ext['pinned']);
                ?>
                <form class="form-horizontal extmgr-item" method="post" action="/ext-mgr-pin.php">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($extId, ENT_QUOTES); ?>">

                    <div class="control-group">
                        <label class="control-label"><?php echo htmlspecialchars($ext['name'] ?? $extId, ENT_QUOTES); ?></label>
                        <div class="controls">
                            <span class="extmgr-id">ID: <?php echo htmlspecialchars($extId, ENT_QUOTES); ?></span><br>
                            <?php if (!empty($ext['entry'])): ?>
                                <a class="extmgr-entry" href="<?php echo htmlspecialchars($ext['entry'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($ext['entry'], ENT_QUOTES); ?></a>
                            <?php else: ?>
                                <span class="config-help-static">No entry URL</span>
                            <?php endif; ?>

                            <div class="extmgr-toggle-wrap">
                                <div class="toggle">
                                    <label class="toggle-radio toggle-<?php echo htmlspecialchars($safeToggleId, ENT_QUOTES); ?>" for="toggle-<?php echo htmlspecialchars($safeToggleId, ENT_QUOTES); ?>-2">ON</label>
                                    <input type="radio" name="pinned" id="toggle-<?php echo htmlspecialchars($safeToggleId, ENT_QUOTES); ?>-1" value="1" <?php echo $isPinned ? 'checked="checked"' : ''; ?> onchange="this.form.submit();">
                                    <label class="toggle-radio toggle-<?php echo htmlspecialchars($safeToggleId, ENT_QUOTES); ?>" for="toggle-<?php echo htmlspecialchars($safeToggleId, ENT_QUOTES); ?>-1">OFF</label>
                                    <input type="radio" name="pinned" id="toggle-<?php echo htmlspecialchars($safeToggleId, ENT_QUOTES); ?>-2" value="0" <?php echo !$isPinned ? 'checked="checked"' : ''; ?> onchange="this.form.submit();">
                                </div>
                                <span class="extmgr-state">Pinned in Library panel</span>
                            </div>
                        </div>
                    </div>
                </form>
            <?php endforeach; ?>
        <?php endif; ?>
    </fieldset>
</div>

</div>
</div>
</div>

<?php
$footerFile = '/var/www/footer.min.php';
if (!file_exists($footerFile)) {
    $footerFile = '/var/www/footer.php';
}
if (!file_exists($footerFile)) {
    $footerFile = '/var/www/inc/footer.php';
}
include $footerFile;
PHP

$SUDO install -o www-data -g www-data -m 0644 "$TMP_DIR/ext-mgr.php" "$EXT_DIR/ext-mgr.php"

echo "[4/8] Writing refresh endpoint..."
cat > "$TMP_DIR/ext-mgr-refresh.php" <<'PHP'
<?php
require_once '/var/www/extensions/extensions-registry.php';
saveExtensionRegistry('/var/www/extensions/registry.json');
header('Location: /ext-mgr.php');
exit;
PHP

$SUDO install -o www-data -g www-data -m 0644 "$TMP_DIR/ext-mgr-refresh.php" "$EXT_DIR/ext-mgr-refresh.php"

cat > "$TMP_DIR/ext-mgr-pin.php" <<'PHP'
<?php
$path = '/var/www/extensions/registry.json';
$id = preg_replace('/[^a-z0-9._-]/i', '', (string)($_POST['id'] ?? ''));

if ($id === '' || !file_exists($path)) {
    header('Location: /ext-mgr.php');
    exit;
}

$data = json_decode((string)@file_get_contents($path), true);
if (!is_array($data) || !is_array($data['extensions'] ?? null)) {
    header('Location: /ext-mgr.php');
    exit;
}

$isPinned = !empty($_POST['pinned']);
foreach ($data['extensions'] as &$ext) {
    if (($ext['id'] ?? '') === $id) {
        $ext['pinned'] = $isPinned;
        break;
    }
}
unset($ext);

file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
header('Location: /ext-mgr.php');
exit;
PHP

$SUDO install -o www-data -g www-data -m 0644 "$TMP_DIR/ext-mgr-pin.php" "$EXT_DIR/ext-mgr-pin.php"

cat > "$TMP_DIR/ext-mgr-modal-fix.js" <<'JS'
(function (window, document) {
    'use strict';

    function initFix($) {
        if (!$ || !$.fn || !$.fn.modal) {
            return;
        }

        // Same fallback logic used for radio-browser modal open reliability.
        $(document).on('click.extMgrConfigureModalFix', 'a[href="#configure-modal"]', function (e) {
            var $modal = $('#configure-modal');
            if (!$modal.length) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();
            $modal.removeClass('hide').modal('show');
        });

        if (window.location.hash === '#configure-modal') {
            setTimeout(function () {
                var $modal = $('#configure-modal');
                if ($modal.length) {
                    $modal.removeClass('hide').modal('show');
                }
            }, 0);
        }
    }

    if (window.jQuery) {
        initFix(window.jQuery);
        return;
    }

    document.addEventListener('DOMContentLoaded', function () {
        initFix(window.jQuery || window.$);
    });
})(window, document);
JS

$SUDO install -o www-data -g www-data -m 0644 "$TMP_DIR/ext-mgr-modal-fix.js" "$EXT_DIR/ext-mgr-modal-fix.js"

# Legacy compatibility endpoints (old naming)
cat > "$TMP_DIR/extensions-manager.php" <<'PHP'
<?php
header('Location: /ext-mgr.php', true, 302);
exit;
PHP

cat > "$TMP_DIR/extensions-manager-refresh.php" <<'PHP'
<?php
header('Location: /ext-mgr-refresh.php', true, 302);
exit;
PHP

cat > "$TMP_DIR/extensions-manager-pin.php" <<'PHP'
<?php
header('Location: /ext-mgr-pin.php', true, 302);
exit;
PHP

$SUDO install -o www-data -g www-data -m 0644 "$TMP_DIR/extensions-manager.php" "$EXT_DIR/extensions-manager.php"
$SUDO install -o www-data -g www-data -m 0644 "$TMP_DIR/extensions-manager-refresh.php" "$EXT_DIR/extensions-manager-refresh.php"
$SUDO install -o www-data -g www-data -m 0644 "$TMP_DIR/extensions-manager-pin.php" "$EXT_DIR/extensions-manager-pin.php"

echo "[5/8] Creating root shortcuts + initial registry..."
$SUDO ln -sfn /var/www/extensions/ext-mgr.php /var/www/ext-mgr.php
$SUDO ln -sfn /var/www/extensions/ext-mgr-refresh.php /var/www/ext-mgr-refresh.php
$SUDO ln -sfn /var/www/extensions/ext-mgr-pin.php /var/www/ext-mgr-pin.php

$SUDO ln -sfn /var/www/ext-mgr.php /var/www/extensions-manager.php
$SUDO ln -sfn /var/www/ext-mgr-refresh.php /var/www/extensions-manager-refresh.php
$SUDO ln -sfn /var/www/ext-mgr-pin.php /var/www/extensions-manager-pin.php

$SUDO php -r "require '/var/www/extensions/extensions-registry.php'; saveExtensionRegistry('/var/www/extensions/registry.json');"
$SUDO chown www-data:www-data /var/www/extensions/registry.json

echo "[6/8] Updating topbar config-tabs button..."
$SUDO python3 - <<'PY'
from pathlib import Path

p = Path('/var/www/header.php')
s = p.read_text(encoding='utf-8', errors='ignore')
s2 = s.replace('id="ext-mgr-btn" class="btn" href="ext-mgr.php"', 'id="ext-mgr-btn" class="btn" href="/ext-mgr.php"')

if s2 != s:
    p.write_text(s2, encoding='utf-8')
    s = s2
    print('normalized: header.php ext-mgr-btn href')

btn = '<a id="ext-mgr-btn" class="btn" href="/ext-mgr.php"><span>Extensions</span><i class="fa-solid fa-sharp fa-puzzle-piece"></i></a>'

if 'id="ext-mgr-btn"' in s:
    print('skipped: ext-mgr-btn already present')
else:
    marker = '<a id="per-config-btn" class="btn" href="per-config.php"><span>Peripherals</span><i class="fa-solid fa-sharp fa-display"></i></a>'
    if marker in s:
        s = s.replace(marker, marker + '\n\t\t\t\t\t' + btn, 1)
    else:
        fallback = '</div>\n\n\t\t<div id="library-header"></div>'
        if fallback not in s:
            raise SystemExit('ERROR: config-tabs marker not found in header.php')
        s = s.replace(fallback, '\n\t\t\t\t\t' + btn + '\n\t\t\t\t</div>\n\n\t\t<div id="library-header"></div>', 1)

    p.write_text(s, encoding='utf-8')
    print('patched: header.php ext-mgr-btn')
PY

echo "[7/8] Writing hover menu JS asset..."
cat > "$TMP_DIR/extensions-hover-menu.js" <<'JS'
(function () {
    if (window.__extensionsHoverMenuInit) {
        return;
    }
    window.__extensionsHoverMenuInit = true;

    var lastItems = [];
    function esc(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function normalizePath(url) {
        try {
            var u = new URL(url, window.location.origin);
            return u.pathname;
        } catch (e) {
            return url;
        }
    }

    function ensureSubmenuStyle() {
        if (document.getElementById('extensions-submenu-style')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'extensions-submenu-style';
        style.textContent = [
            '.extensions-hover-panel{display:none;}',
            '.extensions-manager-btn.menu-separator{padding:0 7.71px !important;min-height:42.9px;line-height:42.9px;}',
            '.extensions-hover-item.btn{flex:1 1 auto;width:auto;border-radius:0 !important;padding:0 7.71px 0 2.1em !important;min-height:42.9px;line-height:42.9px;}',
            '.extensions-hover-item .ext-icon{margin-right:.55em;opacity:.9;}',
            '.extensions-hover-item.btn.active{box-shadow:inset 0 0 2px rgba(0,0,0,.15);}'
        ].join('');
        document.head.appendChild(style);
    }

    function sortWithPinnedFirst(items) {
        if (!Array.isArray(items)) {
            return items || [];
        }
        var pinned = [];
        var rest = [];
        for (var i = 0; i < items.length; i++) {
            if ((items[i] || {}).pinned) {
                pinned.push(items[i]);
            } else {
                rest.push(items[i]);
            }
        }
        return pinned.concat(rest);
    }

    function hasPinnedItems() {
        if (!Array.isArray(lastItems)) {
            return false;
        }
        for (var i = 0; i < lastItems.length; i++) {
            if ((lastItems[i] || {}).pinned) {
                return true;
            }
        }
        return false;
    }

    function renderList(items) {
        var host = document.getElementById('extensions-hover-list');
        if (!host) {
            return;
        }

        if (!Array.isArray(items) || items.length === 0) {
            host.innerHTML = '<span style="display:block;padding:8px 12px 8px 2.1em;color:#aaa;">No extensions found</span>';
            return;
        }

        var currentPath = window.location.pathname;
        var ordered = sortWithPinnedFirst(items);

        var html = '';
        for (var i = 0; i < ordered.length; i++) {
            var it = ordered[i] || {};
            if (!it.entry) {
                continue;
            }

            var active = normalizePath(it.entry) === currentPath;
            var label = esc(it.name || it.id || it.entry);

            html += '<a class="btn extensions-hover-item' + (active ? ' active' : '') + '" data-current="' + (active ? '1' : '0') + '" href="' + esc(it.entry) + '">';
            html += '<i class="fa-solid fa-sharp fa-globe ext-icon"></i>' + label;
            html += '</a>';
        }

        host.innerHTML = html || '<span style="display:block;padding:8px 12px 8px 2.1em;color:#aaa;">No extensions found</span>';
    }

    function bindItemInteractions(root) {
        var items = root.querySelectorAll('.extensions-hover-item');
        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            if (item.dataset.boundHover === '1') {
                continue;
            }
            item.dataset.boundHover = '1';

            item.addEventListener('mouseenter', function () {
                this.classList.add('active');
            });

            item.addEventListener('mouseleave', function () {
                if (this.dataset.current !== '1') {
                    this.classList.remove('active');
                }
            });
        }
    }

    function loadRegistry(panel, afterRender) {
        fetch('/extensions/registry.json?ts=' + Date.now(), { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                lastItems = (data && data.extensions) || [];
                renderList(lastItems);
                bindItemInteractions(panel);
                if (typeof afterRender === 'function') {
                    afterRender();
                }
            })
            .catch(function () {
                lastItems = [];
                renderList([]);
                bindItemInteractions(panel);
                if (typeof afterRender === 'function') {
                    afterRender();
                }
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        ensureSubmenuStyle();

        var wrap = document.querySelector('.extensions-hover-menu');
        var panel = document.getElementById('extensions-hover-panel');
        var trigger = wrap ? wrap.querySelector('.extensions-manager-btn') : null;
        var libraryToggle = document.getElementById('current-tab');
        var dropdown = libraryToggle ? libraryToggle.closest('.dropdown') : null;

        if (!wrap || !panel || !trigger) {
            return;
        }

        function isDropdownOpen() {
            return dropdown ? dropdown.classList.contains('open') : false;
        }

        function shouldStickOpen() {
            return isDropdownOpen() && hasPinnedItems();
        }

        function setPanelOpen() {
            panel.style.display = 'block';
            trigger.classList.add('active');
        }

        function setPanelClosed() {
            panel.style.display = 'none';
            trigger.classList.remove('active');
        }

        function openPanel() {
            setPanelOpen();
            loadRegistry(panel);
        }

        function closePanel(force) {
            if (!force && shouldStickOpen()) {
                return;
            }
            setPanelClosed();
        }

        function syncPinnedStickyState() {
            if (!isDropdownOpen()) {
                setPanelClosed();
                return;
            }

            loadRegistry(panel, function () {
                if (shouldStickOpen()) {
                    setPanelOpen();
                } else {
                    setPanelClosed();
                }
            });
        }

        window.__extensionsSyncPinned = syncPinnedStickyState;

        wrap.addEventListener('mouseenter', function () {
            openPanel();
        });

        wrap.addEventListener('mouseleave', function () {
            closePanel(false);
        });

        if (libraryToggle) {
            libraryToggle.addEventListener('click', function () {
                setTimeout(syncPinnedStickyState, 10);
            });
        }

        if (dropdown) {
            var mo = new MutationObserver(syncPinnedStickyState);
            mo.observe(dropdown, { attributes: true, attributeFilter: ['class'] });
        }

        syncPinnedStickyState();
    });
})();
JS

$SUDO install -o www-data -g www-data -m 0644 "$TMP_DIR/extensions-hover-menu.js" "$EXT_DIR/extensions-hover-menu.js"

echo "[8/8] Updating Configure modal (M menu) tile..."
$SUDO python3 - <<'PY'
from pathlib import Path

tile = '<li><a href="ext-mgr.php" class="btn btn-large"><i class="fa-solid fa-sharp fa-puzzle-piece"></i><br>Extensions</a></li>'
tile = '<li><a href="/ext-mgr.php" class="btn btn-large"><i class="fa-solid fa-sharp fa-puzzle-piece"></i><br>Extensions</a></li>'

targets = [
    Path('/var/www/footer.min.php'),
    Path('/var/www/footer.php'),
]

inserted_any = False
for p in targets:
    if not p.exists():
        continue

    s = p.read_text(encoding='utf-8', errors='ignore')
    s2 = s.replace('href="ext-mgr.php" class="btn btn-large"', 'href="/ext-mgr.php" class="btn btn-large"')

    if s2 != s:
        p.write_text(s2, encoding='utf-8')
        s = s2
        print(f'normalized href: {p}')

    if 'href="/ext-mgr.php" class="btn btn-large"' in s:
        print(f'skipped (already present): {p}')
        inserted_any = True
        continue

    marker_clock = '<?php if ($section == \'index\') { ?> <li class="context-menu"'
    marker_camilla = '<li><a href="cdsp-config.php" class="btn btn-large"><i class="fa-solid fa-sharp fa-square-sliders-vertical"></i><br>CamillaDSP</a></li>'
    marker_close = '</ul></div></div><div class="modal-footer">'

    if marker_clock in s:
        s = s.replace(marker_clock, tile + ' ' + marker_clock, 1)
    elif marker_camilla in s:
        s = s.replace(marker_camilla, marker_camilla + ' ' + tile, 1)
    elif marker_close in s:
        s = s.replace(marker_close, tile + marker_close, 1)
    else:
        print(f'warn: configure modal marker not found in {p}')
        continue

    p.write_text(s, encoding='utf-8')
    print(f'patched: {p}')
    inserted_any = True

if not inserted_any:
    raise SystemExit('ERROR: could not patch configure modal in footer files')
PY

echo "[9/8] Updating index template menu button (hover submenu)..."
$SUDO python3 - <<'PY'
from pathlib import Path

p = Path('/var/www/templates/indextpl.min.html')
s = p.read_text(encoding='utf-8', errors='ignore')

s = s.replace('/extensions/installed/radio-browser/radio-browser.php', '/radio-browser.php')
s = s.replace("window.location.href='/extensions-manager.php';", "window.location.href='/ext-mgr.php';")
s = s.replace('/extensions-manager.php', '/ext-mgr.php')

base_btn = '<button aria-label="Extensions" class="btn extensions-manager-btn menu-separator" href="#notarget" onclick="window.location.href=\'/ext-mgr.php\';"><i class="fa-solid fa-sharp fa-puzzle-piece"></i> Extensions</button>'

if 'radio-browser-link-btn' in s:
    s = s.replace(
        'aria-label="Radio Browser" class="btn radio-browser-link-btn menu-separator" href="#notarget" onclick="window.location.href=\'/radio-browser.php\';"><i class="fa-solid fa-sharp fa-globe"></i> Radio Browser',
        'aria-label="Extensions" class="btn extensions-manager-btn menu-separator" href="#notarget" onclick="window.location.href=\'/ext-mgr.php\';"><i class="fa-solid fa-sharp fa-puzzle-piece"></i> Extensions'
    )

if 'extensions-hover-menu' not in s:
    if base_btn in s:
        s = s.replace(
            base_btn,
            '<span class="extensions-hover-menu" style="position:relative;display:block;width:100%;">'
            + base_btn.replace('class="btn extensions-manager-btn menu-separator"', 'class="btn extensions-manager-btn menu-separator" style="width:100%;"')
            + '<div id="extensions-hover-panel" class="extensions-hover-panel" style="display:none;position:static;min-width:0;z-index:auto;background:transparent;border:none;box-shadow:none;padding:0 0 4px 0;border-radius:0;">'
            + '<div id="extensions-hover-list"></div>'
            + '</div></span>',
            1
        )
    else:
        marker = '</span></button> <button aria-label="Folder" class="btn folder-view-btn" href="#library-panel">'
        insert = '</span></button> <span class="extensions-hover-menu" style="position:relative;display:block;width:100%;">' + base_btn.replace('class="btn extensions-manager-btn menu-separator"', 'class="btn extensions-manager-btn menu-separator" style="width:100%;"') + '<div id="extensions-hover-panel" class="extensions-hover-panel" style="display:none;position:static;min-width:0;z-index:auto;background:transparent;border:none;box-shadow:none;padding:0 0 4px 0;border-radius:0;"><div id="extensions-hover-list"></div></div></span> <button aria-label="Folder" class="btn folder-view-btn" href="#library-panel">'
        if marker not in s:
            raise SystemExit('ERROR: index template marker not found')
        s = s.replace(marker, insert, 1)

radio_start = s.find('<button aria-label="Radio" class="btn radio-view-btn"')
ext_start = s.find('<span class="extensions-hover-menu"')
if radio_start != -1 and ext_start != -1 and ext_start > radio_start:
    radio_block = s[radio_start:ext_start].rstrip()
    folder_start = s.find('<button aria-label="Folder" class="btn folder-view-btn"', ext_start)
    if folder_start != -1:
        ext_block = s[ext_start:folder_start].rstrip()
        s = s[:radio_start] + ext_block + ' ' + radio_block + ' ' + s[folder_start:]

script_tag = '<script src="/extensions/extensions-hover-menu.js" defer></script>'
if script_tag not in s:
    anchor = '</span> <button aria-label="Folder" class="btn folder-view-btn" href="#library-panel">'
    if anchor not in s:
        raise SystemExit('ERROR: script injection anchor not found')
    s = s.replace(anchor, '</span> ' + script_tag + ' <button aria-label="Folder" class="btn folder-view-btn" href="#library-panel">', 1)

p.write_text(s, encoding='utf-8')
print('patched')
PY

echo "[10/8] Validation..."
$SUDO php -l "$EXT_DIR/ext-mgr.php"
$SUDO php -l "$EXT_DIR/ext-mgr-refresh.php"
$SUDO php -l "$EXT_DIR/ext-mgr-pin.php"
$SUDO php -l "$EXT_DIR/extensions-manager.php"
$SUDO php -l "$EXT_DIR/extensions-manager-refresh.php"
$SUDO php -l "$EXT_DIR/extensions-manager-pin.php"

curl -s http://localhost/index.php | tr '\n' ' ' | sed 's/></>\n</g' | grep -n 'extensions-hover-menu\|extensions-manager-btn\|/ext-mgr.php' | head -n 10
curl -s http://localhost/index.php | tr '\n' ' ' | sed 's/></>\n</g' | grep -n 'href="/ext-mgr.php" class="btn btn-large"\|configure-modal' | head -n 20
curl -s http://localhost/ext-mgr.php | grep -qi 'Extension Manager' && echo 'ext-mgr: OK'

echo
echo "OK: Module 3 extension manager installed"
echo "Template backup: ${TEMPLATE_FILE}.bak-module3-${STAMP}"
