# ext-mgr Sandbox Import Roadmap

## Status

This document translates the sandbox-first architecture from the inspiration notes into an implementation roadmap for the current ext-mgr repository.

The goal is not to extend the current ad-hoc import flow. The goal is to replace it with a single robust model where:

- the extension root is the only source of truth
- scan, review, install, uninstall, repair, and sync all use the same contracts
- uninstall never guesses because it reads the install footprint
- illegal writes outside the sandbox are blocked, not tolerated

## Decisions Locked In

These decisions are explicit and should be treated as non-negotiable for this roadmap.

1. No legacy-extension compatibility layer is required.
2. New packages must follow the new ext-mgr contract.
3. `ext_mgr.type` is required.
4. `ext_mgr.stageProfile` is required.
5. `/var/www/extensions/installed/<id>` is the canonical extension root.
6. Files outside the sandbox may only exist as:
   - symlinks back into the sandbox
   - append-only managed blocks with markers
   - generated ext-mgr runtime metadata
7. Direct writes into arbitrary `/var/www/` targets are violations and abort install.
8. Uninstall, repair, and sync are driven by manifest plus footprint, not by heuristics.

## Current Repo Assessment

The current repo already contains useful pieces of the target architecture, but they are still fragmented.

### Already Reusable

- `ext-mgr-api.php` already owns upload, action dispatch, sync, uninstall, and log APIs.
- `scripts/ext-mgr-import-wizard.sh` already owns privilege-sensitive install steps, permissions, registry update, route creation, and log bootstrap.
- `scripts/ext-mgr-remove-path.sh` is already a good low-level removal helper.
- `assets/js/ext-mgr.js` already has modal UX, upload handling, and manager action plumbing.
- Uninstall logging, cleanup tracking, sync reconciliation, and manager log flows are already stronger than before.

### What Is Missing

- No canonical scan output contract.
- No formal path-policy engine.
- No authoritative `install-footprint.json` contract written during install and read during uninstall.
- No clear split between `scan`, `generate package metadata`, and `execute install`.
- Import logic still infers too much in PHP and shell without a shared typed model.
- Registry is still too important. In the target model it becomes a cache/index, not the source of truth.

### Conclusion

Yes: much of the intelligence is already present in PHP, JS, and shell helpers.

No: it is not yet arranged as the robust architecture from the inspiration docs. The next step is a structural refactor, not another round of patches on the current flow.

## Target Architecture

The target flow is:

1. Upload zip to a staging area.
2. Scan staging root with a policy-aware helper.
3. Produce a normalized scan result.
4. Drive a 6-step wizard from that result.
5. Generate a normalized manifest and rewritten package content.
6. Install from that normalized package only.
7. Write an install footprint during install.
8. Use that footprint for uninstall, repair, and sync.

### Source Of Truth

At runtime, the source of truth becomes:

- extension files under `/var/www/extensions/installed/<id>/`
- `manifest.json` inside that root
- `data/install-footprint.json` inside that root

The registry remains useful, but only as an index for fast listing and UI state.

## Canonical Data Contracts

### 1. Manifest Contract

The import wizard should only install packages that end with a normalized manifest.

Minimum required fields:

```json
{
  "id": "example-extension",
  "name": "Example Extension",
  "version": "1.0.0",
  "main": "template.php",
  "ext_mgr": {
    "type": "functionality",
    "stageProfile": "hidden-by-default",
    "enabled": true,
    "state": "active",
    "menuVisibility": {
      "m": false,
      "library": false,
      "system": false
    },
    "settingsCardOnly": false,
    "service": {
      "name": "example-extension.service",
      "requiresExtMgr": true,
      "parentService": "moode-extmgr.service",
      "dependencies": ["moode-extmgr.service"]
    },
    "logging": {
      "localDir": "logs",
      "globalDir": "/var/www/extensions/sys/logs/extensionslogs/example-extension",
      "files": ["install.log", "system.log", "error.log"]
    },
    "install": {
      "packages": [],
      "pipPackages": [],
      "packageArtifacts": [],
      "script": "scripts/install.sh"
    }
  }
}
```

Required validation rules:

