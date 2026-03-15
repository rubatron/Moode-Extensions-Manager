# Module 3 Validation

## Functional validation
1. Open `http://moode1.local/index.php`
2. Open Library dropdown
3. Click `Extensions`
4. Expected: navigate to `http://moode1.local/extensions-manager.php`
5. Click `Refresh Extensions`
6. Expected: registry rebuilds and installed extensions list updates

## Runtime checks
Verify manager route:
```bash
curl -I http://localhost/extensions-manager.php
```

Verify refresh route:
```bash
curl -I http://localhost/extensions-manager-refresh.php
```

Verify registry file exists:
```bash
ls -l /var/www/extensions/registry.json
```

Verify registry content:
```bash
cat /var/www/extensions/registry.json
```
