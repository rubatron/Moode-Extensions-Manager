# Changes (Module 3: Extensions Manager)

## New files deployed by installer
- `/var/www/extensions/extensions-registry.php`
- `/var/www/extensions/extensions-manager.php`
- `/var/www/extensions/extensions-manager-refresh.php`
- `/var/www/extensions/extensions-hover-menu.js`
- `/var/www/extensions/registry.json`

## Root-level links
- `/var/www/extensions-manager.php` -> `/var/www/extensions/extensions-manager.php`
- `/var/www/extensions-manager-refresh.php` -> `/var/www/extensions/extensions-manager-refresh.php`

## Updated file
- `/var/www/templates/indextpl.min.html`
  - Adds or normalizes an `Extensions` menu button
  - Target: `/extensions-manager.php`
  - Adds inline hover panel (`extensions-hover-menu`) that dynamically reads `/extensions/registry.json`
  - Places `Extensions` before `Radio` in the Library list
  - Loads submenu behavior script: `/extensions/extensions-hover-menu.js`

- `/var/www/extensions/extensions-hover-menu.js`
  - Renders extension entries with icon (`fa-globe`)
  - Reads `pinned` state from `/var/www/extensions/registry.json`
  - Keeps extension panel visible when Library dropdown is opened and at least one extension is pinned

- `/var/www/extensions/extensions-manager-pin.php`
  - Saves per-extension `pinned` state from manager page form updates

- `/var/www/extensions/registry.json`
  - Extended with per-extension `pinned` boolean field

## Backup
- `/var/www/templates/indextpl.min.html.bak-module3-<timestamp>`
