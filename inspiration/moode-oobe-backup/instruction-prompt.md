# Instructieprompt — ext-mgr Extension Development
## Versie 3 — inclusief sandbox architectuur

## Context

Je helpt bij de ontwikkeling van een **extension manager systeem (ext-mgr)** voor **moOde Audio Player** — een Raspberry Pi audiospeler op Debian/Raspbian onder `/var/www/`. Extensions worden als `.zip` geïmporteerd via een import wizard, geïnstalleerd onder een managed root, en beheerd via een webinterface.

De referentie-extension heet **"Ronnie Pickering's Extension"** (`ronnie-pickering-extension`). De import wizard is een 6-staps interactieve UI die een zip scant, metadata prefilt, en een bijgewerkte zip genereert.

---

## De sandbox filosofie

**De extension folder is de enige bron van waarheid.**

Alles wat buiten de sandbox zichtbaar moet zijn, is een verwijzing *terug* naar wat erin zit — nooit omgekeerd. Er zijn drie smaken:

### 1. Symlinks (groen — gewoon)
`/etc/systemd/system/my.service` → `packages/services/my.service` in sandbox.
Het bestand *leeft* in de sandbox. Bij uninstall verdwijnt de symlink; systemd vindt de unit niet meer.

### 2. Symlinks (grijs — warning)
`/etc/udev/rules.d/99-gpio.rules` → `packages/config/udev/99-gpio.rules`
`/usr/local/bin/oled-daemon` → `packages/bin/oled-daemon`
Zelfde principe, buiten het pure systemd-patroon. Worden geflagd door scanner. Allemaal opgeslagen in `data/install-footprint.json`.

### 3. Append-only (oranje — /boot/)
`/boot/config.txt` kan niet gesymlinkt worden. Gebruik append-only met guard comments:
```bash
# BEGIN <ext-id>
dtoverlay=i2c-gpio,bus=3
# END <ext-id>
```
Footprint slaat de toegevoegde regels op als tekst. `uninstall.sh` verwijdert het BEGIN/END blok.

### Violation (rood — geblokkeerd)
Elk `/var/www/` pad buiten de sandbox is een violation. `install.sh` aborteert.

### De footprint als sleutel
`data/install-footprint.json` registreert alles wat buiten de sandbox is aangeraakt:
- Elke symlink
- Elk apt-package
- Elke pip-package
- Elke `/boot/` toevoeging

`uninstall.sh` leest uitsluitend de footprint — hoeft nooit te gokken.

---

## Projectstructuur

```
/var/www/extensions/
├── installed/<ext-id>/                ← SANDBOX ROOT
│   ├── packages/                      ← ALLE ARTIFACTS HIER
│   │   ├── services/    ──symlink──→  /etc/systemd/system/
│   │   ├── config/
│   │   │   ├── udev/    ──symlink──→  /etc/udev/rules.d/
│   │   │   ├── modules/ ──symlink──→  /etc/modules-load.d/
│   │   │   ├── lirc/    ──symlink──→  /etc/lirc/
│   │   │   └── modprobe/──symlink──→  /etc/modprobe.d/
│   │   ├── bin/         ──symlink──→  /usr/local/bin/
│   │   ├── lib/         ──symlink──→  /usr/local/lib/
│   │   ├── pylib/       (pip --target, PYTHONPATH in service)
│   │   ├── webroot/     ──symlink──→  /var/www/extensions/...
│   │   ├── opt/         ──symlink──→  /opt/
│   │   └── data/                      bundled config artifacts
│   ├── scripts/
│   │   ├── install.sh                 intelligent installer
│   │   ├── uninstall.sh               footprint-based uninstaller
│   │   ├── repair.sh                  symlink repair
│   │   ├── service-runner.sh          daemon heartbeat
│   │   └── <ext-id>.service           systemd main unit
│   ├── assets/
│   │   ├── css/template.css
│   │   ├── js/template.js
│   │   └── images/                    icon.ico / icon.png / icon.svg
│   ├── backend/
│   │   ├── api.php
│   │   └── ext_helper.py              Python scanner/register/rewriter/icon
│   ├── data/
│   │   └── install-footprint.json    ← geschreven door install.sh
│   ├── logs/
│   ├── cache/
│   ├── template.php
│   ├── manifest.json
│   └── info.json
└── sys/
    ├── pkg-register.json              centrale package ownership registry
    └── logs/extensionslogs/<id>/
```

---

## Path Policy — volledig gestructureerd

| Severity | Prefix | Label | Bundle target |
|----------|--------|-------|--------------|
| ✓ OK | `/var/www/extensions/installed/<id>/` | managed root | — |
| ✓ OK | `/var/www/extensions/sys/` | shared sys root | — |
| ✓ OK | `/etc/systemd/system` | systemd units | `packages/services/` |
| ✓ OK | `/usr/bin/`, `/usr/sbin/` | system tools | — |
| ✗ VIOLATION | `/var/www/` (overig) | moOde web root | `packages/webroot/` |
| ⚠ WARNING | `/etc/udev/rules.d/` | udev rules | `packages/config/udev/` |
| ⚠ WARNING | `/etc/modules-load.d/` | kernel modules | `packages/config/modules/` |
| ⚠ WARNING | `/etc/modprobe.d/` | modprobe config | `packages/config/modprobe/` |
| ⚠ WARNING | `/etc/lirc/` | LIRC config | `packages/config/lirc/` |
| ⚠ WARNING | `/etc/asound` | ALSA config | `packages/config/alsa/` |
| ⚠ WARNING | `/etc/` (overig) | system config | `packages/config/` |
| ⚠ WARNING | `/boot/config.txt` | Pi boot config | append-only patroon |
| ⚠ WARNING | `/boot/` (overig) | Pi boot partitie | append-only patroon |
| ⚠ WARNING | `/home/` | user home | gebruik `$ROOT/data/` |
| ℹ INFO | `/usr/local/bin/` | local binary | `packages/bin/` |
| ℹ INFO | `/usr/local/lib/` | local library | `packages/lib/` |
| ℹ INFO | `/var/lib/` | runtime data | `$ROOT/data/` of symlink |
| ℹ INFO | `/opt/` | optional package | `packages/opt/` |
| ℹ INFO | `/tmp/`, `/run/` | transient | — |

