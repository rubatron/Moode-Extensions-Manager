# Import Wizard Guide

> Step-by-step guide for the ext-mgr import wizard

## Overview

The import wizard provides a guided 5-step flow for installing extensions safely.

## Steps

### Step 1: Upload

**File:** ZIP package containing extension files

**Requirements:**

- Valid ZIP archive
- Contains `manifest.json` at root or in single subfolder
- Maximum size: 50MB

**Actions:**

- Drag & drop or click to select file
- Progress indicator during upload
- Automatic extraction to staging area

**API:** `import_extension_upload`

---

### Step 2: Metadata

**Configure extension identity:**

| Field | Required | Description |
|-------|----------|-------------|
| Name | Yes | Display name (from manifest or custom) |
| Version | Yes | Semantic version (x.y.z) |
| Type | Yes | `extension` or `theme` |
| Service Name | No | Systemd service name if applicable |
| Dependencies | No | Service dependencies (comma-separated) |
| APT Packages | No | Required packages (comma-separated) |

**Auto-populated from manifest when available.**

---

### Step 3: Menu

**Configure menu visibility:**

| Toggle | Default | Description |
|--------|---------|-------------|
| M Menu | Off | Show in moOde M menu |
| Library Menu | Off | Show in Library navigation |
| System Menu | Off | Show in System settings |
| Settings Card Only | Off | No page, just settings card |

**Note:** Extensions default to hidden until explicitly enabled.

---

### Step 4: Review

**Summary panel showing:**

- Extension metadata
- Menu visibility settings
- Code scan results (from ext_helper.py)
- Detected patterns/warnings
- moOde component integration (header/footer)
- Bundled services
- Required packages

**Code Scanner Checks:**

- Dangerous function calls (`eval`, `exec`, `shell_exec`)
- Unsanitized input usage
- SQL injection patterns
- Hardcoded credentials
- Debug statements

**API:** `import_extension_scan`

---

### Step 5: Install

**Progress bar with stages:**

1. **Copying files** - Move from staging to installed location
2. **Installing packages** - apt-get install for declared packages
3. **Running install script** - Execute `scripts/install.sh`
4. **Setting up services** - Install and enable service units
5. **Creating symlinks** - Canonical route at `/var/www/<id>.php`
6. **Updating registry** - Add to registry.json

**Success:**

- Congratulations panel
- Link to open extension
- Option to install another

**API:** `import_extension_install`

---

## UI Components

### Chevron Stepper

Visual progress indicator with:

- Completed steps: Solid orange background
- Active step: Orange with glow effect
- Upcoming steps: Dark background

### Progress Bar

Animated gradient bar showing:

- Current stage label
- Percentage complete
- Stage transitions

### Success Panel

Green congratulations message with:

- Extension name and version
- Quick action buttons
- Install another option

---

## Error Handling

| Error | Recovery |
|-------|----------|
| Invalid ZIP | Re-upload valid archive |
| Missing manifest | Add manifest.json to package |
| Invalid manifest | Fix required fields (id, name, main) |
| Package install fails | Check apt sources, retry |
| Install script fails | Check script permissions, logs |
| Service fails | Check unit file syntax |

---

## Keyboard Shortcuts

| Key | Action |
|-----|--------|
| Enter | Next step (when valid) |
| Escape | Cancel wizard |
| Tab | Navigate fields |

---

## Best Practices

1. **Test locally first** - Use template kit to develop
2. **Include all dependencies** - Declare apt packages in manifest
3. **Handle errors gracefully** - Install scripts should be idempotent
4. **Follow naming conventions** - kebab-case for IDs
5. **Keep packages minimal** - Only include necessary files
