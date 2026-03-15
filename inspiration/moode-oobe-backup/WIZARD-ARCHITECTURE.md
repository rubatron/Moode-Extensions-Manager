# Import Wizard — Architecture Design
## ext-mgr v2

---

## Sandbox architectuur — kernprincipe

```
/var/www/extensions/installed/<ext-id>/   ← SANDBOX (bron van waarheid)
│
├── packages/services/   ──symlink──→   /etc/systemd/system/
├── packages/config/udev/──symlink──→   /etc/udev/rules.d/
├── packages/config/lirc/──symlink──→   /etc/lirc/
├── packages/bin/        ──symlink──→   /usr/local/bin/
├── packages/pylib/      (PYTHONPATH in service unit)
│
└── data/install-footprint.json   ← alles wat buiten sandbox aangeraakt is
```

Elke externe verwijzing wijst terug naar de sandbox — nooit omgekeerd.
Bij uninstall: lees footprint, verwijder alle symlinks + packages. Klaar.

**Uitzonderingen:**
- `/boot/config.txt` — append-only met `# BEGIN <ext-id>` / `# END <ext-id>` guards
- `/var/www/` buiten managed root — VIOLATION, geblokkeerd door install.sh

---

## Path policy (severity levels)

| Severity | Prefix | Strategy |
|----------|--------|----------|
| OK | `/var/www/extensions/installed/<id>/` | direct schrijven |
| OK | `/etc/systemd/system` | symlinks only |
| VIOLATION | `/var/www/` (overig) | → `packages/webroot/` + symlink |
| WARNING | `/etc/udev/rules.d/` | → `packages/config/udev/` + symlink |
| WARNING | `/etc/modules-load.d/` | → `packages/config/modules/` + symlink |
| WARNING | `/boot/config.txt` | append-only met guards |
| INFO | `/usr/local/bin/` | → `packages/bin/` + symlink |
| INFO | `/var/lib/` | → `$ROOT/data/` of symlink |

Volledig in `ext_helper.py PATH_POLICY` tabel (22 entries).
CLI: `python3 ext_helper.py policy`

---

## Wizard flow

```
Browser                    wizard.php              ext_helper.py
   │                           │                        │
   ├─ POST zip ───────────────→│                        │
   │                           ├─ extract to /tmp/ ─→  │
   │                           ├─ scan ──────────────→ scan()
   │                           │ ←── JSON scan result ─┤
   │ ←── prefilled metadata ───┤                        │
   │                           │                        │
   ├─ stap 1-6 invullen ──────→│                        │
   │                           │                        │
   ├─ POST generate ──────────→│                        │
   │                           ├─ rewrite ───────────→ rewrite()
   │                           ├─ write manifest.json   │
   │                           ├─ write info.json        │
   │                           ├─ (icon) ────────────→ save-icon()
   │                           ├─ zip tmp/              │
   │ ←── application/zip ──────┤                        │
```

---

## wizard.php endpoints

### POST ?action=upload
Input: `multipart/form-data` — `zip` field
Output:
```json
{
  "ok": true,
  "session_id": "uuid",
  "scan": { ...ext_helper scan output... }
}
```

### POST ?action=generate
Input: `application/json`
```json
{
  "session_id": "uuid",
  "ext_id": "my-extension",
  "name": "My Extension",
  "version": "1.0.0",
  "type": "functionality",
  "icon": "fa-solid fa-music",
  "menu_m": true, "menu_lib": true, "menu_sys": false,
  "settings_only": false,
  "stage": "visible-by-default",
  "svc_name": "my-extension.service",
  "deps": ["moode-extmgr.service"],
  "apt_packages": []
}
```
Output: `application/zip`

### POST ?action=upload-icon
Input: `multipart/form-data` — `icon` + `session_id`
Output: `{"ok": true, "path": "assets/images/icon.ico"}`

---

## stageProfile

| Waarde | Gedrag |
|--------|--------|
| `visible-by-default` | Direct zichtbaar in menus na install |
| `hidden-by-default` | Geïnstalleerd maar verborgen; gebruiker activeert |

Gebruik `hidden-by-default` voor extensions die pre-configuratie vereisen.

---

## Security checklist

- [ ] Zip-slip validatie: alle entries binnen target dir
- [ ] Session ID is UUID, opgeslagen in `$_SESSION`
- [ ] Temp dirs na download of na 30min TTL opgeschoond
- [ ] Zip max 50MB, icon max 512KB
- [ ] Toegestane icon extensies: `.ico`, `.png`, `.svg`
- [ ] manifest `id`: sanitize naar `[a-z0-9\-]`
- [ ] Path audit: violations blokkeren generate

---

## Custom icon upload

Twee modi in de icon picker:
1. FA grid — 5 categorieën + zoekbalk
2. Custom upload — `.ico`, `.png`, `.svg` (max 512 KB)

Bij upload: `ext_helper.py save-icon` → `assets/images/icon.<ext>`
`info.json → customIconPath` gezet. `iconClass` blijft als FA fallback.

---

## Install pipeline na wizard

```
zip download
    ↓
ext-mgr upload UI (apart van wizard)
    ↓
Zip-slip validatie
    ↓
Extraheer naar /var/www/extensions/staging/<ext-id>/
    ↓
Conflict check (update vs fresh install)
    ↓
Atomisch naar /var/www/extensions/installed/<ext-id>/
    ↓
Rechten instellen (644/755)
    ↓
PHP route: ln -s template.php /var/www/<ext-id>.php
    ↓
EXT_MGR_EXTENSION_ROOT=... sudo -u moode-extmgrusr bash scripts/install.sh
    ↓
Menu registratie (manifest.menuVisibility → moOde)
    ↓
Extension actief
```
