#!/usr/bin/env bash
set -euo pipefail

DOCROOT="${EXTMGR_DOCROOT:-/var/www/html}"
SRC_ROOT="/workspace/ext-mgr"

mkdir -p "$DOCROOT/extensions/sys/assets/js" "$DOCROOT/extensions/sys/assets/css" "$DOCROOT/extensions/sys/scripts"

install -m 0644 "$SRC_ROOT/ext-mgr.php" "$DOCROOT/extensions/sys/ext-mgr.php"
install -m 0644 "$SRC_ROOT/ext-mgr-api.php" "$DOCROOT/extensions/sys/ext-mgr-api.php"
install -m 0644 "$SRC_ROOT/ext-mgr.meta.json" "$DOCROOT/extensions/sys/ext-mgr.meta.json"
install -m 0644 "$SRC_ROOT/ext-mgr.release.json" "$DOCROOT/extensions/sys/ext-mgr.release.json"
install -m 0644 "$SRC_ROOT/ext-mgr.version" "$DOCROOT/extensions/sys/ext-mgr.version"
install -m 0644 "$SRC_ROOT/ext-mgr.integrity.json" "$DOCROOT/extensions/sys/ext-mgr.integrity.json"
install -m 0644 "$SRC_ROOT/registry.json" "$DOCROOT/extensions/sys/registry.json"
install -m 0644 "$SRC_ROOT/assets/js/ext-mgr.js" "$DOCROOT/extensions/sys/assets/js/ext-mgr.js"
install -m 0644 "$SRC_ROOT/assets/js/ext-mgr-modal-fix.js" "$DOCROOT/extensions/sys/assets/js/ext-mgr-modal-fix.js"
install -m 0644 "$SRC_ROOT/assets/css/ext-mgr.css" "$DOCROOT/extensions/sys/assets/css/ext-mgr.css"
install -m 0644 "$SRC_ROOT/assets/js/ext-mgr-hover-menu.js" "$DOCROOT/extensions/sys/assets/js/ext-mgr-hover-menu.js"

ln -sfn /var/www/html/extensions/sys/ext-mgr.php /var/www/html/ext-mgr.php
ln -sfn /var/www/html/extensions/sys/ext-mgr-api.php /var/www/html/ext-mgr-api.php

exec "$@"
