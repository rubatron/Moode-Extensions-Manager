# ext-mgr (moOde Extensions Manager)

> Extension management system for moOde audio player (Raspberry Pi)
> Version 1.x | March 2026

## Features

- **Import Wizard** - Guided 5-step extension installation with progress feedback
- **Menu Control** - Toggle M menu / Library menu visibility per extension
- **Code Scanner** - Automated detection of patterns and issues via ext_helper.py
- **Service Management** - Systemd integration with watchdog monitoring
- **Self-Update** - Built-in update mechanism with rollback support
- **Debug Tools** - Registry, variables, services, and API status inspection

## Quick Start

### Install

```bash
wget -qO- https://raw.githubusercontent.com/rubatron/Moode-Extensions-Manager/main/scripts/bootstrap-moode.sh | sudo bash
```

### Uninstall

```bash
sudo bash install.sh --uninstall
```

## Architecture

See [ARCHITECTURE.md](ARCHITECTURE.md) for complete system documentation including:

- Component diagrams
- API reference (35+ endpoints)
- Security model
- Extension manifest format
- Service parenting

## Key Endpoints

| Endpoint | Purpose |
|----------|---------|
| `/ext-mgr.php` | Main UI |
| `/ext-mgr-api.php` | API (symlink to /extensions/api/) |

## Import Workflow

1. **Upload** - ZIP package upload and extraction
2. **Metadata** - Name, version, type configuration
3. **Menu** - M menu / Library / System visibility toggles
4. **Review** - Summary with code scan results
5. **Install** - Animated progress with stage feedback

## Extension Structure

```
my-extension/
├── manifest.json        # Required: id, name, main
├── my-extension.php     # Main entry point
├── assets/
│   ├── css/
│   └── js/
├── backend/
│   ├── api.php
│   └── ext_helper.py
├── scripts/
│   ├── install.sh
│   ├── uninstall.sh
│   └── my-extension.service
└── packages/
    └── config/
```

## Uninstall Behavior

Per-extension uninstall uses install metadata for clean removal:

- Remove symlinks and runtime links
- Execute `scripts/uninstall.sh` if declared
- Remove installed service units
- Remove apt packages (with shared-package guards)
- Timestamped backup kept under `/var/www/extensions/sys/backup`

## Security

- Extension ID validation (alphanumeric + hyphens)
- Path traversal blocked in ZIP extraction
- Privileged symlink repair via isolated helper
- Control-plane runs as `moode-extmgrusr`
- Watchdog monitors heartbeat and restarts stale services

## Documentation

- [ARCHITECTURE.md](ARCHITECTURE.md) - Complete system architecture
- [docs/guides/](docs/guides/) - Developer guides and FAQ

## Development

- Frontend: `assets/js/ext-mgr.js` + `assets/css/ext-mgr.css`
- Backend: `api/ext-mgr-api.php` (~7000 lines, 35+ actions)
- Scanner: `backend/ext_helper.py` (code pattern detection)

## License

MIT
