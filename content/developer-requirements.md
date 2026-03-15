# Developer Requirements

Use this checklist when creating or importing extensions with the template kit.

## Required Files

- manifest.json
- info.json
- Main entry PHP file (for example template.php)

## Template Kit Layout

- ExtensionTemplate/assets for CSS, JavaScript and images
- ExtensionTemplate/backend for PHP/API helpers
- ExtensionTemplate/templates for HTML fragments or view files
- ExtensionTemplate/scripts for install, repair and uninstall helpers
- ExtensionTemplate/packages for bundled dependency artifacts and extra service units
- ExtensionTemplate/data for persistent extension data
- ExtensionTemplate/cache for temporary runtime artifacts

## ext-mgr Menu Staging

The template kit ships with a hidden-until-ready profile:

- ext_mgr.menuVisibility.m = false
- ext_mgr.menuVisibility.library = false
- ext_mgr.menuVisibility.system = false

Recommended rollout order:

1. Keep settings-card mode enabled while validating the route and install behavior.
2. Set m=true after interaction tests in the M menu.
3. Set library=true after Library UX/content validation.

## Install Hooks

For packages that need extra OS packages or a custom post-copy step, declare them in manifest.json:

```json
{
 "ext_mgr": {
  "install": {
   "packages": ["python3-requests"],
   "script": "scripts/install.sh"
  }
 }
}
```

Rules:

- `ext_mgr.install.packages` is installed by ext-mgr before your install script runs.
- `ext_mgr.install.script` is executed under `moode-extmgrusr`, not as root.
- ext-mgr exports `EXT_MGR_EXTENSION_ROOT`, `EXT_MGR_EXTENSION_DIR`, `EXT_MGR_EXTENSION_ID`, and `EXT_MGR_EXTENSION_CANONICAL_LINK`.
- ext-mgr also exports `EXT_MGR_EXTENSION_PACKAGES_DIR`, which points at the package-runtime symlink managed by ext-mgr.
- Write runtime files under `/var/www/extensions/installed/<id>` only.
- If a legacy install script writes to `/var/www/extensions/<id>`, ext-mgr will relocate that tree into `/var/www/extensions/installed/<id>`.
- Do not write directly into `/var/www/extensions/sys` from an extension package.
- The starter kit ships `scripts/install.sh`, `scripts/repair.sh`, and `scripts/uninstall.sh` as safe defaults.
- During import review, ext-mgr scans declared apt packages, bundled package files, and shipped service units.
- ext-mgr writes install metadata to `/var/www/extensions/installed/<id>/.ext-mgr/install-metadata.json` after import.

## Service Dependency Rules

- If your extension ships a service, declare `ext_mgr.service.name` in manifest.json.
- Optional dependent units can be declared in `ext_mgr.service.dependencies`.
- Service unit must include `Requires=moode-extmgr.service` and `After=moode-extmgr.service`.
- Run extension services as `moode-extmgrusr` unless elevated privileges are strictly required.
- Units staged in `packages/services` are normalized by ext-mgr to run under `moode-extmgrusr:moode-extmgr` and are linked into the main extension service dependency set.

## Logging Rules

- Keep local extension logs under `/var/www/extensions/installed/<id>/logs`.
- ext-mgr watchdog writes global extension logs under `/var/www/extensions/sys/logs/extensionslogs/<id>`.
- ext-mgr manager logs are reserved under `/var/www/extensions/sys/logs/ext-mgr logs`.
- Ensure your extension service writes useful runtime events to `system.log` and failures to `error.log`.

## Route and UI Rules

- Keep canonical route stable (for example /your-extension.php).
- Do not override moOde modal open/close behavior.
- Let ext-mgr own visibility state and menu injection.

## Validation Before Release

- Page loads in moOde shell without PHP warnings.
- Menu visibility follows ext_mgr.menuVisibility values.
- Extension remains functional when visibility is toggled off.
- Run one ext-mgr Import Wizard dry-run before production import to validate hooks safely.
