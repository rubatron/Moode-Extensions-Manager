# Module 3 Rollback

## Remove module 3 manager files
```bash
sudo rm -f /var/www/extensions/extensions-registry.php
sudo rm -f /var/www/extensions/extensions-manager.php
sudo rm -f /var/www/extensions/extensions-manager-refresh.php
sudo rm -f /var/www/extensions/registry.json
sudo rm -f /var/www/extensions-manager.php
sudo rm -f /var/www/extensions-manager-refresh.php
```

## Restore index template
Use backup printed by installer, for example:
```bash
sudo cp -a /var/www/templates/indextpl.min.html.bak-module3-YYYYmmdd-HHMMSS /var/www/templates/indextpl.min.html
```

## Validate rollback
```bash
curl -s http://localhost/index.php | tr '\n' ' ' | sed 's/></>\n</g' | grep -n 'extensions-manager-btn\|/extensions-manager.php'
```
Expected: no output.
