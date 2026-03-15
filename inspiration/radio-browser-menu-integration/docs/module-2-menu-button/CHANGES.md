# Changes (Module 2: Menu Button)

## Summary
This module adds a dedicated `Radio Browser` button to the Library dropdown menu in moOde index view.

## Target File
- `/var/www/templates/indextpl.min.html`

## Applied Changes
1. Inserted a new button in the Library dropdown button group near existing view buttons.
- Added class: `radio-browser-link-btn`
- Added label: `Radio Browser`

2. Set canonical navigation target to:
- `/radio-browser.php`

3. Normalized any previous direct extension path references:
- from: `/extensions/installed/radio-browser/radio-browser.php`
- to: `/radio-browser.php`

## Backups
Backups created during rollout include:
- `/var/www/templates/indextpl.min.html.bak-rbmenubtn-20260312-1`
- `/var/www/templates/indextpl.min.html.bak-rbmenulink-20260312-1`

## Notes
- This module is intentionally limited to menu navigation behavior.
- Modal behavior remains part of module 1.
