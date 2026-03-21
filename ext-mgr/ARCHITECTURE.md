# ext-mgr Architecture

> moOde Extensions Manager - Complete System Architecture
> Last updated: March 2026

## Overview

ext-mgr is the extension management system for moOde audio player (Raspberry Pi). It provides a secure, guided workflow for installing, managing, and updating third-party extensions.

## System Components

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              Browser (UI)                                   │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐ │
│  │ ext-mgr.php │  │ ext-mgr.js  │  │ ext-mgr.css │  │ Import Wizard       │ │
│  │ (View)      │  │ (Logic)     │  │ (Styling)   │  │ (Chevron Stepper)   │ │
│  └──────┬──────┘  └──────┬──────┘  └─────────────┘  └─────────────────────┘ │
└─────────┼────────────────┼──────────────────────────────────────────────────┘
          │                │
          ▼                ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         ext-mgr-api.php                                     │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │ API Actions: list, status, import_*, set_*, debug_*, repair, etc.   │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│  ┌──────────────────────┐  ┌──────────────────────┐  ┌───────────────────┐  │
│  │ registry.json        │  │ ext-mgr.meta.json    │  │ ext_helper.py     │  │
│  │ (Extension State)    │  │ (Manager Config)     │  │ (Code Scanner)    │  │
│  └──────────────────────┘  └──────────────────────┘  └───────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
          │
          ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           File System                                       │
│  /var/www/extensions/                                                       │
│  ├── installed/<id>/        ← Extension installations                       │
│  │   ├── manifest.json      ← Extension metadata                            │
│  │   ├── assets/            ← CSS, JS, images                               │
│  │   ├── backend/           ← PHP API, Python helpers                       │
│  │   ├── scripts/           ← install.sh, uninstall.sh, service units       │
│  │   ├── packages/          ← config files, data, services                  │
│  │   ├── logs/              ← Extension logs                                │
│  │   └── .ext-mgr/          ← Install metadata, runtime links               │
│  ├── sys/                                                                   │
│  │   ├── logs/              ← Manager logs + watchdog logs                  │
│  │   ├── cache/             ← Scan cache, upload staging                    │
│  │   └── backup/            ← Uninstall backups, snapshots                  │
│  └── tmp/                   ← Temporary upload processing                   │
│                                                                             │
│  /var/www/<id>.php          ← Canonical extension route (symlink)           │
└─────────────────────────────────────────────────────────────────────────────┘
          │
          ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           System Services                                   │
│  ┌─────────────────────────┐  ┌────────────────────────────────────────┐    │
│  │ moode-extmgr.service    │  │ moode-extmgr-watchdog.service          │    │
│  │ (Control Plane)         │  │ (Heartbeat Monitor)                    │    │
│  │ Runs as: moode-extmgrusr│  │ Restarts stale control-plane           │    │
│  └─────────────────────────┘  └────────────────────────────────────────┘    │
│  ┌─────────────────────────┐                                                │
│  │ <extension>.service     │  ← Per-extension service units                 │
│  │ Requires: moode-extmgr  │                                                │
│  └─────────────────────────┘                                                │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Core Files

| File | Purpose |
|------|---------|
| `ext-mgr.php` | Main UI view, renders extension cards and wizard |
| `api/ext-mgr-api.php` | All API endpoints (~35 actions) |
| `ext-mgr.js` | Frontend logic, wizard stepper, progress UI |
| `ext-mgr.css` | Complete styling including moOde palette |
| `registry.json` | Extension state (enabled, menu visibility) |
| `ext-mgr.meta.json` | Manager configuration |
| `ext_helper.py` | Python code scanner with pattern detection |
| `ext_helper_lite.py` | Lightweight scanner for template kit |

## Import Wizard Flow

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│ 1.Upload │ → │ 2.Metadata│ → │ 3.Menu   │ → │ 4.Review │ → │ 5.Install│
│          │    │ name/ver │    │ toggles  │    │ summary  │    │ progress │
└──────────┘    └──────────┘    └──────────┘    └──────────┘    └──────────┘
     │                                                               │
     ▼                                                               ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  API Calls:                                                              │
