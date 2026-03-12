# Migration Plan

## Stage 1
- Introduce `ext-mgr.js` and move existing inline behavior there.
- Keep existing endpoints active.

## Stage 2
- Route all manager UI API calls to `ext-mgr-api.php`.
- Keep legacy endpoint redirects for compatibility.

## Stage 3
- Remove dead endpoint code paths after validation window.
- Keep a rollback branch and backup copy of old scripts.

## Validation Gates
- API list/refresh/pin smoke checks pass.
- UI pin state updates without full page refresh.
- No modal/menu regressions in moOde navigation.
