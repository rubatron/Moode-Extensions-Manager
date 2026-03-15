#!/usr/bin/env bash
set -euo pipefail

HEADER_FILE="/var/www/header.php"
RB_FILE="/var/www/extensions/installed/radio-browser/radio-browser.php"
RB_JS_FILE="/var/www/extensions/installed/radio-browser/assets/radio-browser-modal-fix.js"

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

require_file "$HEADER_FILE"
require_file "$RB_FILE"

STAMP="$(date +%Y%m%d-%H%M%S)"

echo "[1/6] Creating backups..."
$SUDO cp -a "$HEADER_FILE" "$HEADER_FILE.bak-rbmenu-$STAMP"
$SUDO cp -a "$RB_FILE" "$RB_FILE.bak-modalfix-$STAMP"

echo "[2/6] Updating header section condition..."
$SUDO sed -i "s/if (\$section == 'index')/if (\$section == 'index' || \$section == 'radio-browser')/" "$HEADER_FILE"

echo "[3/6] Ensuring modal fix script include in radio-browser.php..."
if ! grep -q "radio-browser-modal-fix.js" "$RB_FILE"; then
    $SUDO sed -i '/radio-browser\.js" defer<\/script>/a echo '\''<script src="'\'' . $extAssetsPath . '\''/radio-browser-modal-fix.js" defer><\/script>'\'' . "\\n";' "$RB_FILE"
fi

echo "[4/6] Writing modal fallback script..."
cat <<'JS' | $SUDO tee "$RB_JS_FILE" > /dev/null
(function (window, document) {
    'use strict';

    function initFix($) {
        if (!$ || !$.fn || !$.fn.modal) {
            return;
        }

        // On radio-browser page, force Bootstrap modal open for Configure links.
        $(document).on('click.rbConfigureModalFix', 'a[href="#configure-modal"]', function (e) {
            var $modal = $('#configure-modal');
            if (!$modal.length) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();
            $modal.removeClass('hide').modal('show');
        });

        // Support direct hash load: /radio-browser.php#configure-modal
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

echo "[5/6] Validating PHP syntax..."
php -l "$HEADER_FILE"
php -l "$RB_FILE"

echo "[6/6] Verification snippets..."
nl -ba "$HEADER_FILE" | sed -n '145,148p'
nl -ba "$RB_FILE" | sed -n '53,57p'

echo
echo "OK: Radio Browser modal menu integration installed."
echo "Backups:"
echo "- $HEADER_FILE.bak-rbmenu-$STAMP"
echo "- $RB_FILE.bak-modalfix-$STAMP"
echo "Next: hard refresh your browser (Ctrl+F5) and test Configure on radio-browser.php."
