# moOde ext-mgr Extension Porting Guide

Guide for porting moOde Audio Player add-ons to the **ext-mgr extension format** (v2-template).

---

## Context

moOde Audio Player runs on Raspberry Pi (Debian/Raspbian). The ext-mgr system manages extensions that live in:
```
/var/www/extensions/installed/<ext-id>/
```
Each extension is a sandbox. Everything outside that folder is a symlink or footprint entry — never a direct write.

---

## Responsibility Division — CRITICAL

ext-mgr runs `install.sh` as **`moode-extmgrusr`** (unprivileged). **Before** `install.sh` runs, ext-mgr has already executed as root:

| Task | Who | How |
|------|-----|-----|
| `apt-get install` | ext-mgr (root) | `manifest.json → ext_mgr.install.packages` |
| Systemd service install | ext-mgr (root) | `install-helper.sh` → `/etc/systemd/system/` |
| Canonical route symlink | ext-mgr (root) | `/var/www/<ext-id>.php → template.php` |

**`install.sh` can and should only:**
- `pip install --target=$ROOT/packages/pylib/` (sandbox ownership)
- Write in `$ROOT/**`
- Create wrapper scripts in `$ROOT/packages/bin/`
- Create `data/`, `cache/`, `logs/` dirs

**`install.sh` must NEVER:**
- Call `apt-get install` (ext-mgr does this)
- Call `systemctl` (ext-mgr does this)
- `ln -sf` to `/etc/systemd/system/` (ext-mgr does this)
- `ln -sf` to `/usr/local/bin/` (NO permissions → Permission denied)

---

## Required File Structure

```
<ext-id>/
├── manifest.json          ← ext-mgr metadata (see below)
├── info.json              ← display info
├── template.php           ← main page (new pattern)
├── ext-mgr.menu-preset.json
├── ext-mgr-patterns.json
├── README.md
├── assets/
│   ├── css/template.css
│   └── js/template.js
├── backend/
│   └── api.php
├── scripts/
│   ├── install.sh
│   ├── uninstall.sh
│   ├── repair.sh
│   ├── service-runner.sh
│   └── moode-extmgr-<ext-id>.service
├── tools/
│   ├── ext_helper.py
│   └── README.md
├── templates/             ← optional: HTML fragments
├── packages/              ← pip/deb artifacts
├── data/                  ← persistent runtime data (survives cache flush)
├── logs/
└── cache/
```

---

## manifest.json Schema

```json
{
    "id": "<ext-id>",
    "name": "Human Readable Name",
    "version": "1.0.0",
    "main": "template.php",
    "ext_mgr": {
        "enabled": true,
        "state": "active",
        "type": "functionality",
        "stageProfile": "visible-by-default",
        "menuVisibility": { "m": true, "library": true, "system": false },
        "settingsCardOnly": false,
        "iconClass": "fa-solid fa-puzzle-piece",
        "service": {
            "name": "moode-extmgr-<ext-id>.service",
            "requiresExtMgr": true,
            "parentService": "moode-extmgr.service",
            "dependencies": []
        },
        "logging": {
            "localDir": "logs",
            "globalDir": "/var/www/extensions/sys/logs/extensionslogs/<ext-id>",
            "files": ["install.log", "system.log", "error.log"]
        },
        "install": {
            "packages": [],
            "script": "scripts/install.sh"
        }
    }
}
```

**Key points:**
- `id` = ext-mgr ext-id = root folder name in zip = canonical route name
- `service.name` = `moode-extmgr-<ext-id>.service` (required prefix)
- `install.packages` = apt packages that ext-mgr installs as root before install.sh

---

## info.json Schema

```json
{
    "name": "Human Readable Name",
    "version": "1.0.0",
    "type": "functionality",
    "author": "Author Name",
    "license": "GPL-3.0-or-later",
    "description": "Extension description for display in ext-mgr UI.",
    "repository": "https://github.com/user/repo",
    "settingsPage": "/<ext-id>.php",
    "iconClass": "fa-solid fa-puzzle-piece",
    "certified": false
}
```

---

## template.php — Required Pattern (v2)

