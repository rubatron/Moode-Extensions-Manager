# ext-mgr Project Handover

## Goal

This document is the detailed transfer guide for handing ext-mgr to a new maintainer.
It describes architecture philosophy, sandbox and ACL model, feature behavior, menu behavior,
API/action flow, and function-level traceability.

For complete function-by-function indexing, see `docs/FUNCTION-CATALOG.md`.

## 1) Philosophy And Design Principles

### 1.1 Sandbox-first truth model

ext-mgr treats `/var/www/extensions/installed/<id>/` as the canonical source of truth.
Files outside the sandbox are either:

- symlinks back into sandbox content
- append-only managed blocks (for paths that cannot be symlinked)
- generated runtime metadata under ext-mgr control

This prevents drift and allows deterministic uninstall/repair.

### 1.2 Registry as index, not authority

`/var/www/extensions/sys/registry.json` is a fast index for UI rendering and lookup.
When registry conflicts with filesystem reality, filesystem + manifest wins.

### 1.3 Deterministic lifecycle

Target lifecycle:

1. upload
2. scan
3. review
4. generate normalized package
5. install
6. write footprint
7. uninstall/repair/sync from footprint + manifest

### 1.4 Security by constrained boundaries

- Web runtime calls API and controlled helpers only.
- Privileged operations are isolated in scripts and helper entrypoints.
- Path and extension ID validation happens before file mutations.

## 2) ACL, Users, Groups, And Runtime Security

### 2.1 Principals

- `moode-extmgrusr`: service/security user for controlled operations
- `moode-extmgr`: security group
- `www-data`: web runtime

### 2.2 ACL and permission bootstrap

Security-relevant shell functions:

- `scripts/ext-mgr-import-wizard.sh:98` `setup_security_principal()`
- `scripts/ext-mgr-import-wizard.sh:119` `grant_database_access()`
- `scripts/ext-mgr-import-wizard.sh:277` `set_extension_permissions()`
- `install.sh:202` `ensure_extmgr_structure_permissions()`
- `install.sh:188` `sync_security_user_groups()`

These functions create/align users/groups, assign ACLs, and enforce permission posture for
runtime paths and metadata.

## 3) Core Features (What the System Does)

### 3.1 Extension inventory and state

- list extensions
- enabled/disabled state
- menu visibility state
- settings-card mode state

Relevant API action handlers:

- `ext-mgr-api.php:5029` list/refresh
- `ext-mgr-api.php:5036` status
- `ext-mgr-api.php:5381` set_enabled
- `ext-mgr-api.php:5644` set_menu_visibility
- `ext-mgr-api.php:5707` set_settings_card_only

### 3.2 Import pipeline and template generation

- template zip generation
- package upload + extraction + validation
- managed ID generation
- import script execution

Relevant handlers/functions:

- `ext-mgr-api.php:4683` action download_extension_template
- `ext-mgr-api.php:4715` action import_extension_upload
- `ext-mgr-api.php:1700` `buildTemplatePackageFiles()`
- `ext-mgr-api.php:2181` `extractZipArchiveSafely()`
- `ext-mgr-api.php:2301` `runImportWizard()`

### 3.3 Repair, remove, cleanup

- symlink repair
- extension uninstall
- whole extensions-folder cleanup (last resort)

Relevant handlers/functions:

- `ext-mgr-api.php:5437` action repair_symlink
- `ext-mgr-api.php:5482` action remove_extension
- `ext-mgr-api.php:5520` action clear_extensions_folder
- `ext-mgr-api.php:3900` `repairExtensionSymlink()`
- `ext-mgr-api.php:4409` `removeExtensionById()`
- `ext-mgr-api.php:4579` `clearExtensionsFolderGracefully()`

### 3.4 Logging and diagnostics

- list/read/download extension logs
- combined log view
- lightweight log analysis

Relevant handlers/functions:

- `ext-mgr-api.php:4884` list_extension_logs
- `ext-mgr-api.php:4910` read_extension_log
- `ext-mgr-api.php:4964` download_extension_log
- `ext-mgr-api.php:5013` analyze_logs
- `ext-mgr-api.php:799` `summarizeLogsForTarget()`
- `ext-mgr-api.php:915` `buildLogAnalysisPayload()`

### 3.5 Updates and release management

- update check
- update apply
- advanced update mode (track/channel/branch/custom base)

Relevant handlers/functions:

- `ext-mgr-api.php:5057` check_update
- `ext-mgr-api.php:5111` run_update
- `ext-mgr-api.php:5265` set_update_advanced
- `ext-mgr-api.php:2839` `resolveRemoteReleaseCandidate()`
- `ext-mgr-api.php:2926` `fetchManagedFilesFromRelease()`
- `ext-mgr-api.php:3125` `applyManagedFiles()`

