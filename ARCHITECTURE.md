# ext-mgr Architecture

> Scope document for the ext-mgr extension management system.
> Covers current architecture, implementation philosophy, import wizard design,
> data contracts, and the extension template kit.
>
> See `docs/sandbox-import-roadmap.md` for the phased implementation plan.

---

## 1. System Overview

ext-mgr is the extension management control plane for moOde Audio Player — a Raspberry Pi
audio appliance running on Debian/Raspbian under `/var/www/`. It manages the full lifecycle
of community extensions: import, install, configure, enable/disable, repair, and uninstall.

ext-mgr runs as a moOde extension embedded within the moOde web interface. It interoperates with:

- moOde shell templates (`/var/www/header.php`, footer variants, index template)
- extension runtime roots (`/var/www/extensions/installed/*`)
- canonical route surface (`/var/www/<id>.php` per extension)
- moOde systemd service graph (`moode-extmgr.service` as the parent service)
- global extension logs (`/var/www/extensions/sys/logs/extensionslogs/`)

### Primary components

| File | Role |
|------|------|
| `ext-mgr.php` | Page shell — bootstrap data, moOde shell detection, wizard container |
| `assets/js/ext-mgr.js` | Client orchestration — manager actions, wizard state machine, modals |
| `assets/js/ext-mgr-logs.js` | Isolated log viewer module and log-action binding |
| `assets/css/ext-mgr.css` | All ext-mgr styling, layered on moOde base |
| `ext-mgr-api.php` | Action-based API — dispatch, import, install, uninstall, sync, repair, logs |
| `ext-mgr-shell-bridge.php` | Thin PHP helper used by the privileged shell layer |
| `install.sh` | Self-installer for ext-mgr — menu patching, ACL setup, systemd wiring |
| `scripts/ext-mgr-import-wizard.sh` | Privileged import executor — install, registry update, route creation |
| `scripts/ext-mgr-remove-path.sh` | Constrained removal primitive used by uninstall and repair |

---

## 2. Core Philosophy

### The sandbox is the single source of truth

Every installed extension lives under one canonical root:

```
/var/www/extensions/installed/<ext-id>/
```

Anything visible outside that folder is a *reference back* to what lives inside it — never the
other way around. This one principle drives everything: install, uninstall, repair, and sync
all derive their behavior from what is inside the sandbox plus what is recorded in the footprint.

### Three modes of external access

| Mode | Example | Mechanism |
|------|---------|-----------|
| Symlink (OK — normal) | systemd service unit | `packages/services/my.service` → `/etc/systemd/system/my.service` |
| Symlink (WARNING — grey zone) | udev rules, local binaries | `packages/config/udev/99-gpio.rules` → `/etc/udev/rules.d/` |
| Append-only (WARNING — /boot/) | Pi device tree overlays | BEGIN/END guard block in `/boot/config.txt` |
| Direct write (VIOLATION — blocked) | Writing into `/var/www/` outside sandbox | `install.sh` aborts |

### The footprint is the uninstall key

During install, `data/install-footprint.json` is written inside the sandbox. It records every
symlink created, every apt and pip package installed, and every append-only block added to
system files. Uninstall reads this file and removes exactly what is registered. It never guesses.

When the footprint file is absent (legacy situation), uninstall degrades gracefully to a
heuristic fallback — but this is an exception path, not the intended design.

### Policy-aware scanning blocks bad imports before they happen

Every zip uploaded to the import wizard is scanned by `ext_helper.py` before the user can
proceed past step 1. The scanner classifies every path found in the shell scripts against the
path policy table. Violations block progression. Warnings are presented for review. Only a
clean or warning-acknowledged scan can advance to install.

### The registry is an index, not the truth

`/var/www/extensions/sys/registry.json` is a fast-lookup cache derived from installed manifests
and footprints. If the registry disagrees with the filesystem (an extension's manifest or
sandbox root), the filesystem wins. Sync rebuilds the registry from the filesystem, not the
other way around.

### Import, generate, and install are three distinct steps

The current system conflates upload with install. The target architecture separates them:

1. **Upload + scan** — extract zip to staging, run policy scanner, return JSON
2. **Generate** — user edits in wizard, `ext_helper.py` rewrites IDs, builds manifest, packages zip
3. **Install** — execute from the normalized, user-reviewed package

No install can happen before a clean generate. No generate can happen before violations are
resolved. This prevents bad packages from reaching the system.

---

## 3. Repository Layout