```php
<?php
header('Content-Type: text/html; charset=utf-8');

$usingMoodeShell = false;
$section = 'extensions';

if (file_exists('/var/www/inc/common.php'))  require_once '/var/www/inc/common.php';
if (file_exists('/var/www/inc/session.php')) require_once '/var/www/inc/session.php';
if (function_exists('sqlConnect') && function_exists('phpSession')) {
    @sqlConnect(); @phpSession('open');
}

// $extRouteId determined dynamically — works regardless of how ext-mgr creates the route
$extRouteId = preg_replace('/\.php$/', '', basename((string)($_SERVER['SCRIPT_NAME'] ?? '<ext-id>.php')));
if (!is_string($extRouteId) || trim($extRouteId) === '') $extRouteId = '<ext-id>';
$assetBase = '/extensions/installed/' . $extRouteId;

// headerVisible from ext-mgr registry
$extMgrHideHeader = true;
$registryPath = '/var/local/www/extensions/registry.json';
if (file_exists($registryPath)) {
    $reg = @json_decode(@file_get_contents($registryPath), true);
    if (is_array($reg)) foreach ($reg as $ext) {
        if (isset($ext['id']) && $ext['id'] === $extRouteId) {
            $extMgrHideHeader = !($ext['headerVisible'] ?? false); break;
        }
    }
}
if (function_exists('storeBackLink')) @storeBackLink($section, $extRouteId);

if (file_exists('/var/www/header.php')) {
    $usingMoodeShell = true;
    include '/var/www/header.php';
    if ($extMgrHideHeader) echo '<style id="ext-nav-suppress">#config-tabs{display:none!important}</style>' . "\n";
    echo '<link rel="stylesheet" href="' . htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') . '/assets/css/template.css">' . "\n";
} else { ?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Extension Name</title>
<?php echo '<link rel="stylesheet" href="' . htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') . '/assets/css/template.css">'; ?>
</head><body><?php }

// --- EXTENSION LOGIC HERE ---

// Inject JavaScript variables (before footer)
echo '<script>var EXT_API="' . htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') . '/backend/api.php";</script>';
echo '<script src="' . htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') . '/assets/js/template.js"></script>';

if ($usingMoodeShell) {
    if (file_exists('/var/www/footer.min.php'))  include '/var/www/footer.min.php';
    elseif (file_exists('/var/www/footer.php'))  include '/var/www/footer.php';
    else include '/var/www/inc/footer.php';
} else { ?></body></html><?php } ?>
```

**Critical:**
- Use **`#config-tabs { display:none!important }`** — NOT `#navbar-settings` or other selectors
- `$extRouteId` always dynamic via `$_SERVER['SCRIPT_NAME']`
- `$assetBase` always computed, also standalone
- Nav-suppress only when `$extMgrHideHeader === true`

---

## CSS Base Structure (template.css)

```css
/* Settings nav suppression — always present as fallback */
#config-tabs { display: none !important; }

/* Required base classes — ext-mgr expects these */
.ext-template-shell { padding-bottom: 1.2rem; }

.ext-template-header {
    display: flex; align-items: center; gap: 0.6rem;
}
.ext-template-header i { font-size: 1.2rem; color: var(--accentxts, #d35400); }

.ext-template-card {
    margin-top: 0.8rem;
    border: 1px solid rgba(128,128,128,0.28);
    border-radius: 6px;
    padding: 0.7rem;
    background: rgba(0,0,0,0.14);
}
.ext-template-card-title { font-size: 1rem; margin: 0 0 0.5rem; }

/* moOde CSS tokens — always use var() */
/* --accentxts = accent color (orange in default theme)  */
/* --adapttext = primary text color                      */
/* --textvariant = dimmed text                           */
/* --btnshade2 = button background                       */
/* --img-border-radius = rounded corners                 */
/* --themebg = page background                           */
```

---

## scripts/install.sh — Correct Pattern

