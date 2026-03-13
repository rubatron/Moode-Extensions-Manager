# ext-mgr Architecture

## 1. System Boundaries
ext-mgr runs as a moOde extension-management control plane and interoperates with:
- moOde shell templates (/var/www/header.php, footer variants, index template)
- extension runtime roots (/var/www/extensions/installed/*)
- canonical route surface (/var/www/<id>.php)

Primary components:
- ext-mgr.php: page shell and bootstrap data
- assets/js/ext-mgr.js: client orchestration and state/UI binding
- ext-mgr-api.php: action-based API and file-state orchestration
- install.sh: deployment, patching, symlink helper + ACL/security setup
- scripts/ext-mgr-import-wizard.sh: privileged import and registry update flow

## 2. Integration With moOde
### 2.1 Shell Integration
- Prefer native moOde shell includes when present.
- Keep section bound to extensions context for consistent navigation.
- Installer applies idempotent menu patching for top tabs and library dropdown.

### 2.2 Canonical Routing
- ext-mgr exposes /ext-mgr.php and /ext-mgr-api.php.
- Imported extensions expose /<id>.php symlink to installed extension entrypoint.
- ext-mgr maintains legacy compatibility fields in registry (path/showInMMenu/showInLibrary) while using menuVisibility as canonical shape.

## 3. Data Model And Interop
### 3.1 Registry
File: /var/www/extensions/sys/registry.json

Canonical per-extension fields:
- id, name, entry, path
- enabled, state
- menuVisibility.m, menuVisibility.library
- settingsCardOnly
- version, versionSource

### 3.2 Release Policy
File: ext-mgr.release.json

Controls:
- updateTrack (channel/branch/custom)
- channel, branch, provider, repository/customBaseUrl
- managedFiles allowlist
- integrity verification mode + manifest configuration

### 3.3 Metadata
File: ext-mgr.meta.json

Contains operator-facing status and maintenance timestamps.

## 4. Import Pipeline
1. UI uploads zip package to action=import_extension_upload.
2. API stores upload to temp workspace and performs safe extraction.
3. Import wizard script installs into /var/www/extensions/installed/<id>.
4. Canonical symlink /var/www/<id>.php is created.
5. Registry entry is updated and normalized.
6. New import defaults are enforced (hidden from M/Library until explicitly shown).

Security controls in import pipeline:
- extension ID validation
- relative path validation
- archive path traversal checks before extraction
- constrained privileged actions via dedicated helper

## 5. Rights Model And Security
### 5.1 Principals
- moode-extmgr (group)
- moode-extmgrusr (service user)
- www-data (web runtime)

### 5.2 File Ownership + ACL Strategy
- ext-mgr writable state files are assigned to security principal and group.
- ACLs grant rwX to web runtime where required.
- install.sh enforces directory and file modes idempotently.

### 5.3 Privileged Operations
- Symlink repair is mediated by /usr/local/sbin/ext-mgr-repair-symlink.
- sudoers grants narrow NOPASSWD execution for helper only.
- Direct destructive git/system operations are avoided by runtime API.

### 5.4 Update Integrity
- Managed files are constrained by allowlist.
- Optional integrity manifest verification (planned/required/disabled).
- Atomic write and rollback patterns are used in managed file apply flow.

## 6. Operational Guarantees
- Installer is re-runnable and non-destructive to existing registry state.
- API responses are JSON with explicit ok/error.
- Menu/template patching is intended to be idempotent across variants.

## 7. Known Design Tradeoffs
- Legacy compatibility fields are still produced for downstream consumers.
- Action-level auth is inherited from moOde environment; explicit per-action auth hardening can be added if deployment model changes.
