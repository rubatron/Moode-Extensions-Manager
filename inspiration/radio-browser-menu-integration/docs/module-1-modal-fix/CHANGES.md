# Changes

## Modified files on target

1. `/var/www/header.php`
- Relevant line: around `146`
- Change:
  - from: `if ($section == 'index')`
  - to: `if ($section == 'index' || $section == 'radio-browser')`

2. `/var/www/extensions/installed/radio-browser/radio-browser.php`
- Script include added:
  - `radio-browser-modal-fix.js`
- Relevant section: around lines `54-56`

3. `/var/www/extensions/installed/radio-browser/assets/radio-browser-modal-fix.js`
- New file
- Contains fallback open logic for `#configure-modal`

## Available backups
- `/var/www/header.php.bak-rbmenu-20260312-2`
- `/var/www/extensions/installed/radio-browser/radio-browser.php.bak-20260312-3`
- `/var/www/extensions/installed/radio-browser/radio-browser.php.bak-modalfix-20260312-3`

## Note
There were multiple intermediate attempts during interactive remote editing; the list above reflects the final state.
