# Release Notes

## Release
- Project: Rubatron Radio Browser integration for moOde
- Date: 2026-03-12
- Type: Feature release (Modules 1, 2, and 3)
- Status: Ready for repository integration

## Highlights
- Restored reliable `Configure` modal behavior on `radio-browser.php`.
- Added `Radio Browser` entry in the Library menu with canonical route `/radio-browser.php`.
- Introduced full Extensions Manager with file-based registry and dynamic menu integration.
- Added Extensions access in both:
  - Main `Configure` modal menu (M-menu)
  - Top configuration tabs in header
- Standardized extension manager routes to canonical endpoints:
  - `/ext-mgr.php`
  - `/ext-mgr-refresh.php`
  - `/ext-mgr-pin.php`
- Added compatibility redirects from legacy `extensions-manager*.php` routes.
- Added umbrella installer support through root `install.sh`:
  - Interactive option `m`
  - CLI flag `--modules` / `-m`
  - Delegates module selection to `scripts/install-modules.sh`

## Delivered Modules

### Module 1: Modal Fix
- Scope:
  - Align `Configure` behavior between `index.php` and `radio-browser.php`.
  - Add extension-side modal fallback to prevent backdrop-only failure.
- Installer:
  - `scripts/install-modal-fix.sh`

### Module 2: Library Menu Button
- Scope:
  - Add direct `Radio Browser` action to Library dropdown.
  - Normalize target URL to `/radio-browser.php`.
- Installer:
  - `scripts/install-module-2-menu-button.sh`

### Module 3: Extensions Manager
- Scope:
  - Add file-based extension registry (`registry.json`) and refresh flow.
  - Add manager UI and pin/unpin support.
  - Drive dynamic Library submenu and extension launch entries.
- Installer:
  - `scripts/install-module-3-extension-manager.sh`

## Key Files Added or Updated
- Root:
  - `install.sh`
  - `README.md`
  - `CHANGELOG.md`
  - `RELEASE-NOTES.md`
- Scripts:
  - `scripts/install-modal-fix.sh`
  - `scripts/install-module-2-menu-button.sh`
  - `scripts/install-module-3-extension-manager.sh`
  - `scripts/install-modules.sh`
- Documentation:
  - `docs/GENERAL-REPORT-MODULES-1-2-3.md`
  - `docs/module-1-modal-fix/*`
  - `docs/module-2-menu-button/*`
  - `docs/module-3-extension-manager/*`

## Validation Summary
- Installer syntax checks passed (`bash -n`) for updated scripts.
- PHP syntax checks passed (`php -l`) for updated runtime PHP files on target.
- Live smoke checks passed for:
  - `http://moode1.local/ext-mgr.php`
  - Header and modal menu integration markers
  - Module selector launch via `install.sh --modules`
- Latest umbrella installer smoke test:
  - `install.sh --modules` opens module menu and exits cleanly on `q`.

## Upgrade and Compatibility Notes
- Legacy manager routes remain supported via compatibility redirects.
- Existing registry pin state is preserved across refresh where possible.
- Core changes are applied via idempotent installer patch logic.

## Rollback
- Use module rollback guides:
  - `docs/module-1-modal-fix/ROLLBACK.md`
  - `docs/module-2-menu-button/ROLLBACK.md`
  - `docs/module-3-extension-manager/ROLLBACK.md`

## Ready-to-Merge Checklist (Rubatron Repo)
- [ ] Copy this project into target repository path.
- [ ] Verify executable bits on shell scripts (`chmod +x`).
- [ ] Run local shell syntax checks for installers.
- [ ] Run one target-device smoke install (`--modules` and at least one module path).
- [ ] Confirm `ext-mgr.php` renders and menu entries appear.
- [ ] Commit with a release message and tag.

## Suggested Commit Message
`feat(radio-browser): ship modules 1/2/3 with ext-mgr integration and umbrella installer`

## Suggested Tag
`radio-browser-integration-v1.0.0`
