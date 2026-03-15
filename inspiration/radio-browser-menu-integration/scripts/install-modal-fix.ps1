param(
    [string]$Host = "moode1.local",
    [string]$User = "pi"
)

$ErrorActionPreference = "Stop"

$remote = "$User@$Host"
$tmpDir = Join-Path $env:TEMP "rb-modal-installer"
$null = New-Item -ItemType Directory -Force -Path $tmpDir

$remoteScriptLocal = Join-Path $tmpDir "rb-apply-modal-fix.sh"
$modalFixLocal = Join-Path $tmpDir "radio-browser-modal-fix.js"

$remoteScript = @'
set -e

HEADER_FILE="/var/www/header.php"
RB_FILE="/var/www/extensions/installed/radio-browser/radio-browser.php"
RB_JS_FILE="/var/www/extensions/installed/radio-browser/assets/radio-browser-modal-fix.js"

STAMP=$(date +%Y%m%d-%H%M%S)

sudo cp -a "$HEADER_FILE" "$HEADER_FILE.bak-rbmenu-$STAMP"
sudo cp -a "$RB_FILE" "$RB_FILE.bak-modalfix-$STAMP"

# 1) Enable Configure menu for radio-browser section.
sudo sed -i "s/if (\$section == 'index')/if (\$section == 'index' || \$section == 'radio-browser')/" "$HEADER_FILE"

# 2) Include modal fix script once.
if ! grep -q "radio-browser-modal-fix.js" "$RB_FILE"; then
  sudo sed -i '/radio-browser\.js" defer<\/script>/a echo '\''<script src="'\'' . $extAssetsPath . '\''/radio-browser-modal-fix.js" defer><\/script>'\'' . "\\n";' "$RB_FILE"
fi

# 3) Install JS fallback (uploaded separately to /tmp).
sudo install -o www-data -g www-data -m 0644 /tmp/radio-browser-modal-fix.js "$RB_JS_FILE"

# 4) Validate syntax.
php -l "$HEADER_FILE"
php -l "$RB_FILE"

echo "OK: Modal menu integration installed"
'@

$modalFixJs = @'
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
'@

[System.IO.File]::WriteAllText($remoteScriptLocal, $remoteScript, [System.Text.UTF8Encoding]::new($false))
[System.IO.File]::WriteAllText($modalFixLocal, $modalFixJs, [System.Text.UTF8Encoding]::new($false))

Write-Host "Uploading installer payload to $remote ..."
scp $remoteScriptLocal "${remote}:/tmp/rb-apply-modal-fix.sh" | Out-Host
scp $modalFixLocal "${remote}:/tmp/radio-browser-modal-fix.js" | Out-Host

Write-Host "Applying fix on remote host ..."
ssh $remote "bash /tmp/rb-apply-modal-fix.sh" | Out-Host

Write-Host "Verifying output snippets ..."
ssh $remote "nl -ba /var/www/header.php | sed -n '145,148p'" | Out-Host
ssh $remote "nl -ba /var/www/extensions/installed/radio-browser/radio-browser.php | sed -n '53,57p'" | Out-Host

Write-Host "Done. Hard refresh your browser (Ctrl+F5) and test Configure on radio-browser.php."
