# Architecture

## Target Split
- `ext-mgr.php`: page and bootstrap only.
- `ext-mgr-api.php`: API actions (`list`, `refresh`, `pin`) returning JSON.
- `assets/js/ext-mgr.js`: all UI behavior and state.

## Why This Split
- Clear separation of concerns.
- Easier testing per layer.
- Lower regression risk when adding features.

## Next Steps
- Add action-level auth checks to API endpoint.
- Add schema validation for `registry.json` writes.
- Add UI tests for pin and refresh flows.
