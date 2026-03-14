# ext-mgr Roadmap

## Goal
Ship ext-mgr as the canonical control plane for moOde extensions with reliable menu visibility, settings-only cards, and metadata-driven UX.

## Scope
- Replace legacy pin-first UX with explicit visibility controls.
- Support per-extension UI mode with a third toggle: `settingsCardOnly`.
- Read extension metadata from packaged `info.json` and show it in ext-mgr.
- Keep registry schema backward-compatible while adding new properties.

## Phases

### Phase 1: Visibility Foundation (done)
- Add independent toggles for:
  - M menu visibility
  - Library menu visibility
- Keep compatibility fields:
  - `showInMMenu`
  - `showInLibrary`

### Phase 2: Settings Card Mode (in progress)
- Add registry property:
  - `settingsCardOnly` (boolean)
- Add API action to update this property.
- Add UI toggle and visual status.
- Render settings subcard when enabled.

### Phase 3: Metadata-driven Extension Cards (in progress)
- Read extension metadata from:
  - `/var/www/extensions/installed/<id>/info.json`
  - fallback known info filenames
- Display under each extension:
  - version
  - author
  - license
  - description
- Resolve settings page URL from info file or fallback entry path.

### Phase 4: Menu Integration Refinement
- Ensure menu builders consume new visibility model only.
- Add optional mode where `settingsCardOnly=true` prevents non-settings placements.
- Add clear migration notes for existing installs.

### Phase 5: Operational Hardening
- Add smoke tests for:
  - all 3 toggles
  - metadata loading
  - missing info.json fallback
- Add API regression checks for read-only registry scenarios.

## Registry Model Target
```json
{
  "id": "radio-browser",
  "name": "Radio Browser",
  "entry": "/radio-browser.php",
  "enabled": true,
  "state": "active",
  "menuVisibility": {
    "m": true,
    "library": true
  },
  "showInMMenu": true,
  "showInLibrary": true,
  "settingsCardOnly": false
}
```

## Definition of Done
- All three toggles are writable and persistent.
- ext-mgr UI clearly reflects toggle state by color and label.
- Extension metadata appears per card when info exists.
- Missing info files degrade gracefully without API errors.
- Installer/import flow writes sane defaults for new schema fields.
