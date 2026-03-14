# ext-mgr (moOde Extensions Manager)

## Scope
ext-mgr is the canonical extension manager for moOde with:
- extension inventory and state management
- menu visibility controls (M menu + Library menu)
- safe import workflow for extension packages
- self-update and repair operations

## Runtime Map
```
flowchart LR
  UI[ext-mgr.php + ext-mgr.js] --> API[ext-mgr-api.php]
  API --> REG[(registry.json)]
  API --> META[(ext-mgr.meta.json)]
  API --> REL[(ext-mgr.release.json)]
  API --> WIZ[scripts/ext-mgr-import-wizard.sh]
  WIZ --> INST[/var/www/extensions/installed/<id>/]
  WIZ --> LINK[/var/www/<id>.php]
  INST --> MOODE[moOde menus + pages]
```

## Install
```bash
wget -qO- https://raw.githubusercontent.com/rubatron/Moode-Extensions-Manager/main/scripts/bootstrap-moode.sh | sudo bash
```

## Core Endpoints
- /ext-mgr.php
- /ext-mgr-api.php

## API Actions (selected)
- status, list, refresh
- import_extension_upload
- download_extension_template
- set_enabled
- set_menu_visibility
- set_settings_card_only
- repair_symlink
- check_update, run_update, set_update_advanced

## Import Contract
- Package must include manifest.json with id, name, main.
- Import destination: /var/www/extensions/installed/<id>
- Canonical link: /var/www/<id>.php
- New imports default to hidden in M/Library menus until explicitly enabled.

## Security Highlights
- Atomic JSON writes for state files where possible.
- Extension ID and relative path validation before file operations.
- Privileged symlink repair helper isolated in /usr/local/sbin/ext-mgr-repair-symlink.
- Upload extraction validates archive paths to block path traversal.

## Developer Notes
- Keep UI logic in assets/js/ext-mgr.js and server logic in ext-mgr-api.php.
- Keep moOde shell integration idempotent in install.sh.
- Keep JSON responses in { ok, data|error } shape.

## Deep Architecture
See ARCHITECTURE.md
