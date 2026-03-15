# ext-mgr Model Context

## Purpose

This document is the canonical model context for ext-mgr. It captures architecture, contracts,
runtime assumptions, and active decisions in one place so implementation, review, and debugging
can happen against a shared, stable baseline.

Use this as the first-read context for anyone touching the project.

## Product Definition

ext-mgr is the control plane for moOde extensions. It manages:

- extension import and installation
- extension enable/disable state
- menu visibility in moOde surfaces
- repair and uninstall flows
- logs, diagnostics, and update controls

## Core Invariants

1. Extension root is canonical: `/var/www/extensions/installed/<id>/`.
2. Registry is an index, not the source of truth.
3. Install, uninstall, repair, and sync must converge on manifest + footprint contracts.
4. Path-policy violations block install.
5. Managed services depend on `moode-extmgr.service`.

## Runtime Topology

```text
UI (ext-mgr.php + assets/js/ext-mgr.js)
  -> API (ext-mgr-api.php)
     -> Registry index (/var/www/extensions/sys/registry.json)
     -> Metadata (ext-mgr.meta.json)
     -> Release policy (ext-mgr.release.json)
     -> Privileged executor (scripts/ext-mgr-import-wizard.sh)
        -> Installed roots (/var/www/extensions/installed/<id>/)
        -> Canonical routes (/var/www/<id>.php)
```

## Ownership By Layer

### Frontend

- `ext-mgr.php`: shell page, container, bootstrap
- `assets/js/ext-mgr.js`: manager actions, wizard flow, API client behavior
- `assets/js/ext-mgr-logs.js`: log viewer behavior
- `assets/css/ext-mgr.css`: ext-mgr UI styles and wizard token layer

### API/Orchestration

- `ext-mgr-api.php`: action dispatch, import lifecycle orchestration, registry I/O, log endpoints
- `ext-mgr-shell-bridge.php`: shell command helper boundary

### Shell/Privileged

- `scripts/ext-mgr-import-wizard.sh`: install executor and route wiring
- `scripts/ext-mgr-remove-path.sh`: constrained cleanup primitive
- `install.sh`: ext-mgr self-install and integration patching

## Security Model

### Principals

- `moode-extmgr` (group)
- `moode-extmgrusr` (service user)
- `www-data` (web runtime)

### Controls

- Validate extension IDs and relative paths before file operations.
- Validate archive extraction paths to prevent path traversal.
- Keep privileged operations in dedicated shell helpers.
- Constrain managed files via release/integrity policy.

## Data Contracts

### Manifest Contract (managed extensions)

Minimum critical fields:

- `id`, `name`, `version`, `main`
- `ext_mgr.type` (required)
- `ext_mgr.stageProfile` (required)
- `ext_mgr.menuVisibility`
- `ext_mgr.service` with `requiresExtMgr=true`
- `ext_mgr.logging`
- `ext_mgr.install`

### Install Footprint Contract

Required output after successful install:

- sandbox root
- created symlinks
- append-only blocks
- apt/pip package sets
- generated files and service units

This footprint drives deterministic uninstall and repair.

### Registry Contract

Registry tracks indexable state for UI and operations:

- `id`, `name`, `entry`, `path`
- `enabled`, `state`
- `menuVisibility` and compatibility booleans
- `settingsCardOnly`

## Import Architecture (Target)

1. Upload package to staging.
2. Scan and classify policy impact.
3. Review/edit via wizard.
4. Generate normalized package metadata.
5. Install from normalized package only.
6. Write install footprint.

This split prevents direct upload-to-install behavior from bypassing policy review.

## Current Known State

### Solid

- Menu visibility controls and settings-card mode exist.
- Import executor and cleanup helper scripts exist.
- Uninstall and sync are stronger than earlier ad-hoc versions.
- Detailed architecture and sandbox roadmap docs exist.

### In Progress

- Full scan contract implementation in helper layer.
- Complete staged 6-step import wizard behavior.
- End-to-end footprint-first lifecycle alignment.

## Active Decisions

1. Prefer sandbox-first design over legacy compatibility shims.
2. Require explicit extension type and stage profile.
3. Keep moOde shell integration idempotent.
4. Keep generated templates moOde-aware and safe by default.
5. Keep registry rebuildable from filesystem truth.

## Operational Runbook Anchors

### Primary endpoints

- `/ext-mgr.php`
- `/ext-mgr-api.php`

### Branching

- `main`: production branch
- `dev`: safety/integration branch

### Recovery pattern

If a bad push lands on main:

1. Revert offending commit on `main`.
2. Push revert immediately.
3. Validate runtime and only then re-apply fix.

## Documentation Map

- Core overview: `README.md`
- Deep architecture: `ARCHITECTURE.md`
- Sandbox execution roadmap: `docs/sandbox-import-roadmap.md`
- Project board: `docs/PROJECT.md`
- Legacy phase board: `docs/PROJECT-ROADMAP.md`
- Task tracking: `docs/PROJECT-TODO.md`

## Maintenance Rule

Update this file whenever one of these changes:

- runtime invariants
- import/install lifecycle contracts
- ownership boundaries between PHP/JS/shell
- security assumptions
- release/recovery branch policy

If these are unchanged, do not update this file.
