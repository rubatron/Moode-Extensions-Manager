# ext-mgr Import Wizard — Design & Analysis

---

## 1. Wat `header.php` doet en waarom de template daarmee rekening moet houden

moOde's `header.php` rendert het volledige navigatiechrome:

- Topbalk met Library / Audio / Network / System / Renderers / Peripherals / Extensions tabs
- Het hamburger **M-menu** (volume, sleep timer, etc.)
- Bootstrap + jQuery + Font Awesome al geladen
- Sessiebeheer en gebruikersrol al gecontroleerd

Een extensie die `header.php` includet **mag en moet** alleen zijn eigen content-blok renderen.
De `ext-rp-page-title` (grote Bebas Neue header) was daarom dubbel op — dat is nu gecorrigeerd
via `if (!$usingMoodeShell)`.

**Correcte template-regel:**

```
moOde shell aanwezig  → header.php + eigen content + footer
Standalone / dev      → eigen volledige HTML + branding + CDN fonts
```

---

## 2. Addon-classificatie: Stephanowicz-stijl vs. ons ext-mgr systeem

Op basis van de bekende Stephanowicz addons en de moOde addon-patronen zijn er
**vijf herkenbare types**:

### Type A — `hardware`

Koppelt een fysiek apparaat aan moOde.
Voorbeelden: IR-ontvanger (LIRC), rotary encoder, GPIO-knoppen, OLED-display (luma.oled),
USB DAC driver patches.
Kenmerken:

- Schrijft naar `/etc/` (udev rules, modules, lirc config)
- Heeft een systemd service nodig
- Vereist apt-packages (python3-luma, lirc, wiringpi…)
- Moet hardware-aanwezigheid checken bij installatie

### Type B — `streaming_service`

Voegt een externe audio- of videobron toe.
Voorbeelden: Spotify Connect (librespot/raspotify), Tidal Connect, AirPlay 2 (shairport-sync),
YouTube Music, Plexamp, Bluetooth sink.
Kenmerken:

- Eigen daemon/service met credentials config
- Registreert zichzelf bij moOde's bronnen-API
- Kan netwerkpoorten openen
- Settings-pagina voor account/credentials

### Type C — `theme`

Verandert de visuele presentatie van moOde.
Voorbeelden: donkere/lichte skin, alternatieve coverart-weergave, aangepaste CSS-overrides.
Kenmerken:

- Geen systemd service
- Schrijft naar `/var/www/` of injecteert CSS
- Geen apt-packages
- Heeft een preview nodig

### Type D — `functionality`

Voegt een feature toe aan de bestaande moOde UI of backend.
Voorbeelden: equalizer-presets manager, playlist-export, OTA-updater, sleep timer UI,
onze Ronnie Pickering YouTube embed.
Kenmerken:

- Heeft een eigen PHP-pagina (`template.php`)
- Registreert zichzelf in het menu via `manifest.json`
- Kan optioneel een backend API hebben (`backend/api.php`)
- Kan al dan niet een service hebben

### Type E — `other`

Alles wat niet in A–D past: diagnostics-tools, log-viewers, backup-scripts, etc.

---

## 3. Wat de import wizard moet doen — stap voor stap

### Stap 0 — Upload & detectie

- Accepteer een `.zip`
- Extraheer naar een tijdelijke sandbox
- Zoek en valideer `manifest.json` + `info.json`
- Detecteer aanwezigheid van: `template.php`, `backend/`, `scripts/install.sh`,
  `packages/services/`, `assets/`

### Stap 1 — Basis metadata invullen

Prefill vanuit `info.json`/`manifest.json`, maar laat alles bewerkbaar:

- Name, version, author, description
- Extension ID (slug, auto-gegenereerd uit name, bewerkbaar)
- **Type** — dropdown: hardware / streaming_service / theme / functionality / other
- Repository URL
- Icon class (met live preview)

### Stap 2 — Menu & zichtbaarheid