- `id` must be a slug and match the install root.
- `main` must exist inside the sandbox.
- `type` must be one of `hardware`, `streaming_service`, `theme`, `functionality`, `page`, `other`.
- `stageProfile` must be `visible-by-default` or `hidden-by-default`.
- `requiresExtMgr` is always `true` for managed services.

### 2. Scan Output Contract

The scan helper should return a single JSON object that the PHP API and JS wizard both consume.

Core shape:

```json
{
  "extId": "example-extension",
  "detectedType": "functionality",
  "pathAudit": [],
  "violations": [],
  "warnings": [],
  "aptPackages": [],
  "pipPackages": [],
  "serviceUnits": [],
  "packageArtifacts": [],
  "iconCandidates": [],
  "rewriteCandidates": [],
  "suggestedManifest": {}
}
```

The important point is not the exact key names. The important point is that the API, wizard UI, and install executor all agree on one scan schema.

### 3. Install Footprint Contract

`data/install-footprint.json` becomes mandatory after install.

Core shape:

```json
{
  "extId": "example-extension",
  "installedAt": "2026-03-15T12:00:00Z",
  "manifestHash": "...",
  "sandboxRoot": "/var/www/extensions/installed/example-extension",
  "symlinks": [
    {
      "source": "/var/www/extensions/installed/example-extension/packages/services/example-extension.service",
      "target": "/etc/systemd/system/example-extension.service"
    }
  ],
  "appendOnly": [
    {
      "target": "/boot/config.txt",
      "beginMarker": "# BEGIN example-extension",
      "endMarker": "# END example-extension"
    }
  ],
  "aptPackages": [],
  "pipPackages": [],
  "runtimeDirs": [],
  "generatedFiles": [
    "/var/www/example-extension.php"
  ],
  "services": [
    "example-extension.service"
  ]
}
```

Rules:

- install writes it
- uninstall reads it
- repair reads it
- sync may verify it
- no destructive cleanup should rely on guesswork when this file exists

### 4. Package Ownership Register

The inspiration notes are right to separate package ownership from per-extension state.

Keep a global ownership index such as `/var/www/extensions/sys/pkg-register.json`.

Purpose:

- record which extension owns which apt or pip package
- avoid removing shared packages during uninstall
- allow repair and diagnostics to explain why a package still exists

## Component Boundaries

The current flat file layout can stay, but responsibilities need to be separated more clearly.

### Frontend

`ext-mgr.php`

- remains the shell page
- hosts the wizard container and manager actions
- owns the shell-vs-standalone body attribute contract
- should not carry import decision logic

`assets/js/ext-mgr.js`

- should be split logically into:
  - manager actions
  - wizard state machine
  - modal/dialog utilities
  - API client

The split can remain in one file initially, but the roadmap should treat those as separate modules.

`assets/css/ext-mgr.css`

- should own the import wizard look-and-feel inside ext-mgr
- should expose a wizard-specific token layer using `--rp-*` names
- should defer accent color to `var(--accentxts, #d35400)` instead of hardcoded accent colors
- should avoid overriding moOde shell body styles in shell mode

### Wizard Presentation And Theme Integration

The import wizard does not need a separate visual system from ext-mgr. It should look like the best, most polished part of ext-mgr and inherit the same control-plane identity.

The key CSS decisions from the inspiration notes should be adopted directly:

- all new wizard-facing colors use `--rp-*` tokens
- `--rp-accent` defers to `var(--accentxts, #d35400)`
- fallback page and text colors match moOde Midnight defaults
- standalone-only body styling is gated behind `body[data-standalone]`
- glass effects use blur plus rgba layers, not absolute theme-specific background assumptions

Minimum token set for the wizard shell:

```css
:root {
  --rp-accent: var(--accentxts, #d35400);
  --rp-bg-page: #161616;
  --rp-text: #d6dbe0;
  --rp-glass-bg: rgba(255, 255, 255, 0.035);
  --rp-glass-border: rgba(255, 255, 255, 0.08);
}
```

Hard rules:

- never style bare `body` for shell mode
- only use `body[data-standalone]` for standalone background and page padding
- wizard cards, step rails, review panes, and previews should all sit inside ext-mgr containers instead of introducing a second layout system
- ext-mgr manager controls and import wizard controls should share button, spacing, status, and panel semantics

