#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$SCRIPT_DIR"

SRC_PAGE="$PROJECT_ROOT/ext-mgr.php"
SRC_API="$PROJECT_ROOT/ext-mgr-api.php"
SRC_META="$PROJECT_ROOT/ext-mgr.meta.json"
SRC_REGISTRY="$PROJECT_ROOT/registry.json"
SRC_JS="$PROJECT_ROOT/assets/js/ext-mgr.js"

TARGET_EXT_DIR="/var/www/extensions"
TARGET_JS_DIR="$TARGET_EXT_DIR/assets/js"
TARGET_PAGE="$TARGET_EXT_DIR/ext-mgr.php"
TARGET_API="$TARGET_EXT_DIR/ext-mgr-api.php"
TARGET_META="$TARGET_EXT_DIR/ext-mgr.meta.json"
TARGET_REGISTRY="$TARGET_EXT_DIR/registry.json"
TARGET_JS="$TARGET_JS_DIR/ext-mgr.js"
SYMLINK_HELPER="/usr/local/sbin/ext-mgr-repair-symlink"
SYMLINK_SUDOERS="/etc/sudoers.d/ext-mgr"
SECURITY_GROUP="moode-extmgr"
SECURITY_USER="moode-extmgrusr"
WEB_USER="www-data"

HEADER_FILE="/var/www/header.php"
RB_FILE="/var/www/extensions/installed/radio-browser/radio-browser.php"
RB_JS_FILE="/var/www/extensions/installed/radio-browser/assets/radio-browser-modal-fix.js"

SKIP_MODULE1=0
if [[ "${1:-}" == "--skip-module1" ]]; then
    SKIP_MODULE1=1
fi

if [[ "${EUID}" -eq 0 ]]; then
    SUDO=""
else
    SUDO="sudo"
fi

require_file() {
    local path="$1"
    if [[ ! -f "$path" ]]; then
        echo "ERROR: required file not found: $path" >&2
        exit 1
    fi
}

require_file "$SRC_PAGE"
require_file "$SRC_API"
require_file "$SRC_META"
require_file "$SRC_REGISTRY"
require_file "$SRC_JS"

if [[ "$SKIP_MODULE1" -eq 0 ]]; then
    require_file "$HEADER_FILE"
    require_file "$RB_FILE"
fi

STAMP="$(date +%Y%m%d-%H%M%S)"

echo "[1/8] Preparing target directories..."
$SUDO mkdir -p "$TARGET_EXT_DIR" "$TARGET_JS_DIR"

echo "[2/8] Backing up existing ext-mgr files (if present)..."
for f in "$TARGET_PAGE" "$TARGET_API" "$TARGET_META" "$TARGET_REGISTRY" "$TARGET_JS"; do
    if [[ -f "$f" ]]; then
        $SUDO cp -a "$f" "$f.bak-extmgr-$STAMP"
    fi
done

echo "[3/8] Installing ext-mgr page/api/metadata/js files..."
$SUDO install -o www-data -g www-data -m 0644 "$SRC_PAGE" "$TARGET_PAGE"
$SUDO install -o www-data -g www-data -m 0644 "$SRC_API" "$TARGET_API"
$SUDO install -o www-data -g www-data -m 0644 "$SRC_META" "$TARGET_META"

if [[ -f "$TARGET_REGISTRY" ]]; then
    echo "Existing registry detected, preserving current state at $TARGET_REGISTRY"
else
    $SUDO install -o www-data -g www-data -m 0644 "$SRC_REGISTRY" "$TARGET_REGISTRY"
fi

$SUDO install -o www-data -g www-data -m 0644 "$SRC_JS" "$TARGET_JS"

echo "[4/8] Creating root shortcuts..."
$SUDO ln -sfn /var/www/extensions/ext-mgr.php /var/www/ext-mgr.php
$SUDO ln -sfn /var/www/extensions/ext-mgr-api.php /var/www/ext-mgr-api.php

echo "[4.1/8] Installing privileged symlink repair helper..."
if ! getent group "$SECURITY_GROUP" >/dev/null 2>&1; then
    $SUDO groupadd --system "$SECURITY_GROUP"
fi

if ! id -u "$SECURITY_USER" >/dev/null 2>&1; then
    $SUDO useradd --system --no-create-home --shell /usr/sbin/nologin --gid "$SECURITY_GROUP" "$SECURITY_USER"
fi

$SUDO usermod -aG "$SECURITY_GROUP" "$WEB_USER" || true
$SUDO usermod -aG "$WEB_USER" "$SECURITY_USER" || true

cat <<'SH' | $SUDO tee "$SYMLINK_HELPER" > /dev/null
#!/usr/bin/env bash
set -euo pipefail

EXT_ID="${1:-}"
ENTRY_HINT="${2:-}"

if [[ -z "$EXT_ID" || ! "$EXT_ID" =~ ^[a-zA-Z0-9._-]+$ ]]; then
    echo "invalid-extension-id" >&2
    exit 2
fi