```
ext-mgr/
├── ext-mgr.php                    Page shell — moOde shell detection, wizard container
├── ext-mgr-api.php                Action dispatcher — all API endpoints
├── ext-mgr-shell-bridge.php       PHP helper for privileged shell operations
├── ext-mgr.integrity.json         Integrity manifest for managed file verification
├── ext-mgr.meta.json              Operator-facing status and maintenance timestamps
├── ext-mgr.release.json           Release channel and update track configuration
├── ext-mgr.version                Current release version string
├── install.sh                     Self-installer for ext-mgr (menu patch, ACL, systemd)
├── registry.json                  Development/fallback registry (symlink to sys/ at runtime)
│
├── assets/
│   ├── css/
│   │   └── ext-mgr.css            All ext-mgr styles — moOde-aligned + wizard token layer
│   ├── js/
│   │   ├── ext-mgr.js             Client orchestration — manager + wizard + modals + API client
│   │   └── ext-mgr-logs.js        Isolated log viewer
│   └── images/                    ext-mgr own icons
│
├── scripts/
│   ├── ext-mgr-import-wizard.sh   Privileged import executor
│   └── ext-mgr-remove-path.sh     Constrained removal primitive
│
├── content/                       Static content served by ext-mgr
├── docker/                        Local development environment
├── docker-compose.yml
│
├── docs/
│   └── sandbox-import-roadmap.md  Phased implementation plan
│
├── 1. inspiration/
│   ├── moode-oobe-backup/          Reference design from parallel dev session
│   │   ├── WIZARD-ARCHITECTURE.md  Wizard flow, endpoints, security checklist
│   │   ├── instruction-prompt.md   Full implementation philosophy (v3, canonical)
│   │   ├── ext-mgr-import-wizard-plan.md  Scanner classes, step-by-step wizard design
│   │   └── conversation-summary.md Condensed session history
│   ├── template-extension-template/  Starter kit for extension developers
│   └── radio-browser-menu-integration/
│
└── .vscode/
    ├── settings.json
    └── tasks.json
```

---

## 4. moOde Integration

### 4.1 Shell integration

moOde's `header.php` renders the full navigation chrome: top tabs, M-menu (hamburger),
Bootstrap + jQuery + Font Awesome, session management, and user role checks. An extension
that includes `header.php` must render only its own content block — not a second title bar
or branding element.

Contract:

```
moOde shell present  → include header.php + own content block + footer
Standalone / dev     → own full HTML, branding, CDN fonts, standalone body padding
```

The page shell (`ext-mgr.php`) detects this via `$usingMoodeShell` and sets
`data-standalone="true"` on `<body>` only when the moOde shell is absent. CSS then gates
standalone-only body styling behind `body[data-standalone]`.

### 4.2 Canonical routing

- ext-mgr exposes `/ext-mgr.php` and `/ext-mgr-api.php`
- Imported extensions expose `/<id>.php` — a symlink to the installed extension entrypoint
- The symlink is created by `scripts/ext-mgr-import-wizard.sh` and recorded in the footprint

### 4.3 Menu integration

ext-mgr installer patches moOde's index template to add the Extensions top tab and Library
dropdown entry. This is idempotent across repeated installs.

Per-extension menu visibility is configured via `manifest.ext_mgr.menuVisibility`:

- `m` — show in M-menu (hamburger)
- `library` — show in Library dropdown
- `system` — show in System menu (future)

`settingsCardOnly: true` means the extension does not get a full page but shows only a
settings card inside ext-mgr.

`stageProfile` controls whether a newly installed extension is immediately visible
(`visible-by-default`) or hidden until the user activates it (`hidden-by-default`).
Use `hidden-by-default` for extensions that require configuration before use (streaming
credentials, hardware GPIO setup, etc.).

---

## 5. Current Components In Detail

### 5.1 ext-mgr-api.php

The primary server-side endpoint. All client requests go through `?action=<name>` dispatch.

Current action surface:

| action | Purpose |
|--------|---------|
| `import_extension_upload` | Accept zip, extract to staging, trigger install pipeline |
| `remove_extension` | Uninstall extension by ID — calls footprint cleanup or heuristic fallback |
| `toggle_extension` | Enable / disable extension (state + menu visibility) |
| `toggle_visibility` | Toggle menu visibility flag |
| `get_extension_logs` | Return log file content for a given extension ID |
| `clear_extension_logs` | Clear or rotate log files |
| `sync_registry` | Reconcile registry against installed roots |
| `repair_extension` | Restore symlinks and restart service for given ID |
| `download_extension` | Package installed extension as zip for export |
| `get_health` | Return ext-mgr service health status |

**Target evolution (roadmap):** The API should shrink to request dispatch + orchestration.
Deep scan, install, uninstall, and repair rules should move into dedicated service classes:
`ImportScanService`, `ManifestBuildService`, `InstallOrchestrator`, `FootprintService`,
`UninstallService`, `RepairService`, `RegistryIndexService`.

### 5.2 assets/js/ext-mgr.js

Single client orchestration file. Logically contains four modules (may be split later):