This means the remaining UI work is mostly not inventing a new brand. It is integrating the wizard into ext-mgr with a moOde-safe token layer.

### Backend API

`ext-mgr-api.php`

- remains the action endpoint for now
- should shrink into request dispatch plus orchestration
- should stop containing deep scan/install/uninstall rules inline

Target service boundaries inside PHP:

- `ImportScanService`
- `ManifestBuildService`
- `InstallOrchestrator`
- `FootprintService`
- `UninstallService`
- `RepairService`
- `RegistryIndexService`

These do not need a framework. Plain include files or simple classes are enough.

### Helper Layer

`ext_helper.py` or an equivalent helper should become the policy-aware scanner and rewriter.

Responsibilities:

- scan extracted package contents
- classify paths against policy
- detect apt and pip packages
- detect services
- rewrite template IDs
- save icons
- print policy for diagnostics

### Shell Layer

`scripts/ext-mgr-import-wizard.sh`

- should become an install executor, not a policy engine
- should consume normalized manifest and staged files
- should write the footprint
- should create symlinks and append-only blocks exactly as requested
- should abort on violations detected before execution

`scripts/ext-mgr-remove-path.sh`

- stays as a constrained removal primitive
- remains useful for uninstall and repair cleanup

## Implementation Phases

### Phase 1: Freeze The Contracts

Deliverables:

- finalize manifest schema for new managed packages
- finalize scan output schema
- finalize install footprint schema
- finalize path policy table
- finalize package ownership register schema

Concrete repo work:

- add a new architecture doc section or dedicated schema doc
- define the required `type` and `stageProfile` validation in the API
- add fixture JSON examples under `docs/` or a small `test-fixtures/` folder

Acceptance criteria:

- every later phase can rely on fixed schemas
- no more debate about what install, uninstall, and sync consume

### Phase 2: Build The Scanner And Policy Engine

Deliverables:

- helper command for `scan`
- helper command for `rewrite`
- helper command for `policy`
- helper command for package ownership register operations

Concrete repo work:

- add `ext_helper.py`
- move path classification logic out of PHP and shell into the helper
- make upload API call the scanner after extraction
- return structured scan JSON to the UI

Acceptance criteria:

- a zip can be uploaded and classified without installing it
- violations are explicit and block progression
- warnings are explicit and reviewable in the wizard

### Phase 3: Replace Upload-With-Install With A Real Wizard

Deliverables:

- 6-step wizard UI
- stage-backed wizard session state
- ext-mgr-native wizard shell and step layout
- theme-safe tokenized CSS for all new wizard surfaces
- review step that shows the normalized manifest and policy results
- generate normalized package payload before install

Concrete repo work:

- repurpose current import UI in `ext-mgr.php`
- extend `assets/css/ext-mgr.css` with wizard layout, stepper, glass cards, and tokenized accent usage
- keep wizard styles under ext-mgr namespace rather than creating a separate page stylesheet
- turn `assets/js/ext-mgr.js` import code into a staged flow:
  - upload
  - scan
  - metadata
  - menu
  - service
  - packages
  - review
  - install

Acceptance criteria:

- install is impossible before review passes
- wizard state is derived from scan output plus user edits
- the generated manifest is what gets installed
- wizard renders correctly both inside moOde shell and in standalone mode
- new wizard UI does not require editing moOde core CSS

### Phase 4: Refactor Install To Be Footprint-First

Deliverables:

- shell installer consumes only normalized input
- symlinks are created from declarative install instructions
- append-only writes use begin/end markers
- footprint is written on every successful install

Concrete repo work:

- refactor `scripts/ext-mgr-import-wizard.sh`
- keep existing permission and log bootstrap logic
- remove remaining install-time guesswork where possible

Acceptance criteria:

- successful install always writes `data/install-footprint.json`
- generated route files and services are present in the footprint
- no unmanaged write escapes the sandbox without being recorded

### Phase 5: Make Uninstall, Repair, And Sync Read The Same Truth

Deliverables:

- uninstall uses footprint first
- repair recreates symlinks and managed append-only blocks from footprint
- sync becomes index rebuild plus validation, not heuristic reconciliation

