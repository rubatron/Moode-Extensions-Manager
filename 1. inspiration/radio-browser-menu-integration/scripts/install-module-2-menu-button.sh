#!/usr/bin/env bash
set -euo pipefail

TEMPLATE_FILE="/var/www/templates/indextpl.min.html"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_FILE="${TEMPLATE_FILE}.bak-module2-${TIMESTAMP}"

if [[ "${EUID}" -eq 0 ]]; then
    SUDO=""
else
    SUDO="sudo"
fi

if [[ ! -f "$TEMPLATE_FILE" ]]; then
    echo "ERROR: template not found: $TEMPLATE_FILE" >&2
    exit 1
fi

echo "[1/4] Creating backup..."
$SUDO cp -a "$TEMPLATE_FILE" "$BACKUP_FILE"

echo "[2/4] Applying menu button patch..."
$SUDO python3 - <<'PY'
from pathlib import Path

p = Path('/var/www/templates/indextpl.min.html')
s = p.read_text(encoding='utf-8', errors='ignore')

# Keep URL aligned with canonical route.
s = s.replace('/extensions/installed/radio-browser/radio-browser.php', '/radio-browser.php')

btn_marker = 'radio-browser-link-btn'
if btn_marker not in s:
    old = '</span></button> <button aria-label="Folder" class="btn folder-view-btn" href="#library-panel">'
    new = (
        '</span></button> '
        '<button aria-label="Radio Browser" class="btn radio-browser-link-btn menu-separator" '
        'href="#notarget" onclick="window.location.href=\'/radio-browser.php\';">'
        '<i class="fa-solid fa-sharp fa-globe"></i> Radio Browser</button> '
        '<button aria-label="Folder" class="btn folder-view-btn" href="#library-panel">'
    )
    if old not in s:
        raise SystemExit('ERROR: marker not found, template structure changed')
    s = s.replace(old, new, 1)
else:
    # Ensure existing button always points to /radio-browser.php
    s = s.replace("window.location.href='/extensions/installed/radio-browser/radio-browser.php';", "window.location.href='/radio-browser.php';")

p.write_text(s, encoding='utf-8')
print('patched')
PY

echo "[3/4] Verifying rendered output..."
curl -s http://localhost/index.php | tr '\n' ' ' | sed 's/></>\n</g' | grep -n 'radio-browser-link-btn\|window.location.href=' | head -n 5

echo "[4/4] Checking endpoint..."
ls -l /var/www/radio-browser.php

echo
echo "OK: Module 2 menu button installed."
echo "Backup: $BACKUP_FILE"
