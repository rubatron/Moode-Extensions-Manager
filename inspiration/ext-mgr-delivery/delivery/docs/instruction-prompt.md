# Instructieprompt — ext-mgr Extension Development
## Versie 3 — sandbox architectuur + VSCode workspace + build-zip.sh

## Context

Je helpt bij de ontwikkeling van een **extension manager systeem (ext-mgr)** voor
**moOde Audio Player** — een Raspberry Pi audiospeler op Debian/Raspbian onder `/var/www/`.
Extensions worden als `.zip` geïmporteerd via een import wizard, geïnstalleerd onder
een managed root, en beheerd via een webinterface.

De referentie-extension heet **"Ronnie Pickering's Extension"** (`ronnie-pickering-extension`).

---

## Dev kit structuur

```
delivery/
├── ext-mgr.code-workspace        ← open in VSCode (multi-root)
├── RonniePickeringExtension/
│   ├── .vscode/                  ← tasks, launch, settings, extensions
│   │   ├── tasks.json            ← 9 project-specifieke tasks
│   │   ├── launch.json           ← PHP + Python debug configs
│   │   ├── settings.json         ← file associations, nesting, formatters
│   │   └── extensions.json       ← aanbevolen extensies
│   ├── scripts/build-zip.sh      ← DEV ONLY: bouwt importeerbare zip
│   └── ... (extension files)
├── docs/
└── wizard-design/
```

### build-zip.sh workflow

```bash
bash scripts/build-zip.sh           # → <ext-id>-<version>.zip naast root
bash scripts/build-zip.sh --dry-run # toon welke files meegenomen worden
bash scripts/build-zip.sh -o /tmp   # custom output dir
```

VSCode: `Ctrl+Shift+B` → **ext-mgr: Build zip**

Het script:
1. Leest `id` + `version` uit `manifest.json`
2. Draait `ext_helper.py scan` — aborteert bij violations
3. Bouwt file list via Python exclusie logica
4. Produceert `<ext-id>-<version>.zip`

**Altijd uitgesloten:**
`.vscode/`, `build-zip.sh` zelf, `*.code-workspace`, `.git*`, `__pycache__/`,
`*.pyc`, `logs/`, `cache/`, `data/install-footprint.json`, `.DS_Store`

**Defence in depth:** build-zip.sh sluit ze uit → import endpoint strip ze als fallback.

---

## Sandbox filosofie

**De extension folder is de enige bron van waarheid.**

Alles buiten de sandbox is een verwijzing terug naar wat erin zit:

| Type | Voorbeeld | Mechanisme |
|------|-----------|-----------|
| Groen (OK) | `.service` units | symlink → `/etc/systemd/system/` |
| Grijs (warning) | udev, binaries, lirc | symlink → `/etc/udev/`, `/usr/local/bin/` |
| Oranje (special) | `/boot/config.txt` | append-only met BEGIN/END guards |
| Rood (violation) | `/var/www/` buiten sandbox | geblokkeerd — install.sh aborteert |

`data/install-footprint.json` registreert alles buiten de sandbox (symlinks,
apt-packages, pip-packages, /boot/ toevoegingen). `uninstall.sh` leest alleen
de footprint — hoeft nooit te gokken.

---

## Projectstructuur (runtime)

```
/var/www/extensions/
├── installed/<ext-id>/                ← SANDBOX ROOT
│   ├── packages/                      ← ALLE ARTIFACTS HIER
│   │   ├── services/   ──symlink──→   /etc/systemd/system/
│   │   ├── config/
│   │   │   ├── udev/   ──symlink──→   /etc/udev/rules.d/
│   │   │   ├── modules/──symlink──→   /etc/modules-load.d/
│   │   │   ├── lirc/   ──symlink──→   /etc/lirc/
│   │   │   └── modprobe/──symlink──→  /etc/modprobe.d/
│   │   ├── bin/        ──symlink──→   /usr/local/bin/
│   │   ├── lib/        ──symlink──→   /usr/local/lib/
│   │   ├── pylib/      (pip --target, PYTHONPATH in service unit)
│   │   ├── webroot/    ──symlink──→   /var/www/extensions/...
│   │   └── opt/        ──symlink──→   /opt/
│   ├── scripts/install.sh / uninstall.sh / repair.sh / build-zip.sh
│   ├── assets/css/ js/ images/
│   ├── backend/api.php / ext_helper.py
│   ├── data/install-footprint.json
│   ├── template.php / manifest.json / info.json
│   └── logs/ / cache/
└── sys/
    ├── pkg-register.json
    └── logs/extensionslogs/<id>/
```

---

## Path Policy

| Severity | Prefix | Bundle target |
|----------|--------|--------------|
| ✓ OK | `/var/www/extensions/installed/<id>/` | — |
| ✓ OK | `/etc/systemd/system` | `packages/services/` |
| ✗ VIOLATION | `/var/www/` (overig) | `packages/webroot/` |
| ⚠ WARNING | `/etc/udev/rules.d/` | `packages/config/udev/` |
| ⚠ WARNING | `/etc/modules-load.d/` | `packages/config/modules/` |
| ⚠ WARNING | `/etc/lirc/` | `packages/config/lirc/` |
| ⚠ WARNING | `/boot/config.txt` | append-only patroon |
| ℹ INFO | `/usr/local/bin/` | `packages/bin/` |
| ℹ INFO | `/var/lib/` | `$ROOT/data/` of symlink |

Volledige tabel (22 entries): `python3 backend/ext_helper.py policy`

---

## stageProfile

