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

## Uninstall

```bash
sudo bash install.sh --uninstall
```

This removes ext-mgr core files, installed extensions under `/var/www/extensions/installed`, ext-mgr runtime/cache/log roots, canonical extension routes, and the `moode-extmgrusr` / `moode-extmgr` security principals. A timestamped uninstall backup is kept under `/var/www/extensions/sys/backup`.

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
- analyze_logs

## Import Contract

- Package must include manifest.json with id, name, main.
- Import destination: /var/www/extensions/installed/<id>
- Canonical link: /var/www/<id>.php
- UI dry-run mode validates package hooks without registry/symlink writes.
- New imports default to hidden in M/Library menus until explicitly enabled.
- Optional manifest hook `ext_mgr.install.packages` installs required OS packages before post-copy setup.
- Optional manifest hook `ext_mgr.install.script` runs under `moode-extmgrusr` with ext-mgr environment variables.
- Optional manifest key `ext_mgr.service.dependencies` declares additional service dependencies to inject into the main extension unit.
- Legacy writes to `/var/www/extensions/<id>` are relocated into `/var/www/extensions/installed/<id>` during staged import.
- Helper/wizard path defaults are controlled by `scripts/ext-mgr-install-vars.json`.
- ext-mgr runtime installs both `moode-extmgr.service` and `moode-extmgr-watchdog.service`.
- Standard extension logs are staged in `/var/www/extensions/installed/<id>/logs`.
- Watchdog-managed global extension logs are written to `/var/www/extensions/sys/logs/extensionslogs/<id>`.
- ext-mgr manager logs are written to `/var/www/extensions/sys/logs/ext-mgr logs`.
- Template kit zip now opens with an `ExtensionTemplate/` root containing `assets/`, `backend/`, `templates/`, `scripts/`, `packages/`, `data/`, and `cache/`.
- Import review now scans declared apt packages, bundled package artifacts, and shipped service units before execution.
- Installed extensions get `.ext-mgr/install-metadata.json` with package, service, link, and runtime metadata.

## Security Highlights

- Atomic JSON writes for state files where possible.
- Extension ID and relative path validation before file operations.
- Privileged symlink repair helper isolated in /usr/local/sbin/ext-mgr-repair-symlink.
- Upload extraction validates archive paths to block path traversal.
- ext-mgr control-plane service runs as `moode-extmgrusr` and publishes heartbeat state for operator visibility.
- Watchdog monitors ext-mgr heartbeat and restarts control-plane service when stale/inactive.

## Service Parenting

- Template kit now includes an extension service unit scaffold.
- Generated extension service uses `Requires=moode-extmgr.service` and `After=moode-extmgr.service`.
- This keeps extension daemons anchored to ext-mgr control-plane lifecycle.

## Log Visualization

- Each extension card now includes an `Open Logs` action.
- ext-mgr options include an `Open ext-mgr Logs` button.
- Log view supports install/system/error streams and includes an `Open Log File` action.
- Log view also provides a compact `Analyze` action (top errors, error-rate, restart events).

## Developer Notes

- Keep UI logic in assets/js/ext-mgr.js and server logic in ext-mgr-api.php.
- Keep moOde shell integration idempotent in install.sh.
- Keep JSON responses in { ok, data|error } shape.

## Deep Architecture

See ARCHITECTURE.md
