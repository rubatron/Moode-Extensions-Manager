# ext-mgr API Reference

> Complete API documentation for ext-mgr-api.php
> All endpoints use POST unless noted otherwise

## Base URL

```
/ext-mgr-api.php?action=<action>
```

## Response Format

All responses follow this structure:

```json
{
  "ok": true,
  "data": { ... }
}
```

Or on error:

```json
{
  "ok": false,
  "error": "Error message"
}
```

---

## Extension Management

### list / refresh

List all extensions with their current state.

```
GET /ext-mgr-api.php?action=list
GET /ext-mgr-api.php?action=refresh
```

**Response:**

```json
{
  "ok": true,
  "data": {
    "extensions": [
      {
        "id": "my-extension",
        "name": "My Extension",
        "version": "1.0.0",
        "enabled": true,
        "menu_m": true,
        "menu_library": false,
        "settings_card_only": false,
        "has_service": true,
        "service_running": true
      }
    ]
  }
}
```

### status

Get extension manager status and health.

```
GET /ext-mgr-api.php?action=status
```

### set_enabled

Enable or disable an extension.

```
POST /ext-mgr-api.php?action=set_enabled
Content-Type: application/x-www-form-urlencoded

id=my-extension&enabled=1
```

### remove_extension

Uninstall an extension with full cleanup.

```
POST /ext-mgr-api.php?action=remove_extension

id=my-extension
```

**Cleanup includes:**

- Symlink removal
- Service unit stop/disable/remove
- Uninstall script execution
- Apt package removal (with shared-package guard)
- Backup creation

### repair

Run extension repair script.

```
POST /ext-mgr-api.php?action=repair

id=my-extension
```

### repair_symlink

Fix broken canonical symlink for extension.

```
POST /ext-mgr-api.php?action=repair_symlink

id=my-extension
```

---

## Import Operations

### import_extension_upload

Upload and extract extension ZIP package.

```
POST /ext-mgr-api.php?action=import_extension_upload
Content-Type: multipart/form-data

file=<ZIP file>
```

**Response:**

```json
{
  "ok": true,
  "data": {
    "session_id": "abc123",
    "manifest": {
      "id": "my-extension",
      "name": "My Extension",
      "version": "1.0.0"
    },
    "staging_path": "/var/www/extensions/tmp/abc123"
  }
}
```

### import_extension_scan

Scan uploaded package with ext_helper.py.

```
POST /ext-mgr-api.php?action=import_extension_scan

session_id=abc123
```

**Response:**

```json
{
  "ok": true,
  "data": {
    "scan_results": {
      "files_scanned": 25,
      "patterns_detected": [],
      "warnings": [],
      "moode_components": {
        "header_php": true,
        "footer_php": true
      },
      "services": ["my-extension.service"],
      "packages": ["python3-pip"]
    }
  }
}
```

### import_extension_install

Install scanned package to final location.

```
POST /ext-mgr-api.php?action=import_extension_install

session_id=abc123
name=My Extension
version=1.0.0
menu_m=1
menu_library=0
menu_system=0
settings_card_only=0
```

**Response includes progress stages:**

1. Copying files
2. Installing packages
3. Running install script
4. Setting up services
5. Creating symlinks
6. Updating registry

### download_extension_template

Download starter template ZIP.

```
GET /ext-mgr-api.php?action=download_extension_template
```

Returns ZIP file download.

---

## Menu Visibility

### set_menu_visibility

Toggle M menu or Library menu visibility.

```
POST /ext-mgr-api.php?action=set_menu_visibility

id=my-extension
menu_type=m|library
visible=1
```

### set_settings_card_only

Show extension as settings card only (no menu page).

```
POST /ext-mgr-api.php?action=set_settings_card_only

id=my-extension
enabled=1
```

### set_manager_visibility

Toggle ext-mgr visibility in moOde menus.

```
POST /ext-mgr-api.php?action=set_manager_visibility

visible=1
```

---

## Updates

### check_update

Check for ext-mgr updates.

```
GET /ext-mgr-api.php?action=check_update
```

**Response:**

```json
{
  "ok": true,
  "data": {
    "current_version": "1.2.0",
    "latest_version": "1.3.0",
    "update_available": true,
    "changelog": "..."
  }
}
```

### run_update

Execute self-update.

```
POST /ext-mgr-api.php?action=run_update
```

### set_update_advanced

Configure update source and branch.

```
POST /ext-mgr-api.php?action=set_update_advanced

source=github
branch=main
```

### system_update_hook

Hook called during moOde system updates.

```
POST /ext-mgr-api.php?action=system_update_hook
```

---

## Logs & Analysis

### list_extension_logs

List available log files for extension.

```
GET /ext-mgr-api.php?action=list_extension_logs

id=my-extension
```

### read_extension_log

Read log file content.

```
GET /ext-mgr-api.php?action=read_extension_log

id=my-extension
log_type=install|system|error
lines=100
```

### download_extension_log

Download log file.

```
GET /ext-mgr-api.php?action=download_extension_log

id=my-extension
log_type=install
```

### analyze_logs

Run AI-powered log analysis.

```
GET /ext-mgr-api.php?action=analyze_logs

id=my-extension
```

**Response:**

```json
{
  "ok": true,
  "data": {
    "top_errors": [...],
    "error_rate": "2.3%",
    "restart_events": 5,
    "recommendations": [...]
  }
}
```

---

## Debug Tools

### debug_registry

Dump current registry.json contents.

```
GET /ext-mgr-api.php?action=debug_registry
```

### debug_variables

Dump ext-mgr environment variables.

```
GET /ext-mgr-api.php?action=debug_variables
```

### debug_services

Dump systemd service states for ext-mgr + extensions.

```
GET /ext-mgr-api.php?action=debug_services
```

### debug_api

API health check with diagnostics.

```
GET /ext-mgr-api.php?action=debug_api
```

---

## System Operations

### system_resources

Get system resource info.

```
GET /ext-mgr-api.php?action=system_resources
```

**Response:**

```json
{
  "ok": true,
  "data": {
    "cpu_usage": "15%",
    "memory_usage": "45%",
    "disk_usage": "60%",
    "uptime": "5 days"
  }
}
```

### clear_cache

Clear ext-mgr cache files.

```
POST /ext-mgr-api.php?action=clear_cache
```

### create_backup_snapshot

Create backup snapshot.

```
POST /ext-mgr-api.php?action=create_backup_snapshot
```

### clear_extensions_folder

Remove ALL extensions (nuclear option).

```
POST /ext-mgr-api.php?action=clear_extensions_folder
```

### registry_sync

Force registry synchronization.

```
POST /ext-mgr-api.php?action=registry_sync
```

---

## Variables

### variables

List all ext-mgr variables.

```
GET /ext-mgr-api.php?action=variables
```

### get_variable

Get single variable value.

```
GET /ext-mgr-api.php?action=get_variable

key=my_var
```

### set_variable

Set variable value.

```
POST /ext-mgr-api.php?action=set_variable

key=my_var
value=my_value
```

### delete_variable

Delete variable.

```
POST /ext-mgr-api.php?action=delete_variable

key=my_var
```

---

## Error Codes

| Code | Meaning |
|------|---------|
| `invalid_action` | Unknown action parameter |
| `missing_id` | Extension ID not provided |
| `not_found` | Extension not found |
| `invalid_manifest` | Manifest validation failed |
| `install_failed` | Installation error |
| `permission_denied` | Insufficient privileges |
| `path_traversal` | Security: path traversal blocked |