- **Manager actions** — enable/disable, delete, repair, toggle visibility per extension card
- **Wizard state machine** — multi-step import wizard, scan-driven prefill, generate/download flow
- **Modal/dialog utilities** — moOde-style destructive-action confirmation dialogs
- **API client** — fetch-based JSON wrapper for all API actions

Key patterns already in place:

- Merged sync behavior (sync drives UI refresh, not page reload)
- Tightened API fallback (fallback only on 404/network, not on API errors)
- Custom moOde-style action modal for destructive confirmations

### 5.3 scripts/ext-mgr-import-wizard.sh

The privileged install executor. Runs as `moode-extmgrusr` via sudo.

Current responsibilities:

- Set correct permissions on staged extension root
- Run optional manifest `install.script` (e.g. `scripts/install.sh`) as controlled subprocess
- Update registry with new extension entry
- Create canonical route symlink `/var/www/<id>.php`
- Pre-stage logging directories
- Record footprint (target — not yet implemented)

**Target evolution:** Consume normalized manifest input only. Write
`data/install-footprint.json` on every successful install. Create symlinks and append-only
blocks exactly as declared. Abort if violations are detected before execution begins.

### 5.4 scripts/ext-mgr-remove-path.sh

A constrained removal primitive. Given a path, it removes it if it is within an allowed
scope, then verifies the remove succeeded. Used by uninstall and repair cleanup flows.
Must not operate outside defined roots — path validation is mandatory.

### 5.5 assets/css/ext-mgr.css

Styling that layers on moOde's Bootstrap base.

Structural layers:

1. `:root` — `--rp-*` CSS custom property token block (accent, backgrounds, glass surfaces, dividers)
2. `body[data-standalone]` — standalone page layout (background, text, padding)
3. `body:not([data-standalone])` — shell mode adjustments only
4. Manager UI classes — all use moOde-native hardcoded values to blend with moOde's own UI
5. Import wizard classes — use `--rp-*` tokens and glass surfaces, visually distinct but harmonious

The wizard CSS (`--rp-*` token layer + `.extmgr-wizard-card`, `.extmgr-review-pane`,
`.extmgr-glass-card`) is already in place. The manager UI classes (extension cards, submenu
bodies, status badges, modal dialogs) keep moOde-native spacing and colors.

---

## 6. Extension Structure

A properly structured extension under the new contract looks like this:

```
/var/www/extensions/installed/<ext-id>/
│
├── manifest.json                  Canonical extension descriptor (see contracts section)
├── info.json                      Human-readable name, description, author, repo URL
├── template.php                   Main extension page (moOde shell aware)
│
├── assets/
│   ├── css/template.css           Extension styles
│   ├── js/template.js             Extension client logic
│   └── images/                    icon.ico | icon.png | icon.svg (max 512 KB)
│
├── backend/
│   ├── api.php                    Extension-private API endpoint
│   └── ext_helper.py              Scanner + register + rewriter + icon helper
│
├── scripts/
│   ├── install.sh                 Extension installer (run once by import wizard)
│   ├── uninstall.sh               Footprint-based uninstaller
│   ├── repair.sh                  Symlink repair from footprint
│   ├── service-runner.sh          Daemon heartbeat wrapper
│   └── <ext-id>.service           Primary systemd unit
│
├── packages/                      ALL ARTIFACTS THAT LIVE IN THE SANDBOX
│   ├── services/   ──symlink──→   /etc/systemd/system/
│   ├── config/
│   │   ├── udev/   ──symlink──→   /etc/udev/rules.d/
│   │   ├── modules/──symlink──→   /etc/modules-load.d/
│   │   ├── lirc/   ──symlink──→   /etc/lirc/
│   │   ├── modprobe/─symlink──→   /etc/modprobe.d/
│   │   └── alsa/   ──symlink──→   /etc/asound.conf.d/ (if exists)
│   ├── bin/        ──symlink──→   /usr/local/bin/
│   ├── lib/        ──symlink──→   /usr/local/lib/
│   ├── pylib/                     pip --target dir (PYTHONPATH set in service unit)
│   ├── webroot/    ──symlink──→   /var/www/extensions/<id>/ (if needed)
│   ├── opt/        ──symlink──→   /opt/<ext-id>/
│   └── data/                      bundled static config artifacts (not runtime)
│
├── data/
│   └── install-footprint.json    Written by install.sh, read by uninstall/repair/sync
│
├── logs/                          Local log storage (linked to global sys logs)
└── cache/                         Runtime cache directory
```

### Extension types

| Type | Auto-detection signals | Characteristics |
|------|----------------------|-----------------|
| `hardware` | udev/gpio/lirc/i2c/dtoverlay in install.sh | systemd service, apt packages, /boot/ appends |
| `streaming_service` | librespot/shairport/raspotify/spotifyd/tidal in install.sh | daemon, credentials config, port exposure |
| `theme` | only assets/, no install.sh | no service, no apt packages, preview screenshot |
| `functionality` | has template.php + backend/ | UI page, optional API, optional service |
| `page` | has template.php, no backend/ | display-only page |
| `other` | none of the above | diagnostics, tools, scripts |