```bash
#!/usr/bin/env bash
# Runs as: moode-extmgrusr (unprivileged)
# ext-mgr handles: apt, systemd, canonical route
set -euo pipefail

EXT_ID="${EXT_MGR_EXTENSION_ID:-<ext-id>}"
ROOT="${EXT_MGR_EXTENSION_ROOT:-/var/www/extensions/installed/$EXT_ID}"

case "$ROOT" in
  /var/www/extensions/installed/*) ;;
  *) echo "[$EXT_ID] skip: unsafe ROOT"; exit 0 ;;
esac

mkdir -p "$ROOT/logs" "$ROOT/cache" "$ROOT/data"
LOG="$ROOT/logs/install.log"
log() { echo "[$(date +'%Y-%m-%d %H:%M:%S')] [$EXT_ID] $*" | tee -a "$LOG"; }

log "=== install started === ROOT=$ROOT ($(id -un))"

# EXTENSION-SPECIFIC STEPS HERE
# E.g. pip install, wrapper scripts, etc.

log "=== install completed ==="
echo "[$EXT_ID] install completed"
```

---

## scripts/moode-extmgr-<ext-id>.service

```ini
[Unit]
Description=<Extension Name> service
Requires=moode-extmgr.service
After=moode-extmgr.service network.target
PartOf=moode-extmgr.service

[Service]
Type=oneshot
RemainAfterExit=yes
User=moode-extmgrusr
Group=moode-extmgr
WorkingDirectory=/var/www/extensions/installed/<ext-id>
ExecStart=/bin/true

[Install]
WantedBy=multi-user.target
```

Use `Type=oneshot` unless the extension needs a real daemon. Use `Type=simple` + `scripts/service-runner.sh` only if continuous polling/daemon is required.

---

## Porting Decision Table

When porting an existing addon, classify each file:

| Category | Action | Reason |
|----------|--------|--------|
| Main PHP page | **REWRITE** to template.php pattern | Nav-selector, assetBase, headerVisible |
| Interactive install.sh (CLI, root) | **REPLACE** with ext-mgr install.sh | ext-mgr handles apt/systemd |
| backend/api.php | **PORT** — logic unchanged | Only adjust path constants |
| Frontend JS/CSS | **PORT** — unchanged | Adjust API URL |
| HTML templates | **PORT** — unchanged | Template variables via PHP |
| `restart_services`/`reboot` API calls | **DROP** | `www-data` has no sudo rights |
| `data/*.json` (persistent data) | **KEEP** — sandbox OK | `data/` survives cache flush |
| `cache/` files | **KEEP** — sandbox OK | Cache in own folder |
| moOde DB operations (cfg_radio etc.) | **KEEP** — correct pattern | moOde API correctly used |
| Writes to `/var/local/www/` | **INFO** — track in footprint | Outside sandbox but acceptable |

---

## Path Policy

| Path | Level | Action |
|------|-------|--------|
| `/var/www/extensions/installed/<id>/` | OK | Full sandbox |
| `/var/www/extensions/sys/` | OK | Shared sys root |
| `/etc/systemd/system/*.service` | OK via ext-mgr | Symlink by ext-mgr |
| `/usr/local/bin/` | INFO | ext-mgr has rights, install.sh does NOT |
| `/var/local/www/imagesw/radio-logos/` | INFO | Track in footprint |
| `/var/lib/mpd/playlists/` | INFO | MPD requires this path |
| `/var/www/` (outside installed/) | VIOLATION | Block |
| `/etc/` (outside systemd) | VIOLATION | Block |

---

## Zip Format Rules

- Root folder name in zip = `ext-id` (exactly equal to `manifest.json → id`)
- No `.gitkeep` in output zip
- No `__pycache__`, `*.pyc`, `build-zip.sh` itself, `*.code-workspace`
- `data/`, `logs/`, `cache/` present but empty (with `.gitkeep` during development)
- `tools/ext_helper.py` present for scanner

---

## Porting Considerations

1. **API URL** in frontend JS: always via PHP-injected variable, never hardcoded
2. **`$_custom_api_options`** initialize before use (otherwise PHP notice)
3. **`php-gd`** and **`php-curl`** in `manifest.json → ext_mgr.install.packages` if api.php uses GD/cURL
4. **No `require_once` paths** that assume extension is in `/var/www/`
5. **`#config-tabs`** is the only correct moOde nav-selector