Concrete repo work:

- refactor `removeExtensionById()` in `ext-mgr-api.php`
- add a repair endpoint that uses footprint + manifest
- reduce `syncRegistryWithFilesystem()` to:
  - enumerate installed roots
  - read manifest and footprint
  - rebuild registry index
  - surface mismatches

Acceptance criteria:

- uninstall without footprint is a degraded fallback path only
- uninstall with footprint is deterministic
- sync never invents behavior beyond what manifest and footprint prove

### Phase 6: Harden Operational Guarantees

Deliverables:

- dry-run coverage for scan and install preflight
- test fixture packages for each type
- diagnostics for policy violations, shared package ownership, and footprint drift

Concrete repo work:

- add fixture zips or extracted fixture dirs
- add lightweight validation scripts or tasks
- add log entries that report scan result, footprint write, repair actions, and drift findings

Acceptance criteria:

- hardware, streaming, theme, and functionality packages all have a known test path
- install and uninstall behavior is reproducible across repeated runs

## Recommended Execution Order In This Repo

This is the sequence that makes the least mess in the current codebase.

1. Write and lock schemas.
2. Add `ext_helper.py` with `scan`, `policy`, `rewrite`, and register commands.
3. Change upload API to stage and scan only.
4. Build the wizard state machine in JS.
5. Refactor shell install script to consume normalized manifest and emit footprint.
6. Refactor uninstall and repair around the footprint.
7. Downgrade registry from source of truth to index/cache.

This order matters. If install is refactored before scan and schema are stable, the shell script will end up absorbing policy and UI concerns again.

## What To Keep From Current Code

Keep these parts and build on them:

- current permissions and principal setup in `scripts/ext-mgr-import-wizard.sh`
- current log bootstrap and ext-mgr/global extension logging paths
- current modal UX and destructive-action patterns in `assets/js/ext-mgr.js`
- current manager download and diagnostics/log infrastructure
- current shared-package guard concept during uninstall
- current constrained path removal helper script

## What To Stop Doing

- stop treating registry as the truth of what is installed
- stop mixing scan, infer, rewrite, and install into one step
- stop allowing install behavior that is not representable in manifest plus footprint
- stop letting sync and uninstall rely on best-effort guesses when a footprint exists
- stop tolerating arbitrary `/var/www/` writes from extension install scripts

## First Refactor Targets

If implementation starts immediately, these are the first files to touch:

- `ext-mgr-api.php`: add schema validation gates and split scan/install/uninstall orchestration into isolated functions or includes.
- `scripts/ext-mgr-import-wizard.sh`: convert from mixed policy executor to a manifest-driven installer.
- `assets/js/ext-mgr.js`: replace direct upload-install UX with staged wizard UX.
- `ext-mgr.php`: host the 6-step wizard surface cleanly and set `data-standalone` only when header integration is absent.
- new helper file: implement policy-aware scan and rewrite commands.
- `assets/css/ext-mgr.css`: add shared `--rp-*` tokens and wizard-specific ext-mgr surfaces.

## Definition Of Done

The roadmap is complete when all of the following are true:

- an uploaded zip is scanned before install
- the wizard shows path violations and warnings before install
- install writes an authoritative footprint
- uninstall reads the footprint and removes only what the footprint proves
- repair recreates declared outside-sandbox artifacts from the footprint
- sync rebuilds registry from installed roots instead of guessing
- direct unmanaged writes outside the sandbox are rejected
- wizard UI is visually integrated into ext-mgr and theme-safe inside moOde shell

## Recommended Next Build Slice

The best first architectural implementation slice is:

1. lock schemas
2. implement scanner helper
3. make upload endpoint return scan JSON only

That slice gives a solid seam in the current architecture without forcing the install refactor and wizard rewrite in the same commit series.

The best first user-facing slice after that is:

1. add the ext-mgr-native wizard shell
2. add the `--rp-*` token layer in ext-mgr CSS
3. gate standalone body styling behind `data-standalone`
4. wire the staged scan result into the first wizard step

That is the shortest path to the remaining work you identified: wizard house style plus tight integration with ext-mgr.
