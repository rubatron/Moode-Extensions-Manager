# Gedetailleerde Gespreksamenvatting
## ext-mgr / Ronnie Pickering's Extension — Volledige Ontwikkelsessie

---

## 1–9. Basisopbouw (samengevat)

- PHP YouTube embed → CSS template merge → bestanden splitsen → glass/gloss design
- Template zip ontvangen → echte moOde ext-mgr structuur ontdekt
- Screenshot: dubbele branding → `if (!$usingMoodeShell)` wrapper + `data-standalone`
- Architectuur: stageProfile, 6 extension types, import wizard ontwerp, `type` veld toegevoegd
- Intelligente scripts: install.sh (footprint + symlinks + pkg-register), uninstall.sh, repair.sh
- ext_helper.py: scan, footprint, register, unregister, rewrite, save-icon, policy
- moOde CSS alignment: `--rp-*` tokens, `body[data-standalone]`, glass via backdrop-filter

---

## 10. Import Wizard UI

### Versie 1 → Versie 2
- Icon picker: 5 FA categorieën + zoekbalk + custom upload (.ico/.png/.svg)
- `page` type toegevoegd aan dropdown
- `requiresExtMgr` vergrendeld (nooit toggle)
- Dependencies pre-filled vanuit scan (detected-tags + edit veld)
- Live manifest diff in review stap

### Pipeline visualisatie
Wizard zip → upload → staging → install.sh → systemd → menu.
Stap 4 (install.sh) meest concreet geïmplementeerd.

---

## 11. Dependency detectie (3 lagen)

1. **Structureel** — glob op `.service` bestanden
2. **Semantisch** — regex op `systemctl enable`, `After=`, `Requires=`
3. **Manifest fallback** — bestaande `dependencies` als basis

Strip voor analyse: heredocs, `case...esac`, `echo x` (maar behoud `echo x >> /path`).

---

## 12. Path policy — volledige implementatie

### Aanleiding
Alles buiten `/var/www/` (hardware/streaming addons schrijven naar `/etc/`, `/boot/`, `/usr/local/bin/`) moet ook gestructureerd worden.

### PATH_POLICY tabel (22 entries in ext_helper.py)
Elke categorie heeft: `severity`, `label`, `strategy`, `packages`.

### Severity levels
- `violation` — `/var/www/` buiten managed root — install.sh aborteert
- `warning` — `/etc/udev/`, `/boot/`, `/etc/lirc/` — tracked in footprint
- `info` — `/usr/local/bin/`, `/var/lib/` — aangeraden om te bundelen
- `ok` — managed root, systemd, system tools

### /boot/config.txt speciaal geval
Append-only, nooit overschrijven:
```bash
# BEGIN <ext-id>
dtoverlay=i2c-gpio,bus=3
# END <ext-id>
```
Footprint slaat toegevoegde regels op. Uninstall verwijdert BEGIN/END blok.

### Echo-redirect bug (gevonden en opgelost)
`echo 'x' >> /boot/config.txt` werd gestript door echo-filter.
Fix: behoud echo-regels met `>` of `>>` redirect.

### Pip detectie toegevoegd
`pip_packages` als aparte sleutel naast `apt_packages` in scan output.

---

## 13. Sandbox architectuur

### Kernprincipe
De extension folder is de enige bron van waarheid.

| Mechanisme | Voorbeeld | Hoe |
|-----------|-----------|-----|
| Symlink (groen) | `.service` units | `ln -sf $ROOT/packages/services/x.service /etc/systemd/system/` |
| Symlink (grijs) | udev, binaries | `ln -sf $ROOT/packages/config/udev/x.rules /etc/udev/rules.d/` |
| Append-only (oranje) | `/boot/config.txt` | `echo '# BEGIN <id>' >> /boot/config.txt` |
| Geblokkeerd (rood) | `/var/www/` buiten sandbox | install.sh aborteert |

`data/install-footprint.json` = de sleutel. Bevat alle symlinks, packages, boot-regels.
`uninstall.sh` leest footprint, verwijdert chirurgisch.

---

## 14. VSCode workspace

### Aanleiding
Vraag: zit er meerwaarde in een VSCode workspace meelevereb?

### Antwoord: ja, specifiek voor dit project
- Multi-root workspace: extension + docs + wizard-design in één venster
- `tasks.json`: 9 project-specifieke taken
- `launch.json`: PHP + Python debug configs
- `settings.json`: file nesting (manifest nestelt info.json), file associations
- `extensions.json`: aanbevolen extensies (Intelephense, Python, ShellCheck, shell-format)

---

## 15. build-zip.sh

### Aanleiding
Vraag: neem je een helper script mee om de zip te maken?

### Implementatie
`scripts/build-zip.sh` — Python-delegating bash script:

1. Leest `id` + `version` uit `manifest.json` → zip naam
2. `ext_helper.py scan` — aborteert bij violations
3. Python file listing via exclusie logica (geen raw glob patterns)
4. `zipfile.ZipFile` voor packing

### Uitgesloten bestanden
`.vscode/`, `build-zip.sh` zelf, `*.code-workspace`, `.git*`, `__pycache__/`,
`*.pyc`, `logs/`, `cache/`, `data/install-footprint.json`, `.DS_Store`

### Defence in depth
- build-zip.sh sluit dev files uit (primair)
- Import endpoint strip ze als fallback via `strip_dev_files()`
- build-zip.sh sluit zichzelf uit (dev tooling, niet in extension package)

### VSCode integratie
`tasks.json` roept `bash scripts/build-zip.sh` aan — niet meer een raw zip-commando.
Dry-run variant toegevoegd: `ext-mgr: Build zip (dry run)`.

---

## 16. Eindstatus bestanden

| Bestand | Regels | Status |
|---------|--------|--------|
| `template.php` | 146 | ✓ |
| `assets/css/template.css` | 529 | ✓ glass, moOde aligned |
| `assets/js/template.js` | ~120 | ✓ |
| `backend/ext_helper.py` | ~410 | ✓ 7 cmds + PATH_POLICY tabel |
| `scripts/install.sh` | ~230 | ✓ path policy, footprint, symlinks |
| `scripts/uninstall.sh` | ~133 | ✓ footprint-based |
| `scripts/repair.sh` | ~87 | ✓ symlink repair |
| `scripts/build-zip.sh` | ~100 | ✓ scan + clean zip |
| `.vscode/tasks.json` | ~120 | ✓ 9 tasks |
| `.vscode/launch.json` | ~60 | ✓ PHP + Python debug |
| `.vscode/settings.json` | ~50 | ✓ nesting + associations |
| `.vscode/extensions.json` | ~20 | ✓ aanbevelingen |
| `ext-mgr.code-workspace` | ~80 | ✓ multi-root workspace |
| `README.md` | ~120 | ✓ volledig bijgewerkt |

---

## 17. Open punten

### Hoge prioriteit
- [ ] `wizard.php` — generate actie + zip download
- [ ] ext-mgr import endpoint — upload → staging → strip_dev_files → install.sh
- [ ] PHP route creator
- [ ] Menu registratie

### Middelhoge prioriteit
- [ ] Port tooling voor Stephanowicz addons
- [ ] Service status indicator in template.php
- [ ] `wizard.php` icon upload handler

### Laag
- [ ] Preview screenshot voor theme-type extensions
- [ ] moOde CSS vars volledig mappen
