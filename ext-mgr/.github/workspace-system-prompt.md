# ext-mgr Workspace System Prompt

You are working in the ext-mgr repository for moOde.

## Project Intent
- ext-mgr is the canonical moOde extension manager.
- Preserve moOde-native UX and routing conventions.
- Favor idempotent, production-safe installer changes.

## Runtime Facts
- UI shell: ext-mgr.php
- API surface: ext-mgr-api.php
- Client behavior: assets/js/ext-mgr.js
- Styling: assets/css/ext-mgr.css
- Installer/integration: install.sh
- Import pipeline: scripts/ext-mgr-import-wizard.sh + action=import_extension_upload

## Integration Contracts
- Canonical routes: /ext-mgr.php and /ext-mgr-api.php
- Imported extension route: /<id>.php symlink
- Installed extension root: /var/www/extensions/installed/<id>
- Registry source of truth: /var/www/extensions/sys/registry.json

## Security + Permissions Expectations
- Use moode-extmgr group and moode-extmgrusr principal for writable runtime assets.
- Keep helper-based privileged ops narrow (ext-mgr-repair-symlink).
- Validate extension IDs and relative paths before filesystem operations.
- Avoid unsafe archive extraction; reject traversal entries.

## Update/Release Expectations
- Respect ext-mgr.release.json managedFiles allowlist.
- Keep update flows atomic and rollback-capable where practical.
- Preserve API response contract: { ok: boolean, data|error }.

## UI/UX Expectations
- Keep controls moOde-consistent (btn, btn-primary, btn-small).
- Avoid custom styles that fight existing moOde palette unless explicitly requested.
- Maintain mobile behavior and avoid fixed-width regressions.

## Current Product Decisions (Memory Snapshot)
- Advanced update source modes: main, dev branch, custom URL.
- Custom URL input is only visible when custom mode is selected.
- New imports default hidden from M and Library menus.
- Template download supports ZipArchive and zip-command fallback.
- Upload extraction includes archive path safety validation.

## Working Style
- Prioritize bugs/security/regressions first in reviews.
- Keep docs consolidated in README.mmd and ARCHITECTURE.md.
- Treat legacy docs as pointers, not canonical content.
