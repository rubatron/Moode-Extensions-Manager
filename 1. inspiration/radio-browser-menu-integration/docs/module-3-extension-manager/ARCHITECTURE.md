# Module 3 Architecture: Flexible Extension Management

## Decision: File-based registry first (recommended)

For this stage, a file-based registry is cleaner than adding a new SQLite table.

Why file-based now:
- Lower coupling with moOde core database schema
- Easier deployment and rollback
- Extension metadata lives with extension system (`/var/www/extensions`)
- No migration risk if moOde updates internal DB structure

When SQLite can be added later:
- Need advanced querying/filtering/sorting
- Need transactional writes from multiple management flows
- Need historical/event tracking

## Module 3 Scope
- Add `Extensions` entry in Library dropdown menu (index view)
- Provide an `Extensions Manager` page that lists installed extensions
- Provide `Refresh` action to rescan installed plugins and rebuild registry
- Keep links canonical and simple (e.g. `/radio-browser.php`)

## Storage
- Registry file: `/var/www/extensions/registry.json`
- Generated from: `/var/www/extensions/installed/*`

## Endpoints / Pages
- `/extensions-manager.php` (manager page)
- `/extensions-manager-refresh.php` (refresh action)

## Initial Data Model (JSON)
```json
{
  "generatedAt": "2026-03-12T00:00:00Z",
  "extensions": [
    {
      "id": "radio-browser",
      "name": "Radio Browser",
      "entry": "/radio-browser.php",
      "source": "/var/www/extensions/installed/radio-browser",
      "enabled": true
    }
  ]
}
```
