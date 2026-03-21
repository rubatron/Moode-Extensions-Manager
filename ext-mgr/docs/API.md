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

## moOde Broker API

These endpoints provide safe wrappers for moOde operations. Extensions should use these instead of direct database/API access to avoid conflicts (database locks, session issues).

All broker endpoints use the `MoodeHelper` library which handles:

- WAL mode and busy_timeout for SQLite
- Retry logic with exponential backoff
- Proper moOde REST API calls

### moode_get_state

Get current playback state (current song + volume).

```
GET /ext-mgr-api.php?action=moode_get_state
```

**Response:**

```json
{
  "ok": true,
  "data": {
    "currentSong": { "file": "...", "artist": "...", "title": "..." },
    "volume": { "vol": 50, "muted": false }
  }
}
```

### moode_playback

Control playback.

```
GET /ext-mgr-api.php?action=moode_playback&cmd=<command>

cmd: play | pause | stop | toggle | next | prev | clear | play_item
item: required for play_item (path or URL)
```

**Examples:**

```
action=moode_playback&cmd=play
action=moode_playback&cmd=play_item&item=RADIO/Radio Paradise.pls
```

### moode_volume

Get or set volume.

```
GET /ext-mgr-api.php?action=moode_volume&cmd=get
GET /ext-mgr-api.php?action=moode_volume&cmd=set&value=50
```

### moode_radio

Manage radio stations.

```
GET /ext-mgr-api.php?action=moode_radio&cmd=<command>

cmd: list | get | add | update | delete | play
```

| Command | Parameters |
|---------|------------|
| `list` | - |
| `get` | `name` |
| `add` | `name`, `url`, `type`, `genre`, `broadcaster`, `language`, `country`, `region`, `bitrate`, `format`, `logo`, `geo_fenced`, `home_page`, `monitor` |
| `update` | `id` + same as add |
| `delete` | `name` |
| `play` | `name` |

**Example - Add station:**

```
action=moode_radio&cmd=add&name=My Station&url=https://stream.example.com/128&genre=Jazz
```

### moode_favorites

Manage favorites.

```
GET /ext-mgr-api.php?action=moode_favorites&cmd=<command>

cmd: list | get_name | set_name | add | mark_radio | unmark_radio
```

| Command | Parameters | Description |
|---------|------------|-------------|
| `list` | - | Get favorite radio stations |
| `get_name` | - | Get favorites playlist name |
| `set_name` | `name` | Set favorites playlist name |
| `add` | `item` | Add item to favorites playlist |
| `mark_radio` | `url` | Mark station as favorite (type='f') |
| `unmark_radio` | `url` | Unmark station as favorite |

### moode_playlist

Manage playlists.

```
GET /ext-mgr-api.php?action=moode_playlist&cmd=<command>

cmd: list | get | create | add_items | delete | save_queue | play
```

| Command | Parameters |
|---------|------------|
| `list` | - |
| `get` | `name` |
| `create` | `name`, `genre` (optional) |
| `add_items` | `name`, `items` (JSON array) |
| `delete` | `name` |
| `save_queue` | `name` |
| `play` | `name` |

### moode_queue

Manage play queue.

```
GET /ext-mgr-api.php?action=moode_queue&cmd=<command>

cmd: get | clear | add | add_next | clear_play | delete | move
```

| Command | Parameters |
|---------|------------|
| `get` | - |
| `clear` | - |
| `add` | `path` |
| `add_next` | `path` |
| `clear_play` | `path` |
| `delete` | `range` |
| `move` | `range`, `newpos` |

### moode_library

Library operations.

```
GET /ext-mgr-api.php?action=moode_library&cmd=<command>

cmd: status | update | ls | search
```

| Command | Parameters |
|---------|------------|
| `status` | - (get library update status) |
| `update` | - (trigger library update) |
| `ls` | `path` (list directory contents) |
| `search` | `tagname`, `query` |

### moode_system

System configuration and control.

```
GET /ext-mgr-api.php?action=moode_system&cmd=<command>

cmd: config | config_tables | get_value | reboot | poweroff
```

| Command | Parameters |
|---------|------------|
| `config` | - (get cfg_system) |
| `config_tables` | `include_radio` (0/1) |
| `get_value` | `param` |
| `reboot` | - |
| `poweroff` | - |

### moode_renderer

Control renderers (Bluetooth, AirPlay, Spotify, etc.).

```
GET /ext-mgr-api.php?action=moode_renderer&cmd=<command>&renderer=<name>

cmd: restart | on | off | receiver_status | receiver_on | receiver_off
renderer: bluetooth | airplay | spotify | pleezer | squeezelite | roonbridge
```

### moode_cdsp

CamillaDSP control.

```
GET /ext-mgr-api.php?action=moode_cdsp&cmd=get
GET /ext-mgr-api.php?action=moode_cdsp&cmd=set&config=<config_name>
```

### moode_audioinfo

Get audio info for tracks/stations.

```
GET /ext-mgr-api.php?action=moode_audioinfo&cmd=station&path=<url>
GET /ext-mgr-api.php?action=moode_audioinfo&cmd=track&path=<file_path>
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
