# ext-mgr Project TODO List

> Master task tracking for ext-mgr development.
> Updated: 2026-03-15

---

## Current Sprint: Import Wizard & Variables System

### 🎯 Goals

1. Complete the Import Wizard as per `docs/sandbox-import-roadmap.md`
2. Implement centralized Variables API
3. Prepare foundation for Resource Monitor

---

## Phase 1: Variables API (Foundation)

### 1.1 PHP Backend

- [x] Create Variables API functions in ext-mgr-api.php
- [x] Read from `ext-mgr-install-vars.json` with defaults
- [x] Implement `action=variables` in ext-mgr-api.php
- [x] Return paths, URIs, security config as JSON
- [x] Implement `set_variable`, `delete_variable`, `get_variable` actions
- [x] Protected keys (paths.*, security.*, uris.*)
- [x] Extension-scoped variables support

### 1.2 JavaScript Integration

- [x] Create `Config` module in ext-mgr.js
- [x] Load variables on page init via API
- [x] Create `VariablesManager` UI module
- [x] Wizard-style UI (Scope → Extension → Edit)
- [x] Expose `ExtMgrConfig` and `ExtMgrVariables` globally
- [ ] Replace all hardcoded paths with `Config.getPath('key')`
- [ ] Update tooltip URLs to use config
- [ ] Update modal confirmation messages

### 1.3 Shell Script Integration

- [ ] Create helper function: `ext_mgr_config_get()`
- [ ] Update ext-mgr-import-wizard.sh to use config getter
- [ ] Update moode-extmgr-watchdog.sh to use config getter
- [ ] Document config access pattern for extension developers

### 1.4 Documentation

- [ ] Document variables schema in ARCHITECTURE.md
- [ ] Add examples for extension developers
- [ ] Create migration guide for hardcoded references

---

## Phase 2: Import Wizard Completion

### 2.1 Scan Contract

- [ ] Define formal scan output JSON schema
- [ ] Update ext_helper.py to output standardized format
- [ ] Update PHP scan handler to validate output
- [ ] Document scan contract in ARCHITECTURE.md

### 2.2 Path Policy Enforcement

- [ ] PHP: Parse ext_helper.py violations/warnings
- [ ] PHP: Block install on violations
- [ ] JS: Display violations in wizard Step 1
- [ ] JS: Require acknowledgment for warnings

### 2.3 Install Footprint

- [ ] Define `data/install-footprint.json` schema
- [ ] Shell: Write footprint during install
- [ ] PHP: Read footprint for uninstall
- [ ] PHP: Read footprint for repair
- [ ] Document footprint contract

### 2.4 Package Ownership

- [ ] Define `/sys/pkg-register.json` schema
- [ ] Shell: Register packages during install
- [ ] Shell: Unregister packages during uninstall
- [ ] Shell: Check ownership before removing shared packages

### 2.5 Wizard UI Polish

- [x] Step-by-step wizard layout (single panel view)
- [x] Clickable step indicators in stepper
- [x] Step transitions with fade animation
- [x] Navigation buttons (Previous/Next) per panel
- [x] Panel titles with icons and descriptions
- [x] Checkbox cards for menu options
- [x] ImportWizard JS module for navigation
- [ ] Better error display for scan failures
- [ ] Preview of install actions before confirm
- [ ] Success/failure summary after install

---

## Phase 3: Resource Monitor (Future)

> **Note**: This comes AFTER wizard completion. See `1. inspiration/resource-monitor/`

### 3.1 Backend Scripts

- [ ] Create `/usr/local/bin/moode-resource-index.sh`
- [ ] Create `/usr/local/bin/moode-resource-metrics.py`
- [ ] Set up sudoers for www-data access
- [ ] Add logrotate configuration

### 3.2 PHP API

- [ ] Add `action=system_services` to ext-mgr-api.php
- [ ] Add `action=debian_packages` to ext-mgr-api.php
- [ ] Add `action=moode_status` to ext-mgr-api.php
- [ ] Cache results with TTL

### 3.3 Dashboard UI

- [ ] Create resource monitor section in ext-mgr.php
- [ ] Add Chart.js dependency (lightweight)
- [ ] Real-time CPU/RAM bar charts
- [ ] Service status table
- [ ] Package breakdown by layer (Debian/moOde core/moOde www)

### 3.4 Systemd Timer

- [ ] Create moode-resource.timer unit
- [ ] Create moode-resource.service unit
- [ ] 60-second metric collection interval
- [ ] Prometheus textfile exporter output

---

## Backlog

### Code Quality

- [ ] Refactor ext-mgr.js with module pattern
- [ ] Consolidate tooltip sources (keep JSON only)
- [ ] Add unit tests for ext_helper.py
- [ ] Add integration tests for wizard flow
- [ ] Document all API actions

### Developer Experience

- [ ] Create extension template generator command
- [ ] Add dry-run mode to shell scripts
- [ ] Improve error messages with actionable hints
- [ ] Add debug mode with verbose logging

### Security

- [ ] Audit path traversal protections
- [ ] Review symlink attack vectors
- [ ] Add checksum verification for downloads
- [ ] Implement signature verification (planned, not required)

---

## Completed

### ✅ Docker Development Environment

- [x] PHP 8.2 + Apache container
- [x] moOde www/ source cloning
- [x] Health check configuration
- [x] SSH key setup to tronio.local

### ✅ Documentation

- [x] ARCHITECTURE.md
- [x] sandbox-import-roadmap.md
- [x] CODE-REVIEW.md

---

## Notes

### Resource Monitor Design Constraints

- No Grafana (too heavy)
- No Prometheus server (use textfile exporter only)
- Target: < 50MB RAM overhead
- Target: < 5% CPU during metric collection
- Must work on Pi Zero 2 W

### Import Wizard Priorities

1. Security: Block bad packages before install
2. Reliability: Footprint enables clean uninstall
3. Transparency: User sees exactly what will change
4. Flexibility: Works with varied package structures

### Variables API Design

- Single JSON endpoint, cacheable
- Sync with ext-mgr-install-vars.json
- Provide defaults when file missing
- Document all keys and purposes

---

*Last updated: 2026-03-15*