De scanner (`ext_helper.py scan`) classificeert elk gevonden pad, rapporteert violations en warnings met concrete `strategy` en `packages` target. `install.sh` aborteert bij violations.

---

## stageProfile

| Waarde | Gedrag |
|--------|--------|
| `visible-by-default` | Direct zichtbaar in geconfigureerde menus na install |
| `hidden-by-default` | Geïnstalleerd maar verborgen; gebruiker activeert handmatig |

Gebruik `hidden-by-default` voor extensions die pre-configuratie vereisen (bijv. streaming service credentials, hardware GPIO setup).

---

## Extension Types

| Type | Detectie | Kenmerken |
|------|----------|-----------|
| `hardware` | udev/gpio/lirc/i2c in install.sh | systemd service, apt packages, /boot/ |
| `streaming_service` | librespot/shairport/tidal in install.sh | daemon, credentials config |
| `theme` | alleen CSS assets, geen install.sh | geen service |
| `functionality` | heeft template.php + backend | UI page + API |
| `page` | template.php zonder backend | pure display |
| `other` | rest | diagnostics, tools |

---

## ext_helper.py CLI (7 commando's)

```bash
python3 ext_helper.py scan       <root>                     # volledig JSON profiel
python3 ext_helper.py footprint  <root>                     # install footprint tonen
python3 ext_helper.py register   <reg.json> <id> <pkg>      # exit 0=new, 2=exists
python3 ext_helper.py unregister <reg.json> <id>            # returns orphaned pkgs
python3 ext_helper.py rewrite    <root> <old_id> <new_id>   # rename ID door alles
python3 ext_helper.py save-icon  <root> <src_file>          # .ico/.png/.svg installeren
python3 ext_helper.py policy                                 # print volledige policy tabel
```

### Scan output structuur
```json
{
  "ext_id": "...", "type": "hardware",
  "path_audit": [
    { "severity": "violation", "path": "/var/www/css/...",
      "label": "moOde web root", "strategy": "...", "packages": "packages/webroot/" }
  ],
  "violations": [...],
  "warnings":   [...],
  "apt_packages": ["libi2c-dev"],
  "pip_packages": ["luma.oled", "RPi.GPIO"],
  "service_units": ["scripts/my.service", "packages/services/helper.service"]
}
```

### Dependency detectie (3 lagen)
1. Structureel — glob op `.service` bestanden in `scripts/` en `packages/services/`
2. Semantisch — regex op `systemctl enable`, `After=`, `Requires=` in install.sh
3. Manifest fallback — bestaande `service.dependencies` als basis

Strip voor analyse: heredocs, `case...esac`, `echo`/`log` regels (maar behoud echo-redirects `echo x >> /path`), commentaarregels.

---

## manifest.json schema (compleet)

```json
{
  "id": "my-extension",
  "name": "My Extension",
  "version": "1.0.0",
  "main": "template.php",
  "ext_mgr": {
    "enabled": true,
    "state": "active",
    "type": "functionality",
    "stageProfile": "visible-by-default",
    "menuVisibility": { "m": true, "library": true, "system": false },
    "settingsCardOnly": false,
    "iconClass": "fa-solid fa-puzzle-piece",
    "service": {
      "name": "my-extension.service",
      "requiresExtMgr": true,
      "parentService": "moode-extmgr.service",
      "dependencies": ["moode-extmgr.service"]
    },
    "logging": {
      "localDir": "logs",
      "globalDir": "/var/www/extensions/sys/logs/extensionslogs/my-extension",
      "files": ["install.log", "system.log", "error.log"]
    },
    "install": {
      "packages": [],
      "script": "scripts/install.sh",
      "packageArtifacts": []
    }
  }
}
```

**Vuistregels:**
- `requiresExtMgr` altijd `true` — nooit toggle
- `moode-extmgr.service` altijd eerste in `dependencies`
- `type` verplicht — wizard gebruikt dit voor conditionele formulieren

---

## CSS / UI Regels

- `--rp-*` token prefix, `var(--accentxts, #d35400)` defer naar moOde
- `body[data-standalone]` voor standalone layout
- Nooit globale `body {}` overschrijven in moOde shell mode
- Glass via `backdrop-filter + rgba` — werkt op elke donkere achtergrond
- `config-title`, `config-help-static` zijn moOde standaard klassen — behouden

---

## Import Wizard — 6 stappen

1. Upload + scan (`ext_helper.py scan`)
2. Metadata — name, id, version, type, icon picker (FA + custom upload)
3. Menu — M/Library/System, settingsCardOnly, stageProfile
4. Service — detected units, deps pre-filled, symlink strategie
5. Packages — apt/pip packages, pkg-register check, artifacts
6. Review — live manifest diff, generate + download zip

---

## Open punten

- [ ] `wizard.php` PHP backend (generate + zip download)
- [ ] ext-mgr import endpoint (upload → staging → install.sh)
- [ ] PHP route creator (`ln -s template.php /var/www/<ext-id>.php`)
- [ ] Menu registratie (manifest.menuVisibility → moOde menu entries)
- [ ] Port tooling voor Stephanowicz addons
