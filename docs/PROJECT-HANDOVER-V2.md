# ext-mgr Project Handover V2

## Intent

This V2 handover provides a complete operational and engineering transfer package for ext-mgr,
focused on:

- sandboxed architecture
- ACL/security model
- menu/rendering behavior
- feature and API operations
- function-level traceability with exact file/line anchors

Reference catalogs:

- `docs/FUNCTION-CATALOG.md`
- `docs/FUNCTION-CATALOG-V2.md`

## 1. Operating Philosophy

### 1.1 Sandboxed context

ext-mgr is built on the principle that extension runtime truth lives in:

`/var/www/extensions/installed/<id>/`

Everything outside this root should be either:

- a managed symlink back into sandbox content
- a constrained, traceable write
- ext-mgr-controlled metadata

This minimizes drift and makes lifecycle operations reproducible.

### 1.2 Deterministic lifecycle over heuristic cleanup

Preferred flow is deterministic:

1. Upload package
2. Validate/sanitize
3. Stage and execute controlled import
4. Persist normalized runtime metadata
5. Operate remove/repair/sync from known state

### 1.3 Principle of constrained privilege

- UI layer does not execute privileged operations directly.
- API orchestrates and validates.
- Shell layer executes constrained operations.
- ACL/group policy is bootstrapped and enforced by setup scripts.

## 2. ACL And Security Model

### 2.1 Security principals

- `moode-extmgrusr` (service/security user)
- `moode-extmgr` (group)
- `www-data` (web runtime)

### 2.2 Critical ACL/security functions

- `scripts/ext-mgr-import-wizard.sh:98` `setup_security_principal()`
- `scripts/ext-mgr-import-wizard.sh:119` `grant_database_access()`
- `scripts/ext-mgr-import-wizard.sh:277` `set_extension_permissions()`
- `install.sh:188` `sync_security_user_groups()`
- `install.sh:202` `ensure_extmgr_structure_permissions()`

### 2.3 Validation boundaries

- Path safety checks for archive extraction and relative paths
- Extension ID sanitization and placeholder handling
- Constrained shell command execution wrappers

Core anchors:

- `ext-mgr-api.php:1568` `sanitizeExtensionId()`
- `ext-mgr-api.php:2118` `isSafeArchiveEntryPath()`
- `ext-mgr-api.php:2181` `extractZipArchiveSafely()`
- `ext-mgr-api.php:3838` `isSafeRelativeSubPath()`

## 3. Feature Map (System Capabilities)

### 3.1 Core manager state

- list/status/refresh
- enable/disable
- menu visibility and settings-card mode

Action anchors:

- `ext-mgr-api.php:5029` (`list` / `refresh`)
- `ext-mgr-api.php:5036` (`status`)
- `ext-mgr-api.php:5381` (`set_enabled`)
- `ext-mgr-api.php:5644` (`set_menu_visibility`)
- `ext-mgr-api.php:5707` (`set_settings_card_only`)

### 3.2 Import and template generation

- template package download
- upload + extraction + import orchestration
- managed ID rewrite path

Action anchors:

- `ext-mgr-api.php:4683` (`download_extension_template`)
- `ext-mgr-api.php:4715` (`import_extension_upload`)

Function anchors:

- `ext-mgr-api.php:1700` `buildTemplatePackageFiles()`
- `ext-mgr-api.php:1651` `updateImportedManifestWithManagedId()`
- `ext-mgr-api.php:2301` `runImportWizard()`

### 3.3 Remove/repair/sync

- remove single extension
- clear extensions folder (last resort)
- symlink repair
- registry/filesystem sync

Action anchors:

- `ext-mgr-api.php:5437` (`repair_symlink`)
- `ext-mgr-api.php:5482` (`remove_extension`)
- `ext-mgr-api.php:5520` (`clear_extensions_folder`)
- `ext-mgr-api.php:5043` (`registry_sync`)

Function anchors:

- `ext-mgr-api.php:3900` `repairExtensionSymlink()`
- `ext-mgr-api.php:4409` `removeExtensionById()`
- `ext-mgr-api.php:4579` `clearExtensionsFolderGracefully()`
- `ext-mgr-api.php:3725` `syncRegistryWithFilesystem()`