---

## 7. Path Policy Engine

`ext_helper.py` classifies every path found in `install.sh` and `*.service` files against
the policy table. The classification drives the wizard's import decision.

| Severity | Path prefix | Label | Strategy |
|----------|-------------|-------|----------|
| ✓ OK | `/var/www/extensions/installed/<id>/` | managed root | direct write |
| ✓ OK | `/var/www/extensions/sys/` | shared sys root | direct write |
| ✓ OK | `/etc/systemd/system/` | systemd units | symlink from `packages/services/` |
| ✓ OK | `/usr/bin/`, `/usr/sbin/` | system tools | already present, no action |
| ✗ VIOLATION | `/var/www/` (other) | moOde web root | → `packages/webroot/` + symlink |
| ⚠ WARNING | `/etc/udev/rules.d/` | udev rules | → `packages/config/udev/` + symlink |
| ⚠ WARNING | `/etc/modules-load.d/` | kernel modules | → `packages/config/modules/` + symlink |
| ⚠ WARNING | `/etc/modprobe.d/` | modprobe config | → `packages/config/modprobe/` + symlink |
| ⚠ WARNING | `/etc/lirc/` | LIRC config | → `packages/config/lirc/` + symlink |
| ⚠ WARNING | `/etc/asound` | ALSA config | → `packages/config/alsa/` + symlink |
| ⚠ WARNING | `/etc/` (other) | system config | → `packages/config/` + symlink |
| ⚠ WARNING | `/boot/config.txt` | Pi boot config | append-only with BEGIN/END guards |
| ⚠ WARNING | `/boot/` (other) | Pi boot partition | append-only with BEGIN/END guards |
| ⚠ WARNING | `/home/` | user home | use `$ROOT/data/` instead |
| ℹ INFO | `/usr/local/bin/` | local binary | → `packages/bin/` + symlink |
| ℹ INFO | `/usr/local/lib/` | local library | → `packages/lib/` + symlink |
| ℹ INFO | `/var/lib/` | runtime data | → `$ROOT/data/` or symlink |
| ℹ INFO | `/opt/` | optional package | → `packages/opt/` + symlink |
| ℹ INFO | `/tmp/`, `/run/` | transient | no footprint needed |

**Violations block install.** Warnings are presented to the user in the wizard review pane.
The user must acknowledge each warning before proceeding.

### Parser hardening requirements (from known bugs)

- Strip heredoc blocks before analysis — heredoc content generates false positives in apt-parser
- Strip `case...esac` blocks before path scanning — `case` patterns match as paths
- Strip `echo` and `log` comment lines before path scanning — but **preserve** echo-lines
  that contain redirects (`echo 'x' >> /boot/config.txt`) since those are real writes
- Strip single-line comments (`#`) but preserve the text after a `#` comment on a code line

---

## 8. Import Pipeline

### Current flow (to be replaced)

1. User uploads zip to `action=import_extension_upload`
2. PHP extracts to temp dir, reads manifest
3. `scripts/ext-mgr-import-wizard.sh` runs in one shot:
   - sets permissions
   - runs `install.sh` if present
   - updates registry
   - creates route symlink
   - pre-stages logs

Problems with the current flow:

- Upload and install are coupled — no scan-before-install
- No policy validation before files reach the system
- No normalized manifest generation step
- Footprint is not written
- Wizard state is not driven from scan output

### Target flow (per roadmap)

```
Browser                    ext-mgr-api.php         ext_helper.py
   │                              │                       │
   ├─ 1. POST zip ───────────────→│                       │
   │                              ├─ extract to /tmp/ ──→ │
   │                              ├─ scan ──────────────→ scan()
   │                              │ ←── JSON scan result ─┤
   │ ←── wizard step 1 prefill ───┤                       │
   │                              │                       │
   ├─ 2-5. wizard steps ─────────→│ (session state)       │
   │                              │                       │
   ├─ 6. POST generate ──────────→│                       │
   │                              ├─ rewrite ───────────→ rewrite()
   │                              ├─ build manifest.json  │
   │                              ├─ build info.json       │
   │                              ├─ (icon) ────────────→ save-icon()
   │                              ├─ zip tmp/              │
   │ ←── application/zip ─────────┤                       │
   │                              │                       │
   ├─ 7. POST install ───────────→│                       │
   │                              ├─ stage to installed/  │
   │                              ├─ scripts/ext-mgr-import-wizard.sh
   │                              │     ├─ set permissions │
   │                              │     ├─ run install.sh  │
   │                              │     ├─ create symlinks │
   │                              │     ├─ write footprint │
   │                              │     └─ update registry │
   │ ←── install result ──────────┤                       │
```

