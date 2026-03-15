# ext-mgr Code Review - March 2026

> Comprehensive analysis of the ext-mgr codebase identifying patterns, antipatterns,
> hardcoded values, dead code, and improvement opportunities.

---

## Executive Summary

| Category | Status | Priority |
|----------|--------|----------|
| **Hardcoded Paths** | 🔴 Critical | High |
| **Variables System** | 🟡 Partial | High |
| **JS Architecture** | 🟠 Monolithic | Medium |
| **Dead Code** | 🟡 Minimal | Low |
| **Wizard Flow** | 🟠 Incomplete | High |
| **moOde Integration** | 🟢 Good | Maintenance |

---

## 1. Hardcoded Paths Analysis

### 1.1 Critical: Scattered Path Definitions

The codebase has **80+ hardcoded path references** across 15+ files. This creates:

- Maintenance burden when paths change
- Risk of inconsistency between components
- Difficult testing/containerization

#### Current Hardcoded Paths (by file)

**ext-mgr-api.php** (primary offender):

```php
$extensionsRootPath = '/var/www/extensions';
$extensionsInstalledPath = $extensionsRootPath . '/installed';
$extensionsCachePath = $extensionsRootPath . '/cache';
// ... 20+ more inline path definitions
```

**ext-mgr-import-wizard.sh**:

```bash
SQLITE_DB="/var/local/www/db/moode-sqlite3.db"
REGISTRY_PATH="/var/www/extensions/sys/registry.json"
INSTALLED_ROOT="/var/www/extensions/installed"
# ... 10+ hardcoded paths
```

**assets/js/ext-mgr.js**:

```javascript
setText(cacheDirPathEl, cache.path || '/var/www/extensions/cache');
setText(backupDirPathEl, backup.path || '/var/www/extensions/sys/backup');
window.confirm('Clear /var/www/extensions/cache now?...');
```

### 1.2 Solution: Central Variables System

A variables system already exists partially: `scripts/ext-mgr-install-vars.json`

```json
{
  "schemaVersion": 1,
  "paths": {
    "installedRoot": "/var/www/extensions/installed",
    "legacyExtensionRoot": "/var/www/extensions",
    "runtimeRoot": "/var/www/extensions/sys/.ext-mgr",
    "sysLogsRoot": "/var/www/extensions/sys/logs",
    "extensionsLogsRoot": "/var/www/extensions/sys/logs/extensionslogs",
    "extMgrLogsRoot": "/var/www/extensions/sys/logs/ext-mgr logs",
    "registryPath": "/var/www/extensions/sys/registry.json"
  }
}
```

**Problem**: This file is only used by the shell import wizard, not by PHP or JS.

### 1.3 Recommendation: Unified Variables API

Create `/ext-mgr-api.php?action=variables` that returns:

```json
{
  "ok": true,
  "data": {
    "paths": {
      "extensionsRoot": "/var/www/extensions",
      "installedRoot": "/var/www/extensions/installed",
      "cacheRoot": "/var/www/extensions/cache",
      "registryPath": "/var/www/extensions/sys/registry.json",
      "logsRoot": "/var/www/extensions/sys/logs",
      "tooltipsPath": "/extensions/sys/assets/data/ext-mgr-tooltips.json",
      "canonicalRoutePattern": "/var/www/%s.php"
    },
    "uris": {
      "apiEndpoint": "/ext-mgr-api.php",
      "tooltipsEndpoint": "/extensions/sys/content/tooltips.md"
    },
    "security": {
      "user": "moode-extmgrusr",
      "group": "moode-extmgr",
      "webUser": "www-data"
    }
  }
}
```

**Implementation steps**:

1. Create `ExtMgrConfig` singleton class in PHP
2. Load vars from `ext-mgr-install-vars.json` or use defaults
3. Expose via API action `variables`
4. JS loads vars on init, stores in global state
5. Shell scripts source vars via `php -r 'echo json_encode(...)'`

---

