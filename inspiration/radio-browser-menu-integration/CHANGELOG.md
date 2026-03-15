# Changelog

All notable changes to this project are documented in this file.

## 2026-03-12

### Module 1: Modal Fix Integration
- Enabled main menu `Configure` behavior for `radio-browser` section in `header.php`.
- Added extension fallback script `radio-browser-modal-fix.js`.
- Included fallback script in `radio-browser.php`.
- Added native installer: `scripts/install-modal-fix.sh`.
- Added optional remote installer: `scripts/install-modal-fix.ps1`.
- Added module documentation set under `docs/module-1-modal-fix/`.

### Module 2: Library Menu Button Integration
- Added `Radio Browser` button to Library dropdown menu in index template.
- Updated button target to canonical route `/radio-browser.php`.
- Added native installer: `scripts/install-module-2-menu-button.sh`.
- Added module documentation set under `docs/module-2-menu-button/`.

### Documentation
- Standardized project docs to English.
- Added module-specific report, validation, and rollback guides.

### Module 3: Extensions Manager Foundation
- Added file-based extension registry approach under `/var/www/extensions/registry.json`.
- Added `Extensions Manager` page with discovered extension list and refresh action.
- Added menu integration entry pointing to `/extensions-manager.php`.
- Added native installer: `scripts/install-module-3-extension-manager.sh`.
- Added module documentation set under `docs/module-3-extension-manager/`.

### Module 3: Route and UX Hardening
- Standardized manager endpoints to canonical routes:
	- `/ext-mgr.php`
	- `/ext-mgr-refresh.php`
	- `/ext-mgr-pin.php`
- Added compatibility redirects from legacy `extensions-manager*.php` routes.
- Added Extensions entry in both top config tabs and Configure modal menu.
- Aligned manager page rendering and include order for reliable modal behavior.

### Installer Orchestration
- Added umbrella module launcher script: `scripts/install-modules.sh`.
- Added module selection bridge in root installer:
	- Interactive menu option `m`
	- CLI flag `--modules` / `-m`