| Waarde | Gedrag |
|--------|--------|
| `visible-by-default` | Direct zichtbaar in menus na install |
| `hidden-by-default` | Geïnstalleerd maar verborgen; gebruiker activeert handmatig |

Gebruik `hidden-by-default` voor extensions die pre-configuratie vereisen.

---

## Extension Types

| Type | Detectie | Kenmerken |
|------|----------|-----------|
| `hardware` | udev/gpio/lirc/i2c in install.sh | service, apt/pip, /boot/ |
| `streaming_service` | librespot/shairport/tidal | daemon, credentials |
| `theme` | alleen CSS, geen install.sh | geen service |
| `functionality` | template.php + backend | UI + API |
| `page` | template.php zonder backend | pure display |
| `other` | rest | diagnostics, tools |

---

## ext_helper.py CLI (7 commando's)

```bash
python3 ext_helper.py scan       .                          # audit + metadata
python3 ext_helper.py footprint  .                          # toon footprint
python3 ext_helper.py register   <reg.json> <id> <pkg>      # exit 0=new, 2=exists
python3 ext_helper.py unregister <reg.json> <id>            # release ownership
python3 ext_helper.py rewrite    . <old_id> <new_id>        # rename ID door alles
python3 ext_helper.py save-icon  . <src_file>               # .ico/.png/.svg
python3 ext_helper.py policy                                # print policy tabel
```

Scan output bevat: `path_audit`, `violations`, `warnings`, `apt_packages`,
`pip_packages`, `service_units`, `custom_icon`.

### Dependency detectie (3 lagen)
1. Glob op `.service` bestanden in `scripts/` en `packages/services/`
2. Regex op `systemctl enable`, `After=`, `Requires=` in install.sh
3. Bestaande `manifest.json service.dependencies` als basis

Strip voor analyse: heredocs, `case...esac`, `echo x`/`log x` regels
(maar behoud `echo x >> /path` — dat zijn schrijfoperaties).

---

## VSCode tasks

| Task | Shortcut |
|------|---------|
| **ext-mgr: Build zip** | `Ctrl+Shift+B` (default) |
| ext-mgr: Build zip (dry run) | Terminal menu |
| ext-mgr: Scan extension | Terminal menu |
| ext-mgr: Show path policy | Terminal menu |
| ext-mgr: Validate manifest | Terminal menu |
| ext-mgr: Show footprint | Terminal menu |
| ext-mgr: PHP dev server | Terminal menu |
| ext-mgr: Rewrite extension ID | Terminal menu |
| ext-mgr: ShellCheck all scripts | Terminal menu |

---

## manifest.json schema (compleet)

```json
{
  "id": "my-extension",
  "name": "My Extension",
  "version": "1.0.0",
  "main": "template.php",
  "ext_mgr": {
    "enabled": true, "state": "active",
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
    "install": { "packages": [], "script": "scripts/install.sh", "packageArtifacts": [] }
  }
}
```

**Vuistregels:**
- `requiresExtMgr` altijd `true`
- `moode-extmgr.service` altijd eerste in `dependencies`
- `type` verplicht
- `build-zip.sh` wordt automatisch uitgesloten van de importeerbare zip

---


---

## Settings nav suppression — verplicht voor extension detail pages

Extension detail pages mogen alleen de **back (<)**, **home** en **M** knoppen tonen.
De moOde settings nav (Library / Audio / Network / System / Renderers / Peripherals / Extensions)
hoort uitsluitend op de top-level moOde pagina's, niet binnen extension views.

### Confirmed selector — uit ext-mgr broncode

De settings nav tabs leven in **`#config-tabs`** — bevestigd via
`ext-mgr-hover-menu.js` (regel 188: `document.getElementById('config-tabs')`).
Dit is de enige selector nodig. Eerder gebruikte guessed selectors (#navbar-settings etc.)
zijn incorrect.

### Drie-laags defence (alle drie verplicht)

**Laag 1 — inline `<style>` in template.php (primair, snelst)**
Direct na `include '/var/www/header.php'` injecteren:
```php
echo '<style id="ext-nav-suppress">#config-tabs{display:none!important}</style>' . "\n";
```
Staat vóór enige pagina-content — wint altijd op specificiteit.

**Laag 2 — CSS block in template.css (failsafe)**
```css
#config-tabs { display: none !important; }
```

**Laag 3 — MutationObserver in template.js (dynamische failsafe)**
```js
function suppressNav() {
    var el = document.getElementById('config-tabs');
    if (el) { el.style.setProperty('display', 'none', 'important'); }
}
```
Draait direct + op DOMContentLoaded + via MutationObserver (3 seconden actief).
Vangt gevallen op waarbij moOde de nav herrendert na page load.

### Wat WEL zichtbaar blijft
Back knop (`<`), home knop en M-button zijn gerenderd **buiten** `#config-tabs`
in `header.php` — ze zijn niet aangetast door deze suppression.

### Wat WEL zichtbaar blijft
moOde's back button (`<`), home button en M-menu vallen buiten deze selectors
en zijn niet aangetast. Ze worden gerenderd door separate elementen in `header.php`.

### In de ExtensionTemplate meenemen
Dit patroon is verplicht voor elke extension. Neem alle drie lagen op in de
revised template. De wizard-generator moet dit automatisch meegenereren.

## Open punten

- [ ] `wizard.php` — generate actie + zip download
- [ ] ext-mgr import endpoint — upload → staging → strip_dev_files → install.sh
- [ ] PHP route creator — `ln -s template.php /var/www/<ext-id>.php`
- [ ] Menu registratie — manifest.menuVisibility → moOde menu entries
- [ ] Port tooling voor Stephanowicz addons
