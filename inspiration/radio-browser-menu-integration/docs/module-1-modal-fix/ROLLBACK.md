# Rollback Guide

## Purpose
Quickly restore a previous stable state if needed.

## Header rollback
Use backup:
- `/var/www/header.php.bak-rbmenu-20260312-2`

Command:
```bash
sudo cp -a /var/www/header.php.bak-rbmenu-20260312-2 /var/www/header.php
sudo chown www-data:www-data /var/www/header.php
php -l /var/www/header.php
```

## Radio Browser rollback
Use backup:
- `/var/www/extensions/installed/radio-browser/radio-browser.php.bak-modalfix-20260312-3`

Command:
```bash
sudo cp -a /var/www/extensions/installed/radio-browser/radio-browser.php.bak-modalfix-20260312-3 /var/www/extensions/installed/radio-browser/radio-browser.php
sudo chown www-data:www-data /var/www/extensions/installed/radio-browser/radio-browser.php
php -l /var/www/extensions/installed/radio-browser/radio-browser.php
```

## Remove fallback script only
If you only want to disable the modal fix:
```bash
sudo rm -f /var/www/extensions/installed/radio-browser/assets/radio-browser-modal-fix.js
```
Then also remove the include line from `radio-browser.php`.

## After rollback
- Hard refresh in browser (`Ctrl+F5`)
- Test `index.php` and `radio-browser.php` modal menu behavior
