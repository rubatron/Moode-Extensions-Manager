# Module 2 Validation

## Functional Validation
1. Open `http://moode1.local/index.php`
2. Open Library dropdown menu
3. Click `Radio Browser`
4. Expected result: browser navigates to `http://moode1.local/radio-browser.php`

## Runtime Validation Commands
Check rendered HTML:
```bash
curl -s http://localhost/index.php | tr '\n' ' ' | sed 's/></>\n</g' | grep -n 'radio-browser-link-btn\|window.location.href='
```

Expected snippet includes:
```html
<button ... class="btn radio-browser-link-btn ..." ... onclick="window.location.href='/radio-browser.php';">
```

## Endpoint Validation
Verify endpoint exists:
```bash
ls -l /var/www/radio-browser.php
```

Expected: symlink to extension entrypoint.
