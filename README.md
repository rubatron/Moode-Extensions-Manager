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
- `scripts/bootstrap-moode.sh`
- `scripts/publish-github.ps1`
- `tests/api-smoke.ps1`
- `ext-mgr.integrity.json`

## Local Development
1. Copy or symlink these files into a moOde test instance under `/var/www/extensions/`.
2. Open `/ext-mgr.php` in browser.
3. Use browser devtools to validate API calls and UI state transitions.

## Install On Raspberry Pi (moOde)
Use this flow on a fresh or existing moOde host.

1. Connect to the Pi and install prerequisites.
  - `sudo apt-get update`
  - `sudo apt-get install -y wget php-cli`
2. Fast install without git clone (recommended).
  - `wget -qO- https://raw.githubusercontent.com/rubatron/Moode-Extensions-Manager/main/scripts/bootstrap-moode.sh | sudo bash`
  - ext-mgr only: `wget -qO- https://raw.githubusercontent.com/rubatron/Moode-Extensions-Manager/main/scripts/bootstrap-moode.sh | sudo bash -s -- --skip-module1`
  - If the repository is private, this URL returns `404` without authentication.
  - Private repo variant (requires a PAT with read access to repository contents):
    - `wget --header="Authorization: Bearer <GITHUB_TOKEN>" -qO- https://raw.githubusercontent.com/rubatron/Moode-Extensions-Manager/main/scripts/bootstrap-moode.sh | sudo bash`
3. Alternative install with git clone.
  - `sudo apt-get install -y git`
  - `git clone https://github.com/rubatron/Moode-Extensions-Manager.git`
  - `cd Moode-Extensions-Manager`
  - `sudo ./install.sh`
4. Open in browser.
  - `http://<pi-ip>/ext-mgr.php`
5. Verify API reachability from the Pi.
  - `curl -s -X POST http://localhost/ext-mgr-api.php -d 'action=list'`

The update mechanism itself does not require git on the target host.
- Provider fetch uses HTTP (`curl`, `wget` fallback, or PHP stream context).
- Update apply uses managed file downloads from release tags.

Troubleshooting bootstrap cache/noexec:
- If you still see `Permission denied` from `/tmp/.../install.sh`, you likely fetched an older cached bootstrap script.
- Force-refresh bootstrap URL:
  - `wget -O- "https://raw.githubusercontent.com/rubatron/Moode-Extensions-Manager/main/scripts/bootstrap-moode.sh?ts=$(date +%s)" | sudo bash`
- Or bypass bootstrap script completely:
  - `tmp=$(mktemp -d) && wget -qO "$tmp/ext-mgr.tgz" "https://codeload.github.com/rubatron/Moode-Extensions-Manager/tar.gz/refs/heads/main" && tar -xzf "$tmp/ext-mgr.tgz" -C "$tmp" && sudo bash "$tmp/Moode-Extensions-Manager-main/install.sh"`

Expected install targets:
- `/var/www/extensions/ext-mgr.php`
- `/var/www/extensions/ext-mgr-api.php`
- `/var/www/extensions/assets/js/ext-mgr.js`
- `/var/www/extensions/ext-mgr.meta.json`
- `/var/www/extensions/registry.json`

Created shortcuts:
- `/var/www/ext-mgr.php`
- `/var/www/ext-mgr-api.php`

## Import An Extension With ext-mgr
Import is centralized through the wizard script and updates the registry automatically.

1. Ensure extension source has a `manifest.json` with `id`, `name`, and `main`.
2. Run import:
  - `sudo ./scripts/ext-mgr-import-wizard.sh /path/to/extension-source`
3. Optional state toggles:
  - Disable: `sudo ./scripts/ext-mgr-import-wizard.sh --disable /path/to/extension-source`
  - Enable: `sudo ./scripts/ext-mgr-import-wizard.sh --enable /path/to/extension-source`

Import side effects:
- Installs to `/var/www/extensions/installed/<extension-id>`.
- Creates canonical symlink `/var/www/<extension-id>.php`.
- Updates `/var/www/extensions/registry.json` with enabled/state/version fields.
- Applies ext-mgr security principal, DB ACL policy, and watchdog service setup.

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

## Repository Tracking Policy
Why `.github`, `.vscode`, and `1. inspiration` are currently committed:
- `.github`: repository automation/instruction files that belong to the project.
- `.vscode`: shared task configuration (`ext-mgr: smoke`) used by contributors.
- `1. inspiration`: intentionally stored reference material for architecture and migration context.

If you want those folders local-only for your own workflow, add them to `.gitignore` and remove them from tracking with:
- `git rm -r --cached .vscode .github "1. inspiration"`
