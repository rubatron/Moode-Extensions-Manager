# ext-mgr Copilot Instructions (moOde)

## Primary Goal
Build and maintain ext-mgr as a first-class moOde extension manager that matches native moOde UI/UX and menu behavior.

## moOde Integration Rules
- Prefer moOde shell includes over standalone markup:
  - Include `/var/www/header.php` when available.
  - Include `/var/www/footer.min.php` (fallback to `/var/www/footer.php`).
- Keep page section aligned with menus:
  - Use `$section = 'extensions'` for ext-mgr.
  - Ensure radio-browser integrations recognize `$section == 'radio-browser'` where required.
- Preserve canonical extension routes:
  - Use root shortcuts like `/ext-mgr.php`, `/ext-mgr-api.php`, `/radio-browser.php`.
  - Avoid hard-coded deep links to `/extensions/installed/<id>/...` unless required.

## Menu + Modal Expectations
- Configure modal should open reliably from extension pages.
- Library dropdown should expose Extensions and canonical routes.
- Top config tabs should include `Extensions` entry when running on moOde shell.
- Any menu patch must be idempotent and safe across moOde template variants.

## UI/UX Conventions
- Follow moOde visual language and existing classes (`container`, `fieldset`, `btn`, etc.).
- Keep custom CSS scoped and additive; do not override global moOde styles aggressively.
- Keep mobile behavior intact; avoid fixed widths that break narrow layouts.

## Backend/API Conventions
- Keep API responses JSON with explicit `ok` and `error` fields.
- Keep registry and metadata writes atomic where possible.
- Validate extension IDs and file paths before filesystem operations.

## Installer Conventions
- `install.sh` should be production-safe and re-runnable (idempotent).
- Always create backups before patching moOde system files (`header.php`, templates, footer).
- Do not destroy existing registry state when upgrading.
- If a patch marker is missing, warn clearly and continue with non-destructive behavior when possible.

## Local Development
- Use Docker for rapid local iteration when PHP is unavailable.
- Ensure ext-mgr remains functional in fallback mode when moOde includes are missing.
- Keep smoke tests runnable via `tests/api-smoke.ps1`.

## Reference Material
- `1. inspiration/radio-browser/`
- `1. inspiration/radio-browser-menu-integration/`
- `docs/MOODE-OS-CONTEXT.md`
