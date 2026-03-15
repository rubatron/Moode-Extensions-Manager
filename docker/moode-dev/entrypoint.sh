#!/usr/bin/env bash
set -euo pipefail

SRC_ROOT="${EXTMGR_SRC:-/workspace/ext-mgr}"

# Ensure directories exist (moOde source already provides /var/www structure)
mkdir -p /var/www/extensions/sys/assets/js
mkdir -p /var/www/extensions/sys/assets/css
mkdir -p /var/www/extensions/sys/scripts
mkdir -p /var/www/extensions/sys/logs
mkdir -p /var/www/extensions/installed
mkdir -p /var/www/extensions/cache

# Create stub for moOde database if not exists (ext-mgr doesn't need full DB)
if [[ ! -f /var/local/www/db/moode-sqlite3.db ]]; then
  mkdir -p /var/local/www/db
  sqlite3 /var/local/www/db/moode-sqlite3.db "CREATE TABLE IF NOT EXISTS cfg_system (id INTEGER PRIMARY KEY, param TEXT, value TEXT);"
  sqlite3 /var/local/www/db/moode-sqlite3.db "INSERT OR IGNORE INTO cfg_system (param, value) VALUES ('timezone', 'Europe/Amsterdam');"
fi

# Helper function to link or copy files
link_or_copy() {
  local src="$1"
  local dest="$2"
  local mode="${3:-0644}"
  mkdir -p "$(dirname "$dest")"
  if [[ -e "$dest" || -L "$dest" ]]; then
    rm -f "$dest"
  fi
  if [[ -f "$src" ]]; then
    ln -s "$src" "$dest" || install -m "$mode" "$src" "$dest"
  fi
}

# Link ext-mgr files from workspace into moOde structure
link_or_copy "$SRC_ROOT/ext-mgr.php" /var/www/extensions/sys/ext-mgr.php 0644
link_or_copy "$SRC_ROOT/ext-mgr-api.php" /var/www/extensions/sys/ext-mgr-api.php 0644
link_or_copy "$SRC_ROOT/ext-mgr-shell-bridge.php" /var/www/extensions/sys/ext-mgr-shell-bridge.php 0644
link_or_copy "$SRC_ROOT/ext-mgr.meta.json" /var/www/extensions/sys/ext-mgr.meta.json 0644
link_or_copy "$SRC_ROOT/ext-mgr.release.json" /var/www/extensions/sys/ext-mgr.release.json 0644
link_or_copy "$SRC_ROOT/ext-mgr.version" /var/www/extensions/sys/ext-mgr.version 0644
link_or_copy "$SRC_ROOT/ext-mgr.integrity.json" /var/www/extensions/sys/ext-mgr.integrity.json 0644

# Link JS and CSS assets
link_or_copy "$SRC_ROOT/assets/js/ext-mgr.js" /var/www/extensions/sys/assets/js/ext-mgr.js 0644
link_or_copy "$SRC_ROOT/assets/js/ext-mgr-logs.js" /var/www/extensions/sys/assets/js/ext-mgr-logs.js 0644
link_or_copy "$SRC_ROOT/assets/js/ext-mgr-hover-menu.js" /var/www/extensions/sys/assets/js/ext-mgr-hover-menu.js 0644
link_or_copy "$SRC_ROOT/assets/css/ext-mgr.css" /var/www/extensions/sys/assets/css/ext-mgr.css 0644

# Link scripts
if [[ -d "$SRC_ROOT/scripts" ]]; then
  for script in "$SRC_ROOT/scripts"/*.sh "$SRC_ROOT/scripts"/*.py "$SRC_ROOT/scripts"/*.service; do
    [[ -f "$script" ]] && link_or_copy "$script" "/var/www/extensions/sys/scripts/$(basename "$script")" 0755
  done
fi

# Initialize registry if not exists
if [[ -f "$SRC_ROOT/registry.json" ]]; then
  install -m 0644 "$SRC_ROOT/registry.json" /var/www/extensions/sys/registry.json
elif [[ ! -f /var/www/extensions/sys/registry.json ]]; then
  cat > /var/www/extensions/sys/registry.json <<'JSON'
{
  "generatedAt": "dev",
  "extensions": []
}
JSON
fi

# Create canonical route symlinks
ln -sfn /var/www/extensions/sys/ext-mgr.php /var/www/ext-mgr.php
ln -sfn /var/www/extensions/sys/ext-mgr-api.php /var/www/ext-mgr-api.php

# Fix permissions
chown -R www-data:moode-extmgr /var/www/extensions 2>/dev/null || true
chmod -R 775 /var/www/extensions 2>/dev/null || true

echo "=== moode-dev container ready ==="
echo "ext-mgr: http://localhost:8080/ext-mgr.php"
echo "moOde source: /var/www (from github.com/moode-player/moode)"
echo "Workspace: $SRC_ROOT"

exec "$@"