---

## 9. Import Wizard Design

The import wizard replaces the current single-step upload form. It is a 6-step guided flow
embedded inside ext-mgr.php, using the `.extmgr-wizard-card` and `.extmgr-review-pane` CSS
surface classes already defined.

### Step overview

| Step | Name | Content |
|------|------|---------|
| 0 | Upload | Drop zone, zip validation, scan trigger. Shows policy result after scan. |
| 1 | Metadata | Name, ID (auto-slug, editable), version, type dropdown, icon picker, repo URL |
| 2 | Menu | M / Library / System toggles, settingsCardOnly, stageProfile |
| 3 | Service | Conditional on services detected. Service name, requiresExtMgr, deps, parentService |
| 4 | Packages | apt-packages list, pip-packages list, package artifacts. pkg-register check |
| 5 | Review | Live manifest diff, path audit summary, policy violations, generate + download button |

After download of the generated zip, the user triggers install from the same wizard.

### Step 3 — service configuration

Step 3 is only shown when `scripts/install.sh` or `packages/services/*.service` is detected.
`requiresExtMgr` is always locked to `true` — not a user toggle. Dependencies are pre-filled
from the scan result (3-layer dependency detection).

### JS wizard state machine (target)

```javascript
const STEPS = ['upload', 'metadata', 'menu', 'service', 'packages', 'review'];

class ImportWizard {
  constructor() { this.state = {}; this.currentStep = 0; }

  async upload(file) {
    const fd = new FormData();
    fd.append('zip', file);
    fd.append('action', 'upload');
    const res  = await fetch('/ext-mgr-api.php', { method: 'POST', body: fd });
    const data = await res.json();
    this.state  = { ...this.state, ...data.scan };
    this.render();
  }

  goTo(step) {
    if (!this.validate(this.currentStep)) return;
    this.currentStep = step;
    this.render();
  }

  async generate() {
    const res  = await fetch('/ext-mgr-api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'generate', state: this.state })
    });
    const blob = await res.blob();
    const url  = URL.createObjectURL(blob);
    Object.assign(document.createElement('a'),
      { href: url, download: `${this.state.id}.zip` }).click();
  }
}
```

---

## 10. Data Contracts

### 10.1 Manifest schema (complete)

```json
{
  "id": "my-extension",
  "name": "My Extension",
  "version": "1.0.0",
  "main": "template.php",
  "ext_mgr": {
    "enabled": true,
    "state": "active",
    "type": "functionality",
    "stageProfile": "hidden-by-default",
    "menuVisibility": { "m": false, "library": false, "system": false },
    "settingsCardOnly": false,
    "iconClass": "fa-solid fa-puzzle-piece",
    "service": {
      "name": "my-extension.service",
      "requiresExtMgr": true,
      "parentService": "moode-extmgr.service",
      "dependencies": ["moode-extmgr.service"]
    },
    "logging": {
      "localDir": "logs",
      "globalDir": "/var/www/extensions/sys/logs/extensionslogs/my-extension",
      "files": ["install.log", "system.log", "error.log"]
    },
    "install": {
      "packages": [],
      "pipPackages": [],
      "packageArtifacts": [],
      "script": "scripts/install.sh"
    }
  }
}
```

Validation rules:

- `id` must be `[a-z0-9-]+` and match the sandbox directory name
- `main` must exist inside the sandbox root
- `type` must be one of: `hardware` | `streaming_service` | `theme` | `functionality` | `page` | `other`
- `stageProfile` must be `visible-by-default` or `hidden-by-default`
- `requiresExtMgr` is always `true` for managed service extensions — never a user toggle
- `moode-extmgr.service` must always be first in `dependencies`

### 10.2 Scan output schema

`ext_helper.py scan <root>` returns:

```json
{
  "ext_id": "my-extension",
  "detected_type": "functionality",
  "path_audit": [
    {
      "severity": "violation",
      "path": "/var/www/css/my-extension.css",
      "label": "moOde web root",
      "strategy": "move to packages/webroot/ and symlink",
      "target": "packages/webroot/"
    }
  ],
  "violations": ["..."],
  "warnings":   ["..."],
  "apt_packages": ["libi2c-dev"],
  "pip_packages": ["luma.oled", "RPi.GPIO"],
  "service_units": ["scripts/my-extension.service", "packages/services/helper.service"],
  "package_artifacts": [],
  "icon_candidates": ["assets/images/icon.png"],
  "rewrite_candidates": ["scripts/install.sh", "scripts/my-extension.service", "template.php"],
  "workspace_check": { ... },
  "suggested_manifest": { ... }
}
```

`workspace_check` is populated when a `.vscode/ext-mgr.json` file is found in the package
(see section 12).

### 10.3 Install footprint schema

Written to `data/install-footprint.json` by `scripts/install.sh` on every successful install.

