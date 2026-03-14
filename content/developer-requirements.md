# Developer Requirements

Use this checklist when creating or importing extensions with the template kit.

## Required Files

- manifest.json
- info.json
- Main entry PHP file (for example template.php)

## ext-mgr Menu Staging

The template kit ships with a hidden-until-ready profile:

- ext_mgr.menuVisibility.m = false
- ext_mgr.menuVisibility.library = false
- ext_mgr.menuVisibility.system = false

Recommended rollout order:

1. Set system=true for internal QA and route checks.
2. Set m=true after interaction tests in the M menu.
3. Set library=true after Library UX/content validation.

## Route and UI Rules

- Keep canonical route stable (for example /your-extension.php).
- Do not override moOde modal open/close behavior.
- Let ext-mgr own visibility state and menu injection.

## Validation Before Release

- Page loads in moOde shell without PHP warnings.
- Menu visibility follows ext_mgr.menuVisibility values.
- Extension remains functional when visibility is toggled off.