- Welke menu's? (M / Library / System) — toggles
- `settingsCardOnly` aan/uit
- `stageProfile`: visible-by-default / hidden-by-default

### Stap 3 — Service configuratie

Conditioneel getoond als `scripts/install.sh` of `packages/services/` aanwezig zijn:

- Service name (auto-prefill: `{id}.service`)
- `requiresExtMgr` aan/uit
- Dependencies (vrij invoer, één per regel)
- Parent service (default: `moode-extmgr.service`)

### Stap 4 — Packages & dependencies

- apt-packages lijst (één per regel)
- Overzicht van gedetecteerde package artifacts
- Optioneel: post-install script pad

### Stap 5 — Logging

- Logbestanden bevestigen (default: install.log, system.log, error.log)
- Global log path preview

### Stap 6 — Review & generate

- Toont een diff van wat er verandert in `manifest.json` en `info.json`
- Download knop → geeft een nieuwe zip terug met bijgewerkte bestanden

---

## 4. Intelligente scanning — helper functions

### `ExtensionScanner` (PHP class)

```php
class ExtensionScanner {

    // Detecteert het extension type op basis van bestandsstructuur + inhoud
    public function detectType(string $root): string {
        // hardware: udev rules, GPIO references, /etc/ writes in install.sh
        if ($this->grepInFile("$root/scripts/install.sh", '/udev|gpio|lirc|dtoverlay/i'))
            return 'hardware';
        // streaming: known daemon names
        if ($this->grepInFile("$root/scripts/install.sh", '/librespot|shairport|raspotify|spotifyd|tidal/i'))
            return 'streaming_service';
        // theme: only CSS/image assets, no service
        if (!file_exists("$root/scripts/install.sh") && $this->hasOnlyAssets($root))
            return 'theme';
        // functionality: has a template.php
        if (file_exists("$root/template.php"))
            return 'functionality';
        return 'other';
    }

    // Scant install.sh op apt-get install regels en extraheert packagenamen
    public function detectAptPackages(string $installScript): array {
        $content = file_get_contents($installScript);
        preg_match_all('/apt(?:-get)?\s+install[^;|\n]*?([\w\-]+(?:\s+[\w\-]+)*)/m', $content, $m);
        // filter flags (-y, --no-install-recommends etc.)
        return array_filter($m[1] ?? [], fn($p) => !str_starts_with($p, '-'));
    }

    // Scant packages/services/ op .service bestanden
    public function detectServiceUnits(string $root): array {
        $dir = "$root/packages/services";
        if (!is_dir($dir)) return [];
        return glob("$dir/*.service") ?: [];
    }

    // Genereert een veilige slug van de extensienaam
    public function slugify(string $name): string {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
    }

    // Leest ExecStart uit een .service bestand
    public function parseServiceExec(string $serviceFile): string {
        preg_match('/^ExecStart=(.+)$/m', file_get_contents($serviceFile), $m);
        return trim($m[1] ?? '');
    }
}
```

### `ManifestBuilder` (PHP class)

```php
class ManifestBuilder {
    public function build(array $wizard): array {
        return [
            'id'      => $wizard['id'],
            'name'    => $wizard['name'],
            'version' => $wizard['version'],
            'main'    => 'template.php',
            'ext_mgr' => [
                'enabled'          => true,
                'state'            => 'active',
                'stageProfile'     => $wizard['stageProfile'],
                'menuVisibility'   => $wizard['menuVisibility'],
                'settingsCardOnly' => $wizard['settingsCardOnly'],
                'iconClass'        => $wizard['iconClass'],
                'type'             => $wizard['type'],   // ← het ontbrekende veld!
                'service'          => [
                    'name'           => $wizard['id'] . '.service',
                    'requiresExtMgr' => true,
                    'parentService'  => 'moode-extmgr.service',
                    'dependencies'   => $wizard['dependencies'],
                ],
                'logging' => [
                    'localDir'  => 'logs',
                    'globalDir' => '/var/www/extensions/sys/logs/extensionslogs/' . $wizard['id'],
                    'files'     => ['install.log', 'system.log', 'error.log'],
                ],
                'install' => [
                    'packages' => $wizard['aptPackages'],
                    'script'   => 'scripts/install.sh',
                ],
            ],
        ];
    }
}
```