## 2. JavaScript Architecture Analysis

### 2.1 Current State: Monolithic IIFE

`assets/js/ext-mgr.js` is a **2100+ line** single IIFE containing:

```
├── API client logic (api(), apiUpload())
├── Tooltip system (loadTooltipSnippets(), applyTip())
├── Modal system (ensureActionModal(), openActionModal())
├── Wizard state machine (setWizardFormFromManifest(), wizardSetStep())
├── Extension list rendering (renderItems())
├── Manager actions (runRefresh(), runUpdate(), runRepair())
├── System resources (renderSystemResources())
├── Update tracking (renderUpdateStatus(), checkUpdate())
├── Visibility controls (setManagerVisibility())
├── Event bindings (80+ lines of bindIfPresent calls)
└── Utility functions (escapeHtml(), markdownToHtml(), etc.)
```

### 2.2 Evaluation: Split vs Monolith

| Factor | Split | Monolith |
|--------|-------|----------|
| **Load performance** | Multiple requests (worse) | Single request (better) |
| **moOde integration** | Complex script ordering | Simple single include |
| **Development** | Cleaner separation | All-in-one debugging |
| **Tree-shaking** | Possible with bundler | N/A |
| **Browser caching** | Granular | All-or-nothing |

**Verdict**: Given moOde's single-page shell paradigm and the absence of a build system, **keep monolithic but modularize internally**.

### 2.3 Recommendation: Module Pattern

Refactor to internal modules without splitting files:

```javascript
(function() {
  'use strict';

  // ═══════════════════════════════════════════
  // Module: Config (loads from API)
  // ═══════════════════════════════════════════
  var Config = (function() {
    var paths = {};
    var loaded = false;

    function load() {
      return api({ action: 'variables' }).then(function(data) {
        paths = data.data.paths || {};
        loaded = true;
      });
    }

    function get(key, fallback) {
      return paths[key] || fallback;
    }

    return { load: load, get: get };
  })();

  // ═══════════════════════════════════════════
  // Module: API Client
  // ═══════════════════════════════════════════
  var API = (function() {
    function call(params) { /*...*/ }
    function upload(file) { /*...*/ }
    return { call: call, upload: upload };
  })();

  // ═══════════════════════════════════════════
  // Module: Wizard State Machine
  // ═══════════════════════════════════════════
  var Wizard = (function() {
    var state = { step: 'upload', session: null, scan: null };

    function setStep(step) { /*...*/ }
    function processUpload(file) { /*...*/ }
    function install() { /*...*/ }

    return { setStep: setStep, processUpload: processUpload, install: install };
  })();

  // ═══════════════════════════════════════════
  // Module: Extension List
  // ═══════════════════════════════════════════
  var ExtensionList = (function() {
    var items = [];
    function render() { /*...*/ }
    function filter(criteria) { /*...*/ }
    return { render: render, filter: filter };
  })();

  // ... etc

})();
```

---

## 3. Dead Code Analysis

### 3.1 Identified Dead/Legacy Code

**ext-mgr-api.php**:

- `showInMMenu` / `showInLibrary` fields: Legacy compatibility, kept for downstream scripts
- `isPhpFunctionEnabled()`: Only used for exec() check, could be simplified

**assets/js/ext-mgr.js**:

- `fallbackCopyText()`: Only used as clipboard fallback, modern browsers support navigator.clipboard
- Several element variables declared but never used if DOM element missing (by design for flexibility)