```json
{
  "ext_id": "my-extension",
  "installed_at": "2026-03-15T12:00:00Z",
  "manifest_hash": "sha256:...",
  "sandbox_root": "/var/www/extensions/installed/my-extension",
  "symlinks": [
    {
      "source": "/var/www/extensions/installed/my-extension/packages/services/my-extension.service",
      "target": "/etc/systemd/system/my-extension.service"
    }
  ],
  "append_only": [
    {
      "target": "/boot/config.txt",
      "begin_marker": "# BEGIN my-extension",
      "end_marker": "# END my-extension",
      "lines": ["dtoverlay=i2c-gpio,bus=3"]
    }
  ],
  "apt_packages": ["libi2c-dev"],
  "pip_packages": ["luma.oled"],
  "runtime_dirs": [],
  "generated_files": ["/var/www/my-extension.php"],
  "services": ["my-extension.service"]
}
```

Rules:

- `install.sh` writes it
- `uninstall.sh` reads it — deterministic, no guessing
- `repair.sh` reads it — recreates symlinks and managed blocks
- `sync` reads it — validates index against recorded truth
- Missing footprint → degraded fallback only

### 10.4 Package ownership register

`/var/www/extensions/sys/pkg-register.json`

Records which extension owns which apt/pip package, preventing removal of shared dependencies
during uninstall.

---

## 11. Extension Template Kit

The template kit at `1. inspiration/template-extension-template/` serves as the starter project
that developers clone or download when building a new extension.

### Current template kit contents

```
template-extension-template/
├── manifest.json         Minimal manifest stub (id, name, version, main, ext_mgr block)
├── info.json             Human-readable extension info stub
├── template.php          moOde-shell-aware page with ext-template-* class structure
├── assets/
│   └── css/              Empty — template CSS placeholder
├── scripts/
│   └── install.sh        Install script stub with footprint write pattern
├── data/                 Empty — runtime data dir
└── cache/                Empty — cache dir
```

### Required additions

The template kit must be extended with:

1. **`packages/` directory scaffold** — with empty `services/`, `config/`, `bin/` subdirs and
   README files explaining the symlink contract
2. **`backend/api.php`** — minimal API stub
3. **`backend/ext_helper.py`** — the full helper (symlinked or copied from sys/)
4. **`.vscode/`** — workspace and development tooling (see next section)
5. **`scripts/uninstall.sh`** and **`scripts/repair.sh`** stubs with footprint read patterns

---

## 12. VSCode Workspace In The Template Kit

### The idea

Every extension template should ship with a `.vscode/` folder that serves dual purpose:

1. **Developer convenience** — launch configuration, file associations, recommended extensions,
   PHP formatting settings, SSH remote target presets for the Pi
2. **Import double-check** — a machine-readable `ext-mgr.json` file inside `.vscode/` that
   declares the extension's own identity, which `ext_helper.py` reads at import time and
   cross-references against the actual manifest

### .vscode/ structure in the template kit

```
.vscode/
├── extensions.json        Recommended VS Code extensions for ext-mgr development
├── settings.json          Workspace settings (PHP, formatting, file associations)
├── launch.json            Launch configs (SSH remote, browser preview)
└── ext-mgr.json           ext-mgr workspace descriptor (the double-check file)
```

### ext-mgr.json schema

```json
{
  "declared_id": "my-extension",
  "declared_type": "functionality",
  "declared_entry": "template.php",
  "declared_version": "1.0.0",
  "target_host": "moode.local",
  "install_path": "/var/www/extensions/installed/my-extension",
  "dev_notes": "Optional free-text note for developers"
}
```

All fields are optional but `declared_id`, `declared_type`, and `declared_entry` are the
ones that `ext_helper.py` cross-references.

### Double-check at import

When `ext_helper.py scan <root>` runs, it looks for `.vscode/ext-mgr.json` inside the
extracted zip:

```
if .vscode/ext-mgr.json exists:
    parse declared_id, declared_type, declared_entry
    cross-reference against manifest.json
    cross-reference declared_type against auto-detected type
    add workspace_check block to scan output
```

Cross-reference results flow into the `workspace_check` field in scan output:

```json
"workspace_check": {
  "found": true,
  "declared_id": "my-extension",
  "declared_type": "functionality",
  "declared_entry": "template.php",
  "checks": {
    "id_match":    { "ok": true,  "declared": "my-extension",   "manifest": "my-extension" },
    "type_match":  { "ok": true,  "declared": "functionality",  "detected": "functionality" },
    "entry_match": { "ok": true,  "declared": "template.php",   "manifest": "template.php" }
  },
  "mismatches": []
}
```

When `found` is `false`, the check is skipped with a neutral info note — workspace file is
optional for end-users who did not develop in VS Code.

When mismatches are present, each is surfaced as a warning in the wizard review pane:

