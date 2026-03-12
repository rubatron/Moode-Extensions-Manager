#!/usr/bin/env bash
set -euo pipefail

DOCROOT="${EXTMGR_DOCROOT:-/var/www/html}"
SRC_ROOT="/workspace/ext-mgr"

mkdir -p "$DOCROOT/extensions/assets/js" "$DOCROOT/extensions/assets/css"

install -m 0644 "$SRC_ROOT/ext-mgr.php" "$DOCROOT/extensions/ext-mgr.php"
install -m 0644 "$SRC_ROOT/ext-mgr-api.php" "$DOCROOT/extensions/ext-mgr-api.php"
install -m 0644 "$SRC_ROOT/ext-mgr.meta.json" "$DOCROOT/extensions/ext-mgr.meta.json"
install -m 0644 "$SRC_ROOT/ext-mgr.release.json" "$DOCROOT/extensions/ext-mgr.release.json"
install -m 0644 "$SRC_ROOT/ext-mgr.version" "$DOCROOT/extensions/ext-mgr.version"
install -m 0644 "$SRC_ROOT/ext-mgr.integrity.json" "$DOCROOT/extensions/ext-mgr.integrity.json"
install -m 0644 "$SRC_ROOT/registry.json" "$DOCROOT/extensions/registry.json"
install -m 0644 "$SRC_ROOT/assets/js/ext-mgr.js" "$DOCROOT/extensions/assets/js/ext-mgr.js"
install -m 0644 "$SRC_ROOT/assets/js/ext-mgr-modal-fix.js" "$DOCROOT/extensions/assets/js/ext-mgr-modal-fix.js"
install -m 0644 "$SRC_ROOT/assets/css/ext-mgr.css" "$DOCROOT/extensions/assets/css/ext-mgr.css"
install -m 0644 "$SRC_ROOT/assets/js/ext-mgr-hover-menu.js" "$DOCROOT/extensions/ext-mgr-hover-menu.js"

ln -sfn /var/www/html/extensions/ext-mgr.php /var/www/html/ext-mgr.php
ln -sfn /var/www/html/extensions/ext-mgr-api.php /var/www/html/ext-mgr-api.php

exec "$@"