## 4) Menu System Behavior (How menus/functions work)

Menu injection and visibility are owned by `assets/js/ext-mgr-hover-menu.js`.

### 4.1 Header tab (config tabs row)

- Container is resolved via `document.getElementById('config-tabs')`.
- Manager button render/update:
  - `assets/js/ext-mgr-hover-menu.js:179` `renderHeaderManagerButton()`

### 4.2 Library dropdown

- Container discovery:
  - `assets/js/ext-mgr-hover-menu.js:222` `findLibraryMenuContainer()`
- Existing injection cleanup:
  - `assets/js/ext-mgr-hover-menu.js:226` `removeExistingLibraryInjected()`
- Render pipeline:
  - `assets/js/ext-mgr-hover-menu.js:270` `renderLibraryMenu()`

### 4.3 M menu and system/configure entries

- M menu render:
  - `assets/js/ext-mgr-hover-menu.js:411` `renderMMenu()`
- System menu render:
  - `assets/js/ext-mgr-hover-menu.js:512` `renderSystemMenu()`
- Configure tile render:
  - `assets/js/ext-mgr-hover-menu.js:556` `renderConfigureTile()`
- Menu observer:
  - `assets/js/ext-mgr-hover-menu.js:629` `observeMMenu()`

### 4.4 Manager visibility policy application

- `assets/js/ext-mgr-hover-menu.js:155` `applyManagerVisibility()`

This applies manager-visibility policy separately for header, library, and system surfaces.

## 5) UI Function Flows

Primary manager UI orchestration is in `assets/js/ext-mgr.js`.

Key operations:

- Status/list load:
  - `assets/js/ext-mgr.js:1592` `loadStatusAndList()`
- Refresh:
  - `assets/js/ext-mgr.js:1615` `runRefresh()`
- System resources:
  - `assets/js/ext-mgr.js:1640` `runSystemResources()`
- Clear cache:
  - `assets/js/ext-mgr.js:1690` `runClearCache()`
- Clear extensions folder:
  - `assets/js/ext-mgr.js:1725` `runClearExtensionsFolder()`
- Manager visibility set:
  - `assets/js/ext-mgr.js:1771` `setManagerVisibility()`
- Update check/run:
  - `assets/js/ext-mgr.js:1804` `runCheckUpdate()`
  - `assets/js/ext-mgr.js:1835` `runUpdate()`
- Repair and sync:
  - `assets/js/ext-mgr.js:1860` `runRepair()`
  - `assets/js/ext-mgr.js:1875` `runRegistrySync()`

Log modal/UI is in `assets/js/ext-mgr-logs.js`.

Key operations:

- Modal bootstrap: `assets/js/ext-mgr-logs.js:98` `ensureModal()`
- Load logs: `assets/js/ext-mgr-logs.js:344` `loadLogList()`
- Load content: `assets/js/ext-mgr-logs.js:245` `loadLogContent()`
- Load analysis: `assets/js/ext-mgr-logs.js:207` `loadAnalysis()`

## 6) API Action Routing

Action entrypoints are implemented in `ext-mgr-api.php` as `$action === '...'` handlers.

Complete action-to-line mapping is maintained in `docs/FUNCTION-CATALOG.md` under
"API Action Entry Points".

## 7) Function-Level Traceability

A complete function index with file + line + role hint is provided in:

- `docs/FUNCTION-CATALOG.md`

This includes:

- all top-level PHP functions in `ext-mgr-api.php`
- named JS functions in ext-mgr frontend modules
- shell functions in install/import/cleanup scripts

## 8) Transfer Checklist

Before handover is considered complete:

1. Confirm new maintainer can navigate `docs/MODEL-CONTEXT.md`.
2. Confirm new maintainer can follow one full import action from UI -> API -> shell.
3. Confirm new maintainer can locate any function from `docs/FUNCTION-CATALOG.md`.
4. Confirm new maintainer can execute rollback policy on `main` safely.
5. Confirm new maintainer can validate menu behavior in header, library, M, and configure contexts.

## 9) Recommended First Walkthrough Session

1. Read `docs/MODEL-CONTEXT.md`.
2. Read this handover file.
3. Open `ext-mgr-api.php` and walk action handlers from line 4683 onward.
4. Open `assets/js/ext-mgr-hover-menu.js` and trace menu render functions.
5. Open `scripts/ext-mgr-import-wizard.sh` and review ACL/security bootstrap path.
6. Run a controlled dry-run import and inspect logs.