is_safe_rel() {
    local p="$1"
    [[ -n "$p" && "$p" != /* && "$p" != *..* ]]
}

INSTALLED_DIR="/var/www/extensions/installed/${EXT_ID}"
if [[ ! -d "$INSTALLED_DIR" ]]; then
    echo "installed-dir-not-found" >&2
    exit 3
fi

MANIFEST_MAIN=""
if [[ -f "$INSTALLED_DIR/manifest.json" ]]; then
    MANIFEST_MAIN="$(php -r '$j=json_decode(@file_get_contents($argv[1]), true); if(is_array($j) && isset($j["main"]) && is_string($j["main"])) echo trim($j["main"]);' "$INSTALLED_DIR/manifest.json" 2>/dev/null || true)"
fi

TARGET=""
if is_safe_rel "$MANIFEST_MAIN" && [[ -f "$INSTALLED_DIR/$MANIFEST_MAIN" ]]; then
    TARGET="$INSTALLED_DIR/$MANIFEST_MAIN"
elif is_safe_rel "$ENTRY_HINT" && [[ -f "$INSTALLED_DIR/$ENTRY_HINT" ]]; then
    TARGET="$INSTALLED_DIR/$ENTRY_HINT"
elif [[ -f "$INSTALLED_DIR/${EXT_ID}.php" ]]; then
    TARGET="$INSTALLED_DIR/${EXT_ID}.php"
elif [[ -f "$INSTALLED_DIR/index.php" ]]; then
    TARGET="$INSTALLED_DIR/index.php"
else
    echo "entry-not-found" >&2
    exit 4
fi

LINK_PATH="/var/www/${EXT_ID}.php"
ln -sfn "$TARGET" "$LINK_PATH"
chown -h www-data:www-data "$LINK_PATH" 2>/dev/null || true

echo "$LINK_PATH|$TARGET"
SH

$SUDO chown root:"$SECURITY_GROUP" "$SYMLINK_HELPER"
$SUDO chmod 0750 "$SYMLINK_HELPER"

cat <<EOF | $SUDO tee "$SYMLINK_SUDOERS" > /dev/null
%$SECURITY_GROUP ALL=(root) NOPASSWD: $SYMLINK_HELPER *
EOF
$SUDO chown root:root "$SYMLINK_SUDOERS"
$SUDO chmod 0440 "$SYMLINK_SUDOERS"

echo "[5/8] Validating ext-mgr syntax..."
php -l "$TARGET_PAGE"
php -l "$TARGET_API"

echo "[6/8] Applying Module 1 (radio-browser modal fix)..."
if [[ "$SKIP_MODULE1" -eq 1 ]]; then
    echo "Skipped Module 1 integration due to --skip-module1"
else
    $SUDO cp -a "$HEADER_FILE" "$HEADER_FILE.bak-module1-$STAMP"
    $SUDO cp -a "$RB_FILE" "$RB_FILE.bak-module1-$STAMP"

    # Make header section patch tolerant to quote-style differences across moOde versions.
    if ! grep -Eq "\$section[[:space:]]*==[[:space:]]*['\"]radio-browser['\"]" "$HEADER_FILE"; then
        $SUDO sed -i "s/if (\\\$section == 'index')/if (\\\$section == 'index' || \\\$section == 'radio-browser')/" "$HEADER_FILE" || true
        $SUDO sed -i 's/if (\$section == "index")/if (\$section == "index" || \$section == "radio-browser")/' "$HEADER_FILE" || true

        if ! grep -Eq "\$section[[:space:]]*==[[:space:]]*['\"]radio-browser['\"]" "$HEADER_FILE"; then
            echo "WARN: Could not patch header.php section condition automatically. Configure modal integration may be degraded." >&2
        fi
    fi

    if ! grep -q "radio-browser-modal-fix.js" "$RB_FILE"; then
        if grep -q 'radio-browser\.js" defer<\/script>' "$RB_FILE"; then
            $SUDO sed -i '/radio-browser\.js" defer<\/script>/a echo '\''<script src="'\'' . $extAssetsPath . '\''/radio-browser-modal-fix.js" defer><\/script>'\'' . "\\n";' "$RB_FILE"
        else
            # Fallback for template variations: inject before footer include.
            $SUDO sed -i "/include('\/var\/www\/footer\.min\.php');/i echo '<script src=\"' . \$extAssetsPath . '\/radio-browser-modal-fix.js\" defer><\/script>' . \"\\n\";" "$RB_FILE"
        fi
    fi

    cat <<'JS' | $SUDO tee "$RB_JS_FILE" > /dev/null
(function (window, document) {
    'use strict';

    function initFix($) {
        if (!$ || !$.fn || !$.fn.modal) {
            return;
        }

        $(document).on('click.rbConfigureModalFix', 'a[href="#configure-modal"], a[href*="configure-modal"], [data-target="#configure-modal"]', function (e) {
            var $modal = $('#configure-modal');
            if (!$modal.length) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();
            $modal.removeClass('hide').modal('show');
        });

        if (window.location.hash === '#configure-modal' || window.location.hash.indexOf('configure-modal') !== -1) {
            setTimeout(function () {
                var $modal = $('#configure-modal');
                if ($modal.length) {
                    $modal.removeClass('hide').modal('show');
                }
            }, 0);
        }
    }

    if (window.jQuery) {
        initFix(window.jQuery);
        return;
    }

    document.addEventListener('DOMContentLoaded', function () {
        initFix(window.jQuery || window.$);
    });
})(window, document);
JS

    $SUDO chown www-data:www-data "$RB_JS_FILE"
    $SUDO chmod 0644 "$RB_JS_FILE"

    php -l "$HEADER_FILE"
    php -l "$RB_FILE"
fi

echo "[7/8] Completed installation."
echo "Installed: $TARGET_PAGE, $TARGET_API, $TARGET_JS, $TARGET_META"
echo "Root endpoints: /ext-mgr.php and /ext-mgr-api.php"

echo "[8/8] Done."