**scripts/**:

- `hyperv-*.ps1`: Development utilities, not production code
- `moode-oobe-*.sh`: Backup utilities, separate from core ext-mgr

### 3.2 Verdict

**Minimal dead code**. Legacy compatibility fields are intentional. No immediate cleanup needed.

---

## 4. Import Wizard Architecture Review

### 4.1 Current Flow

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│  JS: Upload  │────▶│ PHP: Scan    │────▶│ JS: Wizard   │
│  Form        │     │ + Extract    │     │ State        │
└──────────────┘     └──────────────┘     └──────────────┘
        │                    │                    │
        ▼                    ▼                    ▼
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│ JS: Review   │────▶│ PHP: Install │────▶│ Shell:       │
│ JSON Preview │     │ Orchestrate  │     │ Privileged   │
└──────────────┘     └──────────────┘     └──────────────┘
```

### 4.2 Gaps vs sandbox-import-roadmap.md

| Requirement | Status | Notes |
|-------------|--------|-------|
| ext_helper.py scan | ⚠️ Partial | Python scanner exists but inconsistently invoked |
| Scan output contract | ❌ Missing | No formal JSON schema between scan→wizard |
| Install footprint | ❌ Missing | `data/install-footprint.json` not written |
| Package ownership register | ❌ Missing | `/sys/pkg-register.json` not implemented |
| Policy-aware path blocking | ⚠️ Partial | `ext_helper.py` has rules but PHP doesn't enforce |

### 4.3 Recommendation: Implement Target Architecture

Per `docs/sandbox-import-roadmap.md`, the wizard needs:

1. **Scan Output Contract** - Define formal JSON schema
2. **Footprint Writer** - Record all external touches during install
3. **Policy Enforcer** - Block violations before install proceeds
4. **Manifest Normalizer** - Standardize package metadata before install

---

## 5. moOde Core Integration

### 5.1 Append-if-Available Pattern

**Current**: ext-mgr correctly checks for moOde shell files before including:

```php
if (file_exists('/var/www/inc/common.php')) {
    require_once '/var/www/inc/common.php';
}
if (file_exists('/var/www/header.php')) {
    $usingMoodeShell = true;
    include '/var/www/header.php';
}
```

**Status**: ✅ Good practice, no changes needed.

### 5.2 API Extension Opportunities

Consider exposing ext-mgr API for moOde core features:

```
GET /ext-mgr-api.php?action=system_services
GET /ext-mgr-api.php?action=debian_packages
GET /ext-mgr-api.php?action=moode_status
```

This would support the future Resource Monitor work.

---

## 6. Tooltips & Data Files

### 6.1 Current Tooltip Loading

JS loads tooltips from 3 sources (fallback chain):

1. `/extensions/sys/content/tooltips.md` (Markdown format)
2. `/extensions/sys/assets/data/ext-mgr-tooltips.json` (JSON format)
3. Hardcoded fallbacks in JS

**Problem**: Duplicated tooltip definitions, unclear which is authoritative.

### 6.2 Recommendation: Single Source

Keep only `assets/data/ext-mgr-tooltips.json` as authoritative. Remove markdown parsing complexity.

---

## 7. Priority Action Items

### High Priority

1. **Implement Variables API**
   - Create `buildVariablesResponse()` in PHP
   - Add `action=variables` handler
   - Update JS to load vars on init
   - Update shell scripts to consume vars

2. **Complete Wizard Architecture**
   - Implement scan output contract
   - Add install-footprint.json writer
   - Enforce path policy before install

### Medium Priority

1. **JS Internal Modularization**
   - Refactor to module pattern
   - Group related functions
   - Document module boundaries

2. **Unify Tooltip Source**
   - Keep JSON only
   - Remove markdown parsing code

### Low Priority

1. **API Extension for Resource Monitor**
   - Plan system_services action
   - Plan debian_packages action

---

## 8. Files Requiring Changes

| File | Changes Needed |
|------|----------------|
| `ext-mgr-api.php` | Add variables action, refactor config loading |
| `assets/js/ext-mgr.js` | Module pattern, load vars from API |
| `scripts/ext-mgr-import-wizard.sh` | Source vars from PHP/JSON |
| `scripts/ext-mgr-install-vars.json` | Expand schema, document |
| `assets/data/ext-mgr-tooltips.json` | Consolidate all tooltips here |

---

*Generated: 2026-03-15*
*Reviewer: ext-mgr development team*
