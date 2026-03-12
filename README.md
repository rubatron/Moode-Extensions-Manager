# ext-mgr Workspace

Lightweight workspace to continue development of the moOde extension manager with a clean split:
- `ext-mgr.php`: page shell and initial bootstrap data
- `ext-mgr-api.php`: JSON API for list, refresh, and pin actions
- `assets/js/ext-mgr.js`: client-side UI/state logic

## Goals
- Keep page rendering and API concerns separated.
- Move interaction logic out of inline scripts.
- Keep migration from current monolithic behavior incremental and low risk.

## Structure
- `ext-mgr.php`
- `ext-mgr-api.php`
- `ext-mgr.meta.json`
- `assets/js/ext-mgr.js`
- `docs/ARCHITECTURE.md`
- `docs/MIGRATION-PLAN.md`
- `scripts/dev-smoke.ps1`
- `scripts/publish-github.ps1`
- `tests/api-smoke.ps1`
- `ext-mgr.integrity.json`

## Local Development
1. Copy or symlink these files into a moOde test instance under `/var/www/extensions/`.
2. Open `/ext-mgr.php` in browser.
3. Use browser devtools to validate API calls and UI state transitions.

## Validation
- Syntax checks:
  - `php -l ext-mgr.php`
  - `php -l ext-mgr-api.php`
- Basic API smoke checks:
  - Run `tests/api-smoke.ps1` with a target base URL.

## Coding Conventions
- Keep API responses JSON with explicit `ok` boolean and `error` message on failures.
- Keep DOM mutation centralized in `assets/js/ext-mgr.js`.
- Keep server file writes atomic where possible.

## Maintenance Model
- Version and owner metadata are stored in `ext-mgr.meta.json`.
- Current version is tracked in `ext-mgr.version` and release/update policy in `ext-mgr.release.json`.
- UI maintenance actions are available in the page:
  - `Check Update` (`action=check_update`)
  - `Run Update` (`action=run_update`)
  - `Repair Installation` (`action=repair`)
- `Check Update` resolves the latest release from the configured provider/repository and channel.
- `Run Update` downloads configured `managedFiles` from the selected release tag and applies them atomically.
- Integrity verification is policy-driven:
  - `signatureVerification=required`: update aborts if manifest fetch or checksum validation fails.
  - `signatureVerification=planned`: checksum validation is attempted; missing manifest degrades with warning.
  - `signatureVerification=disabled`: checksum verification is skipped.
- Integrity manifest path and algorithm are controlled by `integrityManifestPath` and `checksumAlgorithm` in `ext-mgr.release.json`.
- Repair normalizes registry structure and updates maintenance timestamps.

## GitHub Publish
1. Install Git CLI and ensure `git` is available in PATH.
2. Run:
   - `powershell -ExecutionPolicy Bypass -File scripts/publish-github.ps1 -RemoteUrl https://github.com/<owner>/<repo>.git`
3. The script initializes `.git` if needed, commits current changes, configures `origin`, and pushes to the selected branch.
