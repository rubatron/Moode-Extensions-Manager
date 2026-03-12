# moOde OS Context For ext-mgr

This file is a project-local context primer for developing ext-mgr against moOde conventions.

## Scope
- Align ext-mgr UX with native moOde pages.
- Keep menu behavior consistent across index page, configure modal, and extension routes.
- Reduce regressions when patching minified template files.

## Page Integration Baseline
- Preferred page rendering flow on moOde:
  1. Load `/var/www/inc/common.php` and session helpers when available.
  2. Set `$section` to the relevant page identifier.
  3. Include `/var/www/header.php`.
  4. Render extension body content.
  5. Include `/var/www/footer.min.php` (or fallback footer).
- ext-mgr should not feel standalone when moOde shell is available.

## Menu Integration Baseline
- Keep canonical routes at root-level for discoverability:
  - `/ext-mgr.php`
  - `/ext-mgr-api.php`
  - `/extensions-manager.php` (compat redirect/shortcut)
  - `/radio-browser.php`
- Library dropdown should include Extensions entry and canonical routes.
- Configure modal should include Extensions tile.
- Top config tabs should include Extensions button when header structure allows it.

## Modal Reliability
- Some extension pages can show backdrop-only modal failures.
- Use guarded JS fallback to force `#configure-modal` open when:
  - clicking configure links (`href="#configure-modal"` or equivalent)
  - opening page with hash `#configure-modal`

## Registry and Extension Visibility
- Source of truth is `/var/www/extensions/registry.json`.
- Keep extension metadata explicit and stable:
  - `id`, `name`, `enabled`, `pinned`, `menuVisibility`
- Visibility toggles should map to actual menu rendering behavior.

## Installer Safety Rules
- Always back up modified system files with timestamp suffix.
- Keep patches idempotent (safe to run multiple times).
- Prefer additive edits and marker-based patching.
- Warn when markers are missing instead of destructive rewrites.

## Local Rapid Development
- Use Docker when local PHP install is unavailable.
- In non-moOde environments, ext-mgr can run fallback shell while preserving API/UI behavior.
- Validate API contract with `tests/api-smoke.ps1`.

## Notes
- Attempt to consume upstream moOde docs for updates when rate limits allow.
- Inspiration snapshots under `1. inspiration/` are treated as implementation references, not strict upstream source of truth.