│  1. import_extension_upload → extracts ZIP, returns manifest             │
│  2. import_extension_scan   → runs ext_helper.py, detects patterns       │
│  3. import_extension_install → copies files, runs install.sh, services   │
└──────────────────────────────────────────────────────────────────────────┘
```

### Wizard UI Components

- **Chevron Stepper**: CSS clip-path arrows with `.is-active` glow
- **Progress Bar**: Animated gradient with stage labels
- **Success Panel**: Congratulations message after install

## API Reference

### Extension Management

| Action | Method | Description |
|--------|--------|-------------|
| `list` / `refresh` | GET | List all extensions with state |
| `status` | GET | Extension manager status |
| `set_enabled` | POST | Enable/disable extension |
| `remove_extension` | POST | Uninstall extension (with cleanup) |
| `repair` | POST | Run extension repair script |
| `repair_symlink` | POST | Fix broken canonical symlink |

### Import Operations

| Action | Method | Description |
|--------|--------|-------------|
| `import_extension_upload` | POST | Upload & extract ZIP |
| `import_extension_scan` | POST | Scan package with ext_helper.py |
| `import_extension_install` | POST | Install to /var/www/extensions/installed |
| `download_extension_template` | GET | Download starter template ZIP |

### Menu Visibility

| Action | Method | Description |
|--------|--------|-------------|
| `set_menu_visibility` | POST | Toggle M menu / Library menu |
| `set_settings_card_only` | POST | Show only settings card (no page) |
| `set_manager_visibility` | POST | Hide ext-mgr from moOde menus |

### Updates

| Action | Method | Description |
|--------|--------|-------------|
| `check_update` | GET | Check for ext-mgr updates |
| `run_update` | POST | Execute self-update |
| `set_update_advanced` | POST | Configure update source/branch |
| `system_update_hook` | POST | Hook for moOde system updates |

### Logs & Debug

| Action | Method | Description |
|--------|--------|-------------|
| `list_extension_logs` | GET | List available log files |
| `read_extension_log` | GET | Read log content |
| `download_extension_log` | GET | Download log file |
| `analyze_logs` | GET | Run log analysis with AI patterns |
| `debug_registry` | GET | Dump registry.json |
| `debug_variables` | GET | Dump environment variables |
| `debug_services` | GET | Dump systemd service states |
| `debug_api` | GET | API health check |

### System

| Action | Method | Description |
|--------|--------|-------------|
| `system_resources` | GET | CPU, memory, disk info |
| `clear_cache` | POST | Clear ext-mgr cache |
| `create_backup_snapshot` | POST | Create backup snapshot |
| `clear_extensions_folder` | POST | Remove all extensions |
| `variables` | GET | List ext-mgr variables |
| `set_variable` | POST | Set variable value |
| `delete_variable` | POST | Delete variable |
| `get_variable` | GET | Get single variable |
| `registry_sync` | POST | Force registry sync |

### boot_config Management

| Action | Method | Description |
|--------|--------|-------------|
| `boot_config_add` | POST | Add config.txt fragment for extension |
| `boot_config_remove` | POST | Remove config.txt fragment |
| `boot_config_list` | GET | List active fragments |
| `boot_config_status` | GET | Check boot_config capability |

## Security Model

### Principals

- **moode-extmgrusr**: Control-plane service account (limited privileges)
- **moode-extmgr**: Legacy/reserved principal

### Path Validation

- Extension IDs validated: alphanumeric + hyphens only
- Path traversal blocked in ZIP extraction
- Canonical links restricted to `/var/www/`
- Extension roots restricted to `/var/www/extensions/installed/`

### Privilege Isolation

- Symlink repair via privileged helper: `/usr/local/sbin/ext-mgr-repair-symlink`
- boot_config management via sudoers: `/var/www/extensions/api/ext-mgr-boot-config.sh`
- Service management via systemd with proper After/Requires chains
- Atomic JSON writes for state files

## Code Scanner (ext_helper.py)

Scans extension packages for:

### moOde Component Detection

- `header.php` / `footer.php` includes
- Navbar suppression patterns (`display: none`, etc.)
- Template structure validation

### Code Patterns (Warnings)

- `eval()`, `exec()`, `shell_exec()` calls
- Direct `$_GET`/`$_POST` without sanitization
- SQL injection patterns
- Hardcoded credentials
- Debug statements left in code

### Manifest Validation

- Required fields: `id`, `name`, `main`
- Optional: `version`, `description`, `author`
- ext-mgr hooks: `ext_mgr.install.packages`, `ext_mgr.install.script`

## UI Palette (moOde)

```css
--rp-accent: #c55a11;        /* Orange - primary accent */
--bg-dark: #1a1a1a;          /* Background */
--panel-bg: #2a2a2a;         /* Cards, panels */
--text-primary: #e0e0e0;     /* Main text */
--text-muted: #888;          /* Secondary text */
--success: #4caf50;          /* Green */
--warning: #ff9800;          /* Amber */
--error: #f44336;            /* Red */
```

## Directory Structure

```
ext-mgr/
├── assets/
│   ├── css/ext-mgr.css      # All styling
│   ├── js/ext-mgr.js        # Frontend logic
│   └── images/              # Icons, logos
├── backend/
│   ├── ext_helper.py        # Full scanner
│   └── ext_helper_lite.py   # Template kit scanner
├── content/                 # UI templates
├── docs/                    # Documentation
│   ├── architecture/
│   ├── guides/
│   └── README.md
├── scripts/
│   ├── install.sh           # Installer
│   ├── bootstrap-moode.sh   # Remote bootstrap
│   └── ext-mgr-*.sh         # Various helpers
├── api/
│   ├── ext-mgr-api.php      # API (~7000 lines)
│   └── ext-mgr-boot-config.sh # /boot/firmware/config.txt management
├── ext-mgr.php              # Main view
├── registry.json            # Extension state
└── manifest.json            # ext-mgr manifest
```

## Extension Manifest Format

```json
{
  "id": "my-extension",
  "name": "My Extension",
  "version": "1.0.0",
  "description": "Does something useful",
  "author": "Developer Name",
  "main": "my-extension.php",
  "ext_mgr": {
    "install": {
      "packages": ["python3-pip", "ffmpeg"],
      "script": "scripts/install.sh"
    },
    "service": {
      "name": "my-extension.service",
      "dependencies": ["network-online.target"]
    }
  }
}
```

## Service Parenting & Memory Management

All extension services chain to the parent `moode-extmgr.service` via systemd dependency directives.
This ensures graceful shutdown cascades and proper memory cleanup when the manager stops.

### Service Naming Convention

| Service | Purpose |
|---------|--------|
| `moode-extmgr.service` | Parent service - all extensions depend on this |
| `ext-{extensionId}.service` | Per-extension service unit |

### Dependency Injection

During install, the helper script automatically injects:

1. **Manifest dependencies**: Declared in `ext_mgr.service.dependencies`
2. **Bundled services**: Auto-detected `.service` files in `scripts/` or `packages/services/`
3. **Package services**: Services from bundled packages

All dependencies are combined into the main service unit's `Requires` and `After` directives.

### Service Unit Template

```ini
[Unit]
Description=My Extension Service
Requires=moode-extmgr.service <injected-dependencies>
After=moode-extmgr.service network.target <injected-dependencies>
PartOf=moode-extmgr.service

[Service]
Type=simple
User=moode-extmgrusr
Group=moode-extmgr
WorkingDirectory=/var/www/extensions/installed/my-extension
ExecStart=/usr/bin/python3 backend/worker.py
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

### Cascade Behavior

| Directive | Effect |
|-----------|--------|
| `Requires=` | Service fails if dependency fails |
| `After=` | Ensures startup order |
| `PartOf=` | **Stopping parent stops all children** - enables graceful memory cleanup |

Stopping `moode-extmgr.service` propagates to all extension services, ensuring proper resource release.

### Tracking

Injected dependencies are recorded in `install-metadata.json` under `services.dependenciesInjected` for audit and clean removal.

## Install Metadata

Each installed extension gets `.ext-mgr/install-metadata.json`:

```json
{
  "installed_at": "2026-03-15T10:30:00Z",
  "packages": ["python3-pip"],
  "services": ["my-extension.service"],
  "symlinks": ["/var/www/my-extension.php"],
  "runtime_links": []
}
```

Used for clean uninstall with shared-package guards.
