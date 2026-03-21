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

## Custom Patterns

Add your own patterns to `ext-mgr-patterns.json` in the root of your extension.
These will be merged with built-in patterns during import wizard scanning.
