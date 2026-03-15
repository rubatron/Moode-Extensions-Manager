#!/usr/bin/env bash
set -euo pipefail

echo "=== moOde Development Environment (Apache) ==="
echo "Started: $(date)"

DOCROOT="/var/www/html"
SRC_ROOT="/ext-mgr-src"

# Create directories
mkdir -p "$DOCROOT/extensions/sys/assets/js"
mkdir -p "$DOCROOT/extensions/sys/assets/css"
mkdir -p "$DOCROOT/extensions/sys/assets/data"
mkdir -p "$DOCROOT/extensions/sys/scripts"
mkdir -p "$DOCROOT/extensions/sys/logs"
mkdir -p "$DOCROOT/extensions/installed"
mkdir -p "$DOCROOT/extensions/cache"
mkdir -p /var/local/www/db

# Create database if not exists
DB_PATH="/var/local/www/db/moode-sqlite3.db"
if [ ! -f "$DB_PATH" ]; then
    echo "Creating development database..."
    sqlite3 "$DB_PATH" << 'SQL'
CREATE TABLE IF NOT EXISTS cfg_system (
    id INTEGER PRIMARY KEY,
    param TEXT NOT NULL,
    value TEXT
);
INSERT OR REPLACE INTO cfg_system (param, value) VALUES
    ('hostname', 'moode-dev'),
    ('extmgr_version', '1.0.0');
SQL
    chown www-data:www-data "$DB_PATH"
fi

# Copy source files (copy instead of symlink for reliability)
if [ -d "$SRC_ROOT" ]; then
    echo "Copying source files..."

    # Main PHP files - copy with proper permissions
    cp -f "$SRC_ROOT/ext-mgr.php" "$DOCROOT/ext-mgr.php" 2>/dev/null || true
    cp -f "$SRC_ROOT/ext-mgr-api.php" "$DOCROOT/ext-mgr-api.php" 2>/dev/null || true
    cp -f "$SRC_ROOT/ext-mgr-shell-bridge.php" "$DOCROOT/ext-mgr-shell-bridge.php" 2>/dev/null || true

    # Metadata files
    cp -f "$SRC_ROOT/ext-mgr.meta.json" "$DOCROOT/extensions/sys/" 2>/dev/null || true
    cp -f "$SRC_ROOT/ext-mgr.release.json" "$DOCROOT/extensions/sys/" 2>/dev/null || true
    cp -f "$SRC_ROOT/ext-mgr.version" "$DOCROOT/extensions/sys/" 2>/dev/null || true
    cp -f "$SRC_ROOT/ext-mgr.integrity.json" "$DOCROOT/extensions/sys/" 2>/dev/null || true
    cp -f "$SRC_ROOT/registry.json" "$DOCROOT/extensions/sys/" 2>/dev/null || true

    # Assets
    [ -d "$SRC_ROOT/assets/js" ] && cp -f "$SRC_ROOT/assets/js/"*.js "$DOCROOT/extensions/sys/assets/js/" 2>/dev/null || true
    [ -d "$SRC_ROOT/assets/css" ] && cp -f "$SRC_ROOT/assets/css/"*.css "$DOCROOT/extensions/sys/assets/css/" 2>/dev/null || true

    # Scripts
    [ -d "$SRC_ROOT/scripts" ] && cp -f "$SRC_ROOT/scripts/"*.sh "$DOCROOT/extensions/sys/scripts/" 2>/dev/null || true

    echo "Source files copied"
else
    echo "WARNING: Source directory $SRC_ROOT not found!"
fi

# Set permissions - make everything readable by Apache
chown -R www-data:www-data "$DOCROOT"
chmod -R 755 "$DOCROOT"
chmod 644 "$DOCROOT"/*.php 2>/dev/null || true
chown -R www-data:www-data /var/local/www
chmod -R 775 /var/local/www

echo ""
echo "=== Environment Ready (Apache) ==="
echo "Extension Manager: http://localhost:8080/ext-mgr.php"
echo ""

# Debug info
echo "Files in docroot:"
ls -la "$DOCROOT"/*.php 2>/dev/null || echo "No PHP files found"
echo ""

exec "$@"
