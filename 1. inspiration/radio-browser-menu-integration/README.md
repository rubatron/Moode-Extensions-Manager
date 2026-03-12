# Radio Browser Modal Menu Integration

This project contains the implementation report and technical documentation for the moOde Radio Browser modal-menu fix.

## Goal
Make `Configure` from the main menu behave on `radio-browser.php` the same way it behaves on `index.php`.

## Result
Completed: `Configure` now opens correctly on the Radio Browser page.

## Contents
- `CHANGELOG.md`: chronological summary of module work
- `docs/module-1-modal-fix/REPORT.md`: module 1 report (modal fix)
- `docs/module-1-modal-fix/CHANGES.md`: module 1 exact file-level changes
- `docs/module-1-modal-fix/VALIDATION.md`: module 1 validation checks and test outcomes
- `docs/module-1-modal-fix/ROLLBACK.md`: module 1 rollback procedure and backup paths
- `docs/module-2-menu-button/REPORT.md`: module 2 report (Library menu button)
- `docs/module-2-menu-button/CHANGES.md`: module 2 exact file-level changes
- `docs/module-2-menu-button/VALIDATION.md`: module 2 validation
- `docs/module-2-menu-button/ROLLBACK.md`: module 2 rollback guide
- `docs/module-3-extension-manager/ARCHITECTURE.md`: module 3 architecture decision (file registry vs SQLite)
- `docs/module-3-extension-manager/REPORT.md`: module 3 report (extensions manager)
- `docs/module-3-extension-manager/CHANGES.md`: module 3 exact file-level changes
- `docs/module-3-extension-manager/VALIDATION.md`: module 3 validation
- `docs/module-3-extension-manager/ROLLBACK.md`: module 3 rollback guide
- `scripts/install-modal-fix.sh`: native Raspberry Pi installer
- `scripts/install-module-2-menu-button.sh`: native Raspberry Pi installer (module 2)
- `scripts/install-module-3-extension-manager.sh`: native Raspberry Pi installer (module 3)
- `scripts/install-modal-fix.ps1`: optional Windows remote installer

## Installer Usage
Run directly on the Raspberry Pi:

```bash
cd /path/to/radio-browser-menu-integration
chmod +x ./scripts/install-modal-fix.sh
./scripts/install-modal-fix.sh
```

Optional remote install from PowerShell:

```powershell
Set-ExecutionPolicy -Scope Process Bypass -Force
.\scripts\install-modal-fix.ps1 -Host moode1.local -User pi
```

## Module 2 Installer Usage
Run directly on the Raspberry Pi:

```bash
cd /path/to/radio-browser-menu-integration
chmod +x ./scripts/install-module-2-menu-button.sh
./scripts/install-module-2-menu-button.sh
```

## Module 3 Installer Usage
Run directly on the Raspberry Pi:

```bash
cd /path/to/radio-browser-menu-integration
chmod +x ./scripts/install-module-3-extension-manager.sh
./scripts/install-module-3-extension-manager.sh
```

## Project Path
`D:\projects\radio-browser-menu-integration`
