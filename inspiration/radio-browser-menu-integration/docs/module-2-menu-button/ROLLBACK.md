# Module 2 Rollback

## Purpose
Restore the previous Library menu template if the custom button must be removed.

## Backup Files
Examples created during deployment:
- `/var/www/templates/indextpl.min.html.bak-rbmenubtn-20260312-1`
- `/var/www/templates/indextpl.min.html.bak-rbmenulink-20260312-1`

## Rollback Command
Use the latest backup available:
```bash
sudo cp -a /var/www/templates/indextpl.min.html.bak-rbmenulink-20260312-1 /var/www/templates/indextpl.min.html
```

If that backup does not exist, use the earlier one:
```bash
sudo cp -a /var/www/templates/indextpl.min.html.bak-rbmenubtn-20260312-1 /var/www/templates/indextpl.min.html
```

## Post-Rollback Check
```bash
curl -s http://localhost/index.php | tr '\n' ' ' | sed 's/></>\n</g' | grep -n 'radio-browser-link-btn'
```

Expected: no output.
