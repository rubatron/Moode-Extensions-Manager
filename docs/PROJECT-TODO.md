# ext-mgr Project TODO

## In Progress
- [ ] Validate `settingsCardOnly` behavior against real moOde menu rendering rules.
- [ ] Confirm metadata display for at least one installed extension with `info.json`.
- [ ] Validate branch-track update flow using `dev` branch source.
- [ ] Validate installer action modes on Pi: install/uninstall/repair/repair-from-main.

## Next
- [ ] Add API smoke test for `set_settings_card_only`.
- [ ] Add API smoke test for `set_update_advanced` (track/channel/branch).
- [ ] Add UI smoke test checklist for 3 toggles on one extension.
- [ ] Add UI smoke test for collapsible sections defaults and persistence behavior.
- [ ] Add fallback docs for extensions without `info.json`.
- [ ] Add optional `settingsPage` convention to extension author docs.

## Backlog
- [ ] Remove/retire legacy pin API endpoint after compatibility window.
- [ ] Add migration command to normalize old registry records in-place.
- [ ] Add extension health badges (symlink ok, entry exists, info found).
- [ ] Long-term: add certified marketplace view and Store tab in ext-mgr (planning only, no current implementation).

## Notes
- Active registry booleans:
  - `menuVisibility.m`
  - `menuVisibility.library`
  - `settingsCardOnly`
- Compatibility booleans remain:
  - `showInMMenu`
  - `showInLibrary`
