# Extension Scan Contract

> Formal specification for scan output during Import Wizard.
> Version: 1.0.0 | Updated: 2026-03-22

---

## Overview

The scan process validates an extension package before installation. It runs in two stages:

1. **Python Scanner** (`ext_helper.py scan <dir>`) - analyzes code and paths
2. **PHP Review** (`buildImportPackageReview()`) - analyzes manifest and structure

The combined output is returned to the frontend for display in the Import Wizard.

---

## Python Scanner Output Schema

```json
{
  "ext_id": "string",
  "path_audit": [
    {
      "path": "/var/www/...",
      "severity": "ok|info|warning|violation",
      "label": "string"
    }
  ],
  "violations": [
    { "path": "...", "severity": "violation", "label": "..." }
  ],
  "warnings": [
    { "path": "...", "severity": "warning", "label": "..." }
  ],
  "code_patterns": {
    "findings": [
      {
        "id": "pattern_id",
        "label": "Human readable label",
        "severity": "ok|info|warning|violation|upgradeable",
        "file": "relative/path.php",
        "fix": "Suggested fix description"
      }
    ],
    "by_severity": {
      "violation": [],
      "warning": [],
      "info": [],
      "upgradeable": [],
      "ok": []
    }
  }
}
```

### Severity Levels

| Level | Meaning | UI Behavior |
|-------|---------|-------------|
| `violation` | Blocked path or dangerous pattern | **Blocks install**, red alert |
| `warning` | Potential issue, review recommended | Yellow warning, requires acknowledgment |
| `upgradeable` | Can be auto-fixed by installer | Blue info, shows fix description |
| `info` | Informational finding | Gray note, no action required |
| `ok` | Verified safe pattern | Green checkmark |

### Path Audit Policy

Paths in `install.sh` are classified by prefix:

| Prefix | Severity | Label |
|--------|----------|-------|
| `/var/www/extensions/installed/` | ok | managed root |
| `/var/www/extensions/sys/` | ok | shared sys root |
| `/etc/systemd/system/` | ok | systemd units |
| `/var/www/` | violation | moOde web root |
| `/etc/` | warning | system config |
| `/boot/` | warning | pi boot partition |
| `/usr/local/bin/` | info | local binary |
| `/opt/` | info | optional package |

### Code Pattern Detection

Built-in patterns scanned in `*.php`, `*.sh`, `*.js` files:

| ID | Severity | Description |
|----|----------|-------------|
| `hardcoded_header_suppress` | upgradeable | Old-style `#config-tabs{display:none}` |
| `hardcoded_navbar_suppress` | upgradeable | Navbar hiding in CSS |
| `hardcoded_extension_path` | warning | Hardcoded `/var/www/extensions/installed/...` |
| `unsafe_shell_exec` | warning | `shell_exec($var)` without sanitation |
| `direct_apt_install` | info | `apt install -y` in scripts |
| `rm_rf_dangerous` | warning | `rm -rf $variable` |
| `uses_moode_header` | ok | Integrates moOde header |
| `uses_moode_footer` | ok | Integrates moOde footer |
| `has_dynamic_header_control` | ok | Already uses registry-based header |

---

## PHP Review Output Schema

```json
{
  "manifestPackages": ["php-curl", "php-gd"],
  "bundledPackageFiles": ["packages/foo.deb"],
  "packageFolders": ["packages/runtime"],
  "serviceUnits": ["scripts/moode-extmgr-example.service"],
  "serviceDependencies": ["mpd.service"],
  "installScripts": {
    "install": true,
    "repair": false,
    "uninstall": false
  },
  "templateUpgrade": {
    "needed": true,
    "reason": "Template uses hardcoded header suppression.",
    "path": "template.php"
  },
  "counts": {
    "manifestPackages": 2,
    "bundledPackageFiles": 1,
    "serviceUnits": 1,
    "serviceDependencies": 1
  }
}
```

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `manifestPackages` | string[] | Packages from `ext_mgr.install.packages` |
| `bundledPackageFiles` | string[] | Files in `packages/` directory |
| `packageFolders` | string[] | Subdirectories in `packages/` |
| `serviceUnits` | string[] | `.service` files found in `scripts/` |
| `serviceDependencies` | string[] | From `ext_mgr.service.dependencies` |
| `installScripts` | object | Presence of lifecycle scripts |
| `templateUpgrade` | object\|null | Header upgrade info if needed |
| `counts` | object | Summary counts for UI display |

---

## PHP-Added Warnings

PHP may inject additional warnings into the scan result:

| ID | Condition | Message |
|----|-----------|---------|
| `manifest_main_missing` | `manifest.main` not set | Installer will search for standard entry files |
| `manifest_main_not_found` | `manifest.main` file doesn't exist | Verify file exists in package |

---

## Combined Response to Frontend

The `import_extension_scan` API action returns:

```json
{
  "ok": true,
  "data": {
    "sessionId": "uuid-v4",
    "extensionId": "example-ext",
    "review": { /* PHP review output */ },
    "scan": { /* Python scan output + PHP warnings */ },
    "manifest": { /* Full manifest.json */ },
    "info": { /* info.json if present */ }
  }
}
```

---

## Frontend Display Rules

### Step 1: Upload & Scan

1. Show extension name, version, type from manifest
2. Count violations and warnings
3. If violations > 0: Show red "Cannot Install" with list
4. If warnings > 0: Show yellow banner with acknowledgment checkbox
5. Show upgradeable items as blue info cards

### Step 2: Configuration

1. Pre-fill form from manifest and wizard overrides
2. Show detected packages, services, boot_config
3. Allow user to modify before install

### Step 3: Install

1. Show install progress
2. Display any auto-upgrades applied
3. Show final status (success/failure)

---

## Extending the Scanner

### Custom Patterns (Extension-level)

Extensions can include `ext-mgr-patterns.json` in their root:

```json
{
  "patterns": [
    {
      "id": "custom_check",
      "label": "Custom pattern check",
      "severity": "warning",
      "files": ["*.php"],
      "pattern": "some_regex",
      "fix": "Suggested fix"
    }
  ]
}
```

### Adding Built-in Patterns

Edit `ext_helper.py` or `ext_helper_lite.py`:

```python
CODE_PATTERNS = [
    # ... existing patterns
    {
        "id": "new_pattern_id",
        "label": "Human readable",
        "severity": "warning",
        "files": ["*.php", "*.sh"],
        "pattern": r"regex_pattern",
        "fix": "Suggested fix",
    },
]
```

---

## Changelog

- **1.0.0** (2026-03-22): Initial formal specification