### `TemplateRewriter` (PHP class)

Hernoemt alle `template-extension` strings in scripts en service-bestanden naar de nieuwe ID.

```php
class TemplateRewriter {
    public function rewrite(string $root, string $oldId, string $newId): void {
        $files = array_merge(
            glob("$root/scripts/*.sh"),
            glob("$root/scripts/*.service"),
            ["$root/template.php", "$root/backend/api.php"]
        );
        foreach ($files as $file) {
            if (!is_file($file)) continue;
            $content = file_get_contents($file);
            file_put_contents($file, str_replace($oldId, $newId, $content));
        }
        // Rename service file
        $old = "$root/scripts/$oldId.service";
        $new = "$root/scripts/$newId.service";
        if (file_exists($old) && $old !== $new) rename($old, $new);
    }
}
```

---

## 5. Stephanowicz addons porten — aanpak per type

De addons van Stephanowicz zijn al moOde-compatible (ze schrijven direct naar `/var/www/`
en gebruiken moOde's interne functies). Ze missen echter:

- Een `manifest.json` voor ext-mgr
- Een `info.json`
- Een gestructureerde `packages/` map
- De `ext-template-*` CSS klassen

### Port-strategie per type

**hardware (bijv. rotary encoder, IR, OLED):**

1. Kopieer de install/uninstall bash scripts naar `scripts/`
2. Kopieer eventuele `.service` units naar `packages/services/`
3. Extraheer apt-packages uit install.sh → `manifest.json install.packages`
4. Maak een `template.php` als er een configuratiepagina is, anders `settingsCardOnly: true`
5. Wrap bestaande config HTML in `ext-template-card` + `ext-template-picker-row`

**streaming_service (bijv. librespot, shairport):**

1. Zelfde als hardware maar `type: streaming_service`
2. Credentials-formulier in `template.php` met backend/api.php om de config te schrijven
3. Service status indicator (is daemon running?) via `backend/api.php` → `systemctl is-active`

**theme:**

1. Kopieer CSS naar `assets/css/`
2. `install.sh` linkt/kopieert CSS naar moOde's stylesheet directory
3. Geen service nodig → service-sectie weglaten uit manifest
4. Preview-screenshot in `assets/images/preview.png`

**functionality:**

1. Directe mapping naar onze template-structuur
2. Bestaande PHP-pagina wordt `template.php`, refactor naar `ext-template-*` klassen
3. Backend logica naar `backend/api.php`

---

## 6. Wat er nu ontbreekt in ons manifest schema

Na analyse van de bovenstaande types missen we het volgende veld:

```json
"type": "functionality"
```

Geldige waarden: `hardware` | `streaming_service` | `theme` | `functionality` | `other`

Dit veld is nodig voor:

- De import wizard om het juiste formulier te tonen (stap 3 service conditie)
- De extension manager UI om extensions te filteren/groeperen
- De install pipeline om de juiste stappen te kiezen

---

## 7. Wizard UI — stap-voor-stap in JS/PHP

### PHP-kant (wizard.php)

```
POST /ext-mgr/wizard.php
  action=upload    → opslaan zip, scannen, teruggeven detected_data JSON
  action=step      → opslaan wizard state in $_SESSION
  action=generate  → manifest/info herschrijven, nieuwe zip genereren, download
```

### JS-kant (wizard.js)

```javascript
const STEPS = ['upload', 'metadata', 'menu', 'service', 'packages', 'review'];

class ImportWizard {
  constructor() {
    this.state = {};
    this.currentStep = 0;
  }

  async upload(file) {
    const fd = new FormData();
    fd.append('zip', file);
    fd.append('action', 'upload');
    const res = await fetch('/ext-mgr/wizard.php', { method: 'POST', body: fd });
    const detected = await res.json();
    // Prefill state from scanner
    this.state = { ...this.state, ...detected };
    this.render();
  }

  goTo(step) {
    // Validate current step before advancing
    if (!this.validate(this.currentStep)) return;
    this.currentStep = step;
    this.render();
  }

  render() {
    const step = STEPS[this.currentStep];
    document.getElementById('wizard-body').innerHTML = this.templates[step]();
    this.bindEvents();
  }

  async generate() {
    const res = await fetch('/ext-mgr/wizard.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'generate', state: this.state })
    });
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    // Trigger download
    Object.assign(document.createElement('a'),
      { href: url, download: this.state.id + '.zip' }).click();
  }
}
```

---

## 8. Samenvatting: kritische verbeteringen voor ons systeem

| Wat | Prioriteit | Opmerking |
|-----|-----------|-----------|
| `type` veld in manifest | **Hoog** | Fundamenteel voor filtering en wizard-logica |
| Import wizard (stap 0–6) | **Hoog** | Kern van de gevraagde functionaliteit |
| `ExtensionScanner` PHP class | **Hoog** | Intelligente auto-detectie |
| `TemplateRewriter` PHP class | **Hoog** | Automatisch hernoemen van template-extension ID |
| Port-handleiding per type | **Midden** | Documentatie voor Stephanowicz-style addons |
| Preview screenshot support | **Laag** | Nuttig voor theme-type extensions |
| `settingsCardOnly` auto-detect | **Laag** | Als er geen template.php is → forceer true |
EOF

---

## 9. moOde CSS alignment — hoe het werkt

### Variabelen die moOde zelf injecteert (runtime, per theme)

moOde's theme engine schrijft CSS variabelen op `:root` via PHP.
De enige die gegarandeerd beschikbaar is in **alle** moOde themes:

```css
--accentxts: #d35400;   /* oranje accent, thema-afhankelijk */
```

moOde Bootstrap 3 body achtergrond per thema (approximaties):

- Midnight:  `#161616`
- Default:   `#1c1c1c`
- Cards/sections: `rgba(0,0,0,0.14)` tot `rgba(255,255,255,0.04)`

### Onze token strategie (--rp-* prefix)

```css
--rp-accent: var(--accentxts, #d35400);  /* defer to moOde, safe fallback */
--rp-bg-page: #161616;                   /* moOde Midnight fallback        */
--rp-text: #d6dbe0;                      /* moOde standaard body tekst     */
```

Alle `--rp-*` tokens zijn onze eigen namespace — geen conflict met moOde.

### data-standalone attribuut

`template.php` zet `data-standalone="true"` op `<body>` wanneer moOde's
`header.php` **niet** aanwezig is. Zo scopet CSS naar de juiste context:

```css
/* Alleen actief buiten moOde shell */
body[data-standalone] { background: var(--rp-bg-page); padding: 1.5rem; }

/* Binnen moOde shell: shell geeft al padding via Bootstrap #container */
body:not([data-standalone]) .ext-template-shell { padding-top: 0.5rem; }
```

### Waarom geen body/html reset in moOde shell mode

moOde laadt Bootstrap 3 + zijn eigen `moode.css`. Als onze CSS
`body { background: ... }` overschrijft conflicteert dat met moOde's theme.
Door alles onder `.ext-template-shell` te scopen en alleen
`body[data-standalone]` te raken, zijn we 100% non-destructief.

### Glass werkt op elke donkere achtergrond

`backdrop-filter: blur(14px)` en `rgba(255,255,255,0.035)` glass backgrounds
werken op elke donkere moOde achtergrond zonder absolute kleurwaarden te hoeven kennen.