### 3.4 Logging and diagnostics

- list/read/download logs
- combined logs
- lightweight analysis metrics

Action anchors:

- `ext-mgr-api.php:4884` (`list_extension_logs`)
- `ext-mgr-api.php:4910` (`read_extension_log`)
- `ext-mgr-api.php:4964` (`download_extension_log`)
- `ext-mgr-api.php:5013` (`analyze_logs`)

Function anchors:

- `ext-mgr-api.php:631` `availableLogsForTarget()`
- `ext-mgr-api.php:583` `buildCombinedLogContent()`
- `ext-mgr-api.php:799` `summarizeLogsForTarget()`
- `ext-mgr-api.php:915` `buildLogAnalysisPayload()`

### 3.5 Update/release controls

Action anchors:

- `ext-mgr-api.php:5057` (`check_update`)
- `ext-mgr-api.php:5111` (`run_update`)
- `ext-mgr-api.php:5265` (`set_update_advanced`)

Function anchors:

- `ext-mgr-api.php:2839` `resolveRemoteReleaseCandidate()`
- `ext-mgr-api.php:2926` `fetchManagedFilesFromRelease()`
- `ext-mgr-api.php:3070` `verifyPayloadsAgainstManifest()`
- `ext-mgr-api.php:3125` `applyManagedFiles()`

## 4. Menu System: How It Works

Owned primarily by `assets/js/ext-mgr-hover-menu.js`.

### 4.1 Header menu button

- `assets/js/ext-mgr-hover-menu.js:179` `renderHeaderManagerButton()`
- Target container: `#config-tabs`

### 4.2 Library menu injection

- `assets/js/ext-mgr-hover-menu.js:222` `findLibraryMenuContainer()`
- `assets/js/ext-mgr-hover-menu.js:226` `removeExistingLibraryInjected()`
- `assets/js/ext-mgr-hover-menu.js:270` `renderLibraryMenu()`

### 4.3 M menu and configure tile

- `assets/js/ext-mgr-hover-menu.js:362` `findMMenuContainer()`
- `assets/js/ext-mgr-hover-menu.js:411` `renderMMenu()`
- `assets/js/ext-mgr-hover-menu.js:556` `renderConfigureTile()`
- `assets/js/ext-mgr-hover-menu.js:629` `observeMMenu()`

### 4.4 Visibility policy binding

- `assets/js/ext-mgr-hover-menu.js:155` `applyManagerVisibility()`

This applies manager visibility policy independently for header/library/system contexts.

## 5. Function-Level Traceability (Complete)

The complete, line-resolved function map is in:

- `docs/FUNCTION-CATALOG-V2.md`

V2 includes:

- file and line location
- function name
- parameter signature extracted from source
- expected return behavior classification
- side-effect classification
- API action map

## 6. What To Read First (for New Maintainer)

1. `docs/MODEL-CONTEXT.md`
2. `docs/PROJECT-HANDOVER.md`
3. `docs/PROJECT-HANDOVER-V2.md` (this file)
4. `docs/FUNCTION-CATALOG-V2.md`

Then walk code in this order:

1. `ext-mgr-api.php` actions from line 4683 onward
2. `assets/js/ext-mgr.js` status/update/remove workflows
3. `assets/js/ext-mgr-hover-menu.js` menu rendering/injection flow
4. `scripts/ext-mgr-import-wizard.sh` security and import execution
5. `install.sh` bootstrap + ACL posture

## 7. Transfer Validation Checklist

- [ ] Can trace one import from UI to shell execution
- [ ] Can explain manager visibility in header/library/system
- [ ] Can locate remove/repair functions from line anchors
- [ ] Can explain ACL/security bootstrap path
- [ ] Can run rollback safely on `main`

## 8. Branch Policy For Handover

- `main`: production
- `dev`: safety/integration

Recommended handover habit:

1. Snapshot `main` to `dev` before high-impact changes
2. Apply/test changes on `main`
3. Revert quickly if runtime breaks
4. Re-apply with isolated commits