```
⚠ Workspace declares ID "old-template-extension" but manifest contains "my-extension"
⚠ Workspace declares type "page" but scanner detected "functionality"
```

These warnings do not block install but are prominently flagged. The intent is to catch
copy-paste errors, rename oversights, and template reuse without ID replacement — before
anything reaches the system.

### Why this matters

- A developer who clones the template and forgets to rename the ID will see the mismatch
  immediately at import time
- A package that declares itself as `hardware` in the workspace but the scanner reads as
  `streaming_service` is worth inspecting before install
- The workspace file itself is metadata the developer maintains during development — at no
  extra cost it becomes a second opinion for the import scanner

### Recommended .vscode/extensions.json

```json
{
  "recommendations": [
    "bmewburn.vscode-intelephense-client",
    "esbenp.prettier-vscode",
    "ms-vscode-remote.remote-ssh",
    "streetsidesoftware.code-spell-checker",
    "bradlc.vscode-tailwindcss"
  ]
}
```

---

## 13. ext_helper.py

The policy-aware scanner, rewriter, and metadata helper. Lives in the extension's
`backend/` folder (or in a shared sys/ location, symlinked into each extension).

### CLI interface

```bash
python3 ext_helper.py scan       <root>                      # Full JSON profile of a zip
python3 ext_helper.py footprint  <root>                      # Print install footprint
python3 ext_helper.py register   <reg.json> <id> <pkg>       # exit 0=new, exit 2=already owned
python3 ext_helper.py unregister <reg.json> <id>             # Returns orphaned packages
python3 ext_helper.py rewrite    <root> <old_id> <new_id>    # Rename ID across all files
python3 ext_helper.py save-icon  <root> <src_file>           # Install .ico/.png/.svg icon
python3 ext_helper.py policy                                  # Print full policy table
```

### Dependency detection — 3 layers

The scanner detects systemd service dependencies for pre-fill in wizard step 3:

1. **Structural** — glob `*.service` files in `scripts/` and `packages/services/`
2. **Semantic** — regex on `systemctl enable`, `After=`, `Requires=` in `install.sh`
3. **Manifest fallback** — existing `ext_mgr.service.dependencies` as baseline

Pre-analysis strips: heredocs, `case...esac` blocks, pure `echo`/`log` lines. Preserves
echo-redirect lines (`echo 'x' >> /path`).

### IconHandler

Accepts `.ico`, `.png`, `.svg` (max 512 KB). Saves as `assets/images/icon.<ext>`.

---

## 14. Shell Layer

### scripts/install.sh (per-extension)

Each extension ships its own `install.sh`. By convention it:

- Installs apt and pip packages (idempotently)
- Creates symlinks for service units and config files (from canonical sandbox paths)
- Appends to `/boot/config.txt` with BEGIN/END guards where needed
- Writes `data/install-footprint.json` at end of a successful run
- Aborts on violations caught by `ext_helper.py policy` validation before execution

### scripts/uninstall.sh (per-extension)

Reads `data/install-footprint.json`. Removes every registered symlink, service, apt/pip
package, and append-only block. Shared packages (tracked in `pkg-register.json`) are spared.

### scripts/repair.sh (per-extension)

Reads `data/install-footprint.json`. Recreates missing symlinks. Restarts service if the
unit is present but inactive.

### scripts/ext-mgr-import-wizard.sh (ext-mgr system)

Privileged import executor for ext-mgr itself. Runs under `moode-extmgrusr`. Sets up the
full extension environment, runs the extension's own `install.sh`, creates route symlink,
updates registry.

### /boot/config.txt append-only convention

```bash
# BEGIN my-extension
dtoverlay=i2c-gpio,bus=3
dtoverlay=spi0-1cs
# END my-extension
```

Footprint stores the content and markers. Uninstall removes the entire block.

---

## 15. Data Model And Interop

### 15.1 Registry (index, not truth)

File: `/var/www/extensions/sys/registry.json`

The registry is a fast-lookup flat file listing all installed extensions. It is rebuilt by
sync from installed manifests and footprints. If registry and filesystem disagree, the
filesystem wins.

Canonical per-extension fields:

- `id`, `name`, `entry`, `path`
- `enabled`, `state`
- `menuVisibility.m`, `menuVisibility.library`
- `settingsCardOnly`, `version`, `versionSource`

### 15.2 Release policy

File: `ext-mgr.release.json`

Controls: `updateTrack` (channel/branch/custom), `channel`, `branch`, `provider`,
`repository`/`customBaseUrl`, managed files allowlist, integrity verification mode.

### 15.3 Metadata

File: `ext-mgr.meta.json`

Operator-facing status and maintenance timestamps.

---

## 16. Rights Model And Security

### 16.1 Principals

| Principal | Role |
|-----------|------|
| `moode-extmgr` | Group for ext-mgr files |
| `moode-extmgrusr` | Service user for privileged install operations |
| `www-data` | Web runtime — read/write ACL granted on specific paths |

