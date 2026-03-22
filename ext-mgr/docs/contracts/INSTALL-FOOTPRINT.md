# Install Footprint Contract

> Formal specification for extension install metadata.
> Version: 2.0.0 | Updated: 2026-03-22

---

## Overview

When an extension is installed via ext-mgr, the install helper writes a metadata file that tracks everything that was created, modified, or installed. This "footprint" enables:

1. **Clean uninstall** - Remove only what was installed, nothing more
2. **Repair operations** - Restore symlinks, permissions, services
3. **Audit trail** - Track what packages/services belong to which extension
4. **Rollback support** - Future: undo install on failure

---

## File Location

```
/var/www/extensions/installed/<ext-id>/.ext-mgr/install-metadata.json
```

The `.ext-mgr/` directory is hidden and owned by the security principal (moode-extmgrusr:moode-extmgr).

---

## Schema (Version 2)

```json
{
  "schemaVersion": 2,
  "generatedAt": "2026-03-22T14:30:00Z",
  "extensionId": "radio-browser",
  "mainEntry": "template.php",
  "canonicalLink": "/var/www/radio-browser.php",
  "runtime": {
    "user": "moode-extmgrusr",
    "group": "moode-extmgr"
  },
  "packages": {
    "declared": ["php-curl", "php-gd"],
    "installedApt": ["php-curl", "php-gd"],
    "skippedApt": [],
    "bundledFiles": ["packages/extra.deb"],
    "installedBundles": ["packages/extra.deb"]
  },
  "services": {
    "discovered": ["scripts/moode-extmgr-radio-browser.service"],
    "installed": ["moode-extmgr-radio-browser.service"],
    "dependenciesInjected": ["mpd.service"]
  },
  "links": {
    "packageRuntimeLinks": [
      "/var/www/extensions/sys/.ext-mgr/packages/radio-browser",
      "/var/www/extensions/installed/radio-browser/.ext-mgr/packages-runtime"
    ]
  },
  "bootConfig": {
    "lines": ["dtparam=spi=on"],
    "applied": true,
    "requiresReboot": true
  },
  "scripts": {
    "install": true,
    "repair": true,
    "uninstall": true
  }
}
```

---

## Field Reference

### Root Fields

| Field | Type | Description |
|-------|------|-------------|
| `schemaVersion` | number | Schema version for migration (current: 2) |
| `generatedAt` | string | ISO 8601 timestamp when metadata was written |
| `extensionId` | string | Extension identifier (matches manifest.id) |
| `mainEntry` | string | Main PHP file relative to extension root |
| `canonicalLink` | string | Absolute path to canonical symlink in `/var/www/` |

### Runtime

| Field | Type | Description |
|-------|------|-------------|
| `user` | string | Unix user running extension processes |
| `group` | string | Unix group for extension permissions |

### Packages

| Field | Type | Description |
|-------|------|-------------|
| `declared` | string[] | Packages listed in `ext_mgr.install.packages` |
| `installedApt` | string[] | Packages actually installed via apt |
| `skippedApt` | string[] | Packages skipped (already installed or unavailable) |
| `bundledFiles` | string[] | Files found in `packages/` directory |
| `installedBundles` | string[] | Bundled packages that were installed |

### Services

| Field | Type | Description |
|-------|------|-------------|
| `discovered` | string[] | Service unit files found in `scripts/` |
| `installed` | string[] | Service units installed to `/etc/systemd/system/` |
| `dependenciesInjected` | string[] | Dependencies added to service unit files |

### Links

| Field | Type | Description |
|-------|------|-------------|
| `packageRuntimeLinks` | string[] | Symlinks created for package runtime access |

### Boot Config

| Field | Type | Description |
|-------|------|-------------|
| `lines` | string[] | Config lines from `ext_mgr.boot_config` |
| `applied` | boolean | Whether lines were successfully applied |
| `requiresReboot` | boolean | Whether reboot is needed for changes |

### Scripts

| Field | Type | Description |
|-------|------|-------------|
| `install` | boolean | Whether `scripts/install.sh` exists |
| `repair` | boolean | Whether `scripts/repair.sh` exists |
| `uninstall` | boolean | Whether `scripts/uninstall.sh` exists |

---

## Usage

### Reading Metadata (PHP)

```php
function readInstallMetadata($extId) {
    $metadataDir = '/var/www/extensions/installed/' . $extId . '/.ext-mgr';
    $metadataPath = $metadataDir . '/install-metadata.json';

    if (!is_file($metadataPath)) {
        return null;
    }

    $content = file_get_contents($metadataPath);
    $data = json_decode($content, true);

    if (!is_array($data) || ($data['schemaVersion'] ?? 0) < 2) {
        return null; // Unsupported or invalid
    }

    return $data;
}
```

### Uninstall Flow

1. Read install-metadata.json
2. Stop and disable services from `services.installed`
3. Remove service unit files from `/etc/systemd/system/`
4. Remove boot config via `ext-mgr-boot-config.sh remove <ext-id>`
5. Remove canonical symlink from `canonicalLink`
6. Remove package runtime links from `links.packageRuntimeLinks`
7. Remove extension directory
8. Update registry.json

### Repair Flow

1. Read install-metadata.json
2. Verify/recreate canonical symlink
3. Verify/fix permissions for `runtime.user:runtime.group`
4. Verify services are installed and enabled
5. Verify boot config is applied if `bootConfig.applied`

---

## Schema Migrations

### Version 1 → 2

Version 1 had a flat structure without nested objects. Migration:

```python
if schema_version == 1:
    # Wrap flat fields into nested structure
    data['packages'] = {
        'declared': data.pop('declaredPackages', []),
        'installedApt': data.pop('installedAptPackages', []),
        # ... etc
    }
```

### Future Versions

When adding fields:

- Keep backward compatibility
- Use `schemaVersion` to detect old files
- Add migration logic in install-helper.sh

---

## Related Files

| File | Purpose |
|------|---------|
| `ext-mgr-install-helper.sh` | Writes install-metadata.json |
| `ext-mgr-import-wizard.sh` | Calls install-helper during import |
| `ext-mgr-api.php` | Reads metadata for uninstall/repair actions |

---

## Changelog

- **2.0.0** (2026-03-22): Formal specification documenting existing schema
- Schema version 2 introduced with nested objects for packages, services, links
