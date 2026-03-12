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

echo "[5/8] Validating ext-mgr syntax..."
php -l "$TARGET_PAGE"
php -l "$TARGET_API"

echo "[6/8] Applying Module 1 (radio-browser modal fix)..."
if [[ "$SKIP_MODULE1" -eq 1 ]]; then
    echo "Skipped Module 1 integration due to --skip-module1"
else
    $SUDO cp -a "$HEADER_FILE" "$HEADER_FILE.bak-module1-$STAMP"
    $SUDO cp -a "$RB_FILE" "$RB_FILE.bak-module1-$STAMP"

    $SUDO sed -i "s/if (\$section == 'index')/if (\$section == 'index' || \$section == 'radio-browser')/" "$HEADER_FILE"

    if ! grep -q "radio-browser-modal-fix.js" "$RB_FILE"; then
        $SUDO sed -i '/radio-browser\.js" defer<\/script>/a echo '\''<script src="'\'' . $extAssetsPath . '\''/radio-browser-modal-fix.js" defer><\/script>'\'' . "\\n";' "$RB_FILE"
    fi

    cat <<'JS' | $SUDO tee "$RB_JS_FILE" > /dev/null
(function (window, document) {
    'use strict';

    function initFix($) {
        if (!$ || !$.fn || !$.fn.modal) {
            return;
        }

        $(document).on('click.rbConfigureModalFix', 'a[href="#configure-modal"]', function (e) {
            var $modal = $('#configure-modal');
            if (!$modal.length) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();
            $modal.removeClass('hide').modal('show');
        });

        if (window.location.hash === '#configure-modal') {
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
