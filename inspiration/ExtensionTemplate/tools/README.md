# Developer Tools

This folder contains helper scripts for extension development and validation.

## ext_helper.py

Python utility for scanning your extension before import.

### Usage

```bash
# Scan extension for issues
python3 ext_helper.py scan /path/to/your-extension

# View built-in code patterns
python3 ext_helper.py patterns

# View path policy
python3 ext_helper.py policy
```

### Output

The scan command outputs JSON with:
- `path_audit`: File system paths used by install.sh
- `violations`: Paths that violate ext-mgr security policy
- `warnings`: Paths that need review
- `code_patterns`: Detected code patterns (e.g., deprecated APIs, hardcoded paths)
- `apt_packages`: Detected apt package dependencies
- `service_units`: Detected systemd service files

## Custom Patterns

Add your own patterns to `ext-mgr-patterns.json` in the root of your extension.
These will be merged with built-in patterns during import wizard scanning.

### Pattern Structure

```json
{
  "id": "my_custom_pattern",
  "label": "Human-readable label",
  "description": "What this pattern detects",
  "severity": "warning",
  "files": ["*.php", "*.sh"],
  "pattern": "regex_pattern_here",
  "autofix": false,
  "fix_description": "How to fix this manually"
}
```

### Severity Levels

- `ok`: Informational, no action needed
- `info`: Minor suggestion
- `warning`: Should be reviewed/fixed
- `violation`: Blocks import until fixed
- `upgradeable`: Can be auto-fixed by wizard
