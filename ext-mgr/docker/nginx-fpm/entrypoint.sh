#!/bin/bash
set -e

echo "=== moOde Development Environment (NGINX + PHP-FPM) ==="
echo "Started: $(date)"

# Create required directories
mkdir -p /run/php
mkdir -p /var/log/supervisor
mkdir -p /var/log/nginx
mkdir -p /var/www/extensions/sys/assets/{js,css,data}
mkdir -p /var/www/extensions/sys/scripts
mkdir -p /var/www/extensions/sys/logs
mkdir -p /var/www/extensions/installed
mkdir -p /var/www/extensions/cache
mkdir -p /var/local/www/db

# Create/update moOde SQLite database
DB_PATH="/var/local/www/db/moode-sqlite3.db"
if [ ! -f "$DB_PATH" ]; then
    echo "Creating moOde database..."

    # Check for SQL template from moOde source
    SQL_TEMPLATE="/opt/moode-src/var/local/www/db/moode-sqlite3.db.sql"
    if [ -f "$SQL_TEMPLATE" ]; then
        echo "Using moOde database template..."
        sqlite3 "$DB_PATH" < "$SQL_TEMPLATE"
    else
        echo "Creating minimal development database..."
        sqlite3 "$DB_PATH" << 'SQL'
-- Minimal moOde database for ext-mgr development
CREATE TABLE IF NOT EXISTS cfg_system (
    id INTEGER PRIMARY KEY,
    param TEXT NOT NULL,
    value TEXT
);

-- Insert some common moOde settings
INSERT OR REPLACE INTO cfg_system (param, value) VALUES
    ('hostname', 'moode-dev'),
    ('timezone', 'UTC'),
    ('wrkready', '1'),
    ('cardnum', '0'),
    ('volknob', '50'),
    ('volmute', '0'),
    ('extmgr_version', '1.0.0');

-- Create ext-mgr specific tables
CREATE TABLE IF NOT EXISTS cfg_extmgr (
    id INTEGER PRIMARY KEY,
    param TEXT NOT NULL,
    value TEXT
);

CREATE TABLE IF NOT EXISTS cfg_extensions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ext_id TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    version TEXT,
    enabled INTEGER DEFAULT 1,
    config TEXT,
    installed_at TEXT DEFAULT CURRENT_TIMESTAMP
);
SQL
    fi

    chown www-data:www-data "$DB_PATH"
    chmod 664 "$DB_PATH"
    echo "Database created at: $DB_PATH"
else
    echo "Using existing database: $DB_PATH"
fi

# Link ext-mgr source files (mounted from host)
EXT_MGR_SRC="/ext-mgr-src"
EXT_MGR_DEST="/var/www/extensions/sys"

if [ -d "$EXT_MGR_SRC" ]; then
    echo "Linking ext-mgr source files..."

    # PHP files
    for phpfile in "$EXT_MGR_SRC"/*.php; do
        if [ -f "$phpfile" ]; then
            ln -sf "$phpfile" /var/www/
        fi
    done

    # JavaScript files
    if [ -d "$EXT_MGR_SRC/js" ]; then
        for jsfile in "$EXT_MGR_SRC"/js/*.js; do
            [ -f "$jsfile" ] && ln -sf "$jsfile" "$EXT_MGR_DEST/assets/js/"
        done
    fi

    # CSS files
    if [ -d "$EXT_MGR_SRC/css" ]; then
        for cssfile in "$EXT_MGR_SRC"/css/*.css; do
            [ -f "$cssfile" ] && ln -sf "$cssfile" "$EXT_MGR_DEST/assets/css/"
        done
    fi

    # Shell scripts
    if [ -d "$EXT_MGR_SRC/scripts" ]; then
        for script in "$EXT_MGR_SRC"/scripts/*.sh; do
            [ -f "$script" ] && ln -sf "$script" "$EXT_MGR_DEST/scripts/"
        done
    fi

    # Data files
    if [ -d "$EXT_MGR_SRC/data" ]; then
        for datafile in "$EXT_MGR_SRC"/data/*; do
            [ -f "$datafile" ] && ln -sf "$datafile" "$EXT_MGR_DEST/assets/data/"
        done
    fi

    echo "Source files linked successfully"
fi

# Set permissions
chown -R www-data:www-data /var/www/extensions
chmod -R 775 /var/www/extensions
chown -R www-data:www-data /var/local/www
chmod -R 775 /var/local/www

# Test NGINX config
echo "Testing NGINX configuration..."
nginx -t

echo ""
echo "=== Environment Ready ==="
echo "Web server: http://localhost/"
echo "Extension Manager: http://localhost/ext-mgr.php"
echo "Database: $DB_PATH"
echo ""

# Run supervisord (starts both NGINX and PHP-FPM)
exec "$@"
