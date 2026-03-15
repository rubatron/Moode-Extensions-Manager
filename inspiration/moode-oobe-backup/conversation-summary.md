# Gedetailleerde Gespreksamenvatting

## ext-mgr / Ronnie Pickering's Extension — Volledige Ontwikkelsessie

---

## 1. Startpunt — PHP YouTube embed

Embed `r0dcv6GKNNw`. Responsive 16:9 wrapper, XSS-bescherming, YouTube params.

## 2. Merge met CSS template

Bestaand stijlsysteem met `ext-template-*` klassen. Drie cards: video, meta, embed code. Kopieer-knop.

## 3. Bestanden splitsen + design

`template.php` + `style.css` + `script.js`. Bebas Neue / DM Sans / JetBrains Mono. Oranje glass design.

## 4. Template zip ontvangen

Upload `template-extension-template__5_.zip` onthulde de werkelijke moOde ext-mgr structuur: `manifest.json`, moOde shell detectie, `scripts/`, `packages/services/`.

## 5. Glass / gloss design

`backdrop-filter: blur(18px)`, `::before` glans streaks, `::after` bevel highlights, video frame glow, multi-layer button gloss.

## 6. Screenshot — dubbele branding opgelost

moOde nav + branded title tegelijk → `if (!$usingMoodeShell)` wrapper. `data-standalone` attribuut op body.

## 7. Architectuur-discussie

### stageProfile

- `visible-by-default` → direct zichtbaar na install
- `hidden-by-default` → verborgen, gebruiker activeert handmatig
- Gebruik hidden voor extensions die eerst configuratie nodig hebben

### Extension types (6)

`hardware` | `streaming_service` | `theme` | `functionality` | `page` | `other`

### Import wizard (6 stappen)

Upload → Metadata → Menu → Service → Packages → Review

### Ontbrekend veld: `type`

Toegevoegd aan manifest schema. Verplicht voor wizard filtering en install pipeline.

## 8. Intelligente helper scripts

### install.sh

- Footprint tracking (`data/install-footprint.json`)
- Service units als **symlinks** (nooit kopiëren)
- Central package register (`pkg-register.json`)
- Path policy enforcement (abort bij violations)

### uninstall.sh

Leest footprint, verwijdert chirurgisch. Gedeelde packages worden gespaard.

### repair.sh

Herstelt symlinks vanuit footprint. Herstart service indien inactief.

## 9. ext_helper.py

### Commando's

`scan`, `footprint`, `register`, `unregister`, `rewrite`, `save-icon`, `policy`

### Dependency detectie — 3 lagen

1. Structureel — glob op `.service` bestanden
2. Semantisch — regex op `systemctl enable`, `After=`, `Requires=`
3. Manifest fallback — bestaande `dependencies` als basis

### Bugs gevonden en opgelost

- Apt-parser pakte false positives uit Python heredoc blokken → strip heredocs voor parsing
- Path scanner pakte `case...esac` patronen → strip case/esac voor analyse
- Echo-strip verwijderde ook `echo x >> /boot/config.txt` → behoud echo met redirectie

### IconHandler

`.ico`, `.png`, `.svg` (max 512 KB) → `assets/images/icon.<ext>`.

## 10. moOde CSS alignment

`--rp-*` token prefix, `var(--accentxts, #d35400)` defer naar moOde runtime. `body[data-standalone]` voor standalone layout.

## 11. Import Wizard UI

### Versie 1

6-staps basis wizard.

### Versie 2

- Icon picker: 5 FA categorieën + zoekbalk + custom upload zone
- `page` type toegevoegd
- `requiresExtMgr` vergrendeld (nooit toggle)
- Dependencies pre-filled vanuit scan

## 12. Pipeline visualisatie

Wizard zip → ext-mgr upload → staging → install.sh → systemd → menu. Stap 4 meest concreet.

## 13. Dependency detectie explainer

6-staps interactieve widget: overzicht → heredoc strip → laag 1 → laag 2 → laag 3 → eindresultaat.

## 14. Path policy revisie

### Aanleiding

Alles wat naar `/var/www/` schrijft moet naar managed root. En ook alles buiten `/var/www/`.

### PATH_POLICY tabel (22 entries)

Elke path-categorie heeft: `severity`, `label`, `strategy`, `packages`.

Scan output: `path_audit`, `violations`, `warnings` gesorteerd op severity.

Violations blokkeren import. Warnings tonen concrete bundle target.

### Echo-redirect bug

`echo 'x' >> /boot/config.txt` werd gestript door echo-filter. Fix: bewaar echo-regels met `>` of `>>`.

### Pip detectie toegevoegd

`pip_packages` als aparte sleutel naast `apt_packages` in scan output.

## 15. Sandbox architectuur

### De kernfilosofie

**De extension folder is de enige bron van waarheid.**

Alles buiten de sandbox is een verwijzing terug naar wat erin zit:

| Type | Voorbeeld | Mechanisme |
|------|-----------|-----------|
| Groen | `.service` units | symlink naar `/etc/systemd/system/` |
| Grijs | udev rules, binaries | symlink naar `/etc/udev/`, `/usr/local/bin/` |
| Oranje | `/boot/config.txt` | append-only met BEGIN/END guard |
| Rood | `/var/www/` buiten sandbox | geblokkeerd — violation |

### Footprint als sleutel

Alles wat buiten de sandbox is aangeraakt staat in `data/install-footprint.json`. `uninstall.sh` hoeft nooit te gokken.

## 16. Eindstatus bestanden

| Bestand | Regels | Status |
|---------|--------|--------|
| `template.php` | 146 | ✓ |
| `assets/css/template.css` | 529 | ✓ moOde aligned, glass |
| `assets/js/template.js` | ~120 | ✓ |
| `backend/api.php` | ~15 | ✓ |
| `backend/ext_helper.py` | ~380 | ✓ 7 commando's, PATH_POLICY |
| `scripts/install.sh` | ~230 | ✓ path policy, footprint |
| `scripts/uninstall.sh` | 133 | ✓ footprint-based |
| `scripts/repair.sh` | 87 | ✓ |
| `scripts/service-runner.sh` | ~10 | ✓ |
| `scripts/<ext-id>.service` | ~15 | ✓ |
| `manifest.json` | 43 | ✓ incl. type field |
| `info.json` | 10 | ✓ |
| `packages/data/video-config.json` | ~12 | ✓ |
| `packages/services/helper.service` | ~15 | ✓ |

## 17. Open punten

### Hoge prioriteit

- [ ] `wizard.php` — PHP backend, generate actie, zip download
- [ ] ext-mgr import endpoint — ZIP upload, staging, path validatie, zip-slip check
- [ ] PHP route creator — `ln -s template.php /var/www/<ext-id>.php`
- [ ] Menu registratie — `manifest.menuVisibility` → moOde menu entries

### Middelhoge prioriteit

- [ ] Port tooling voor Stephanowicz addons (scan → manifest → zip)
- [ ] `wizard.php` icon upload handler
- [ ] Service status indicator in `template.php`

### Laag

- [ ] Preview screenshot voor theme-type extensions
- [ ] `settingsCardOnly` auto-detect
- [ ] moOde CSS variabelen volledig mappen
