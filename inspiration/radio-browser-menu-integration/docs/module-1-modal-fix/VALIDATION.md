# Validation

## Syntax checks
1. `php -l /var/www/header.php`
- Result: no syntax errors

2. `php -l /var/www/extensions/installed/radio-browser/radio-browser.php`
- Result: no syntax errors

## Runtime checks
1. HTML output includes the scripts:
- `/extensions/installed/radio-browser/assets/radio-browser.js`
- `/extensions/installed/radio-browser/assets/radio-browser-modal-fix.js`

2. DOM includes `#configure-modal` markup on `radio-browser.php`.

3. Functional check:
- From the `Configure` menu on `radio-browser.php`, the modal opens correctly.
- Previous behavior (backdrop only) is resolved.

## Additional
SSH key-based login for `pi@moode1.local` was also configured and verified with:
`ssh -o BatchMode=yes -o PasswordAuthentication=no pi@moode1.local "echo KEY_OK"`