### 16.2 File ownership and ACL strategy

- ext-mgr writable state files are assigned to `moode-extmgrusr:moode-extmgr`
- ACLs grant `rwX` to `www-data` where web runtime must write
- `install.sh` enforces directory and file modes idempotently on every run

### 16.3 Privileged operations

- Symlink repair is mediated by `/usr/local/sbin/ext-mgr-repair-symlink`
- sudo grants narrow `NOPASSWD` execution for the helper only
- Direct destructive git or system operations are never called from the web runtime

### 16.4 Import security checklist

- Zip-slip validation: every zip entry must resolve within the staging target directory
- Session ID is UUID, stored in `$_SESSION` only
- Staging temp dirs are cleaned after download or after 30-minute TTL
- Zip max 50 MB, icon upload max 512 KB
- Allowed icon extensions: `.ico`, `.png`, `.svg`
- Manifest `id` is sanitized to `[a-z0-9-]`
- Path audit violations block generate step — no install can be triggered from a violating package

### 16.5 Update integrity

- Managed files are constrained by allowlist in `ext-mgr.release.json`
- Optional integrity manifest verification (required/planned/disabled mode)
- Atomic write and rollback patterns used in managed file apply flow

---

## 17. CSS And Theming

### Contract

- moOde's runtime theme engine injects `--accentxts` CSS variable on `:root`
- ext-mgr defers to this via `--rp-accent: var(--accentxts, #d35400)`
- Fallback values match moOde Midnight (body `#161616`, text `#d6dbe0`)
- Never style bare `body {}` in moOde shell mode
- Only `body[data-standalone]` may apply page background and padding

### Token block (already in ext-mgr.css)

```css
:root {
  --rp-accent:          var(--accentxts, #d35400);
  --rp-bg-page:         #161616;
  --rp-text:            #d6dbe0;
  --rp-glass-bg:        rgba(255, 255, 255, 0.035);
  --rp-glass-border:    rgba(255, 255, 255, 0.08);
  --rp-panel-bg:        linear-gradient(180deg, rgba(255,255,255,0.06), rgba(255,255,255,0.025));
  /* ...plus full semantic surface scale */
}
```

### Scope split

| Surface | Styling approach |
|---------|-----------------|
| ext-mgr manager controls | moOde-native hardcoded values — blends with moOde UI |
| Import wizard cards, review panes | `--rp-*` tokens + `backdrop-filter: blur()` glass |

The wizard CSS classes `.extmgr-wizard-card`, `.extmgr-review-pane`, `.extmgr-glass-card`
are already defined in `assets/css/ext-mgr.css`. Manager UI classes retain original
moOde-aligned hardcoded hex/rgba values.

---

## 18. Operational Guarantees

- `install.sh` (ext-mgr self-installer) is re-runnable and non-destructive to existing
  registry state
- All API responses are JSON with explicit `ok`/`error` shape
- Menu and template patching is idempotent across moOde variants
- ext-mgr service heartbeat is written by `moode-extmgrusr` and surfaced in API health
- Import pipeline separates upload, scan, generate, and install — no install without review
- Uninstall with footprint is deterministic — uninstall without footprint is a fallback only
- Sync rebuilds from filesystem truth, never invents behavior beyond manifest and footprint

---

## 19. Implementation Roadmap Reference

See `docs/sandbox-import-roadmap.md` for the full phased plan.

Phase summary:

1. **Freeze contracts** — manifest, scan output, footprint, path policy, package register schemas
2. **Build scanner** — `ext_helper.py` with `scan`, `policy`, `rewrite`, register commands
3. **Import wizard UI** — 6-step wizard in `ext-mgr.php`, JS state machine, wizard CSS
4. **Footprint-first install** — refactor `scripts/ext-mgr-import-wizard.sh` to emit footprint
5. **Footprint-driven uninstall/repair/sync** — deterministic lifecycle operations
6. **Operational hardening** — dry-run coverage, test fixtures per type, diagnostics

Current status:

- ✅ CSS token foundation (`--rp-*`, `body[data-standalone]`, wizard surface classes)
- ✅ Manager UI moOde-aligned styling
- ✅ Uninstall logging and cleanup tracking
- ✅ Sync reconciliation and merge behaviors
- ✅ moOde-style action modal pattern
- ⬜ `ext_helper.py` scanner/policy/rewrite/register
- ⬜ Scan-only upload endpoint (Phase 2)
- ⬜ 6-step wizard markup and JS state machine (Phase 3)
- ⬜ Footprint write in install script (Phase 4)
- ⬜ Footprint-driven uninstall/repair (Phase 5)
- ⬜ Template kit additions (packages scaffold, uninstall/repair stubs, `.vscode/`) (Phase 3/6)
