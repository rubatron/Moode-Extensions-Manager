#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -eq 0 ]]; then
  SUDO=""
else
  SUDO="sudo"
fi

INDEX_TEMPLATE_FILE="/var/www/templates/indextpl.min.html"
HEADER_FILE="/var/www/header.php"
FOOTER_MIN_FILE="/var/www/footer.min.php"
FOOTER_FILE="/var/www/footer.php"
SCRIPT_TAG='<script src="/extensions/sys/assets/js/ext-mgr-hover-menu.js" defer></script>'
STAMP="$(date +%Y%m%d-%H%M%S)"

backup_if_exists() {
  local f="$1"
  if [[ -f "$f" ]]; then
    $SUDO cp -a "$f" "$f.bak-extmgr-hotfix-$STAMP"
  fi
}

patch_with_python() {
  local target="$1"
  local mode="$2"
  $SUDO python3 - "$target" "$mode" "$SCRIPT_TAG" <<'PY'
from pathlib import Path
import sys

path = Path(sys.argv[1])
mode = sys.argv[2]
script_tag = sys.argv[3]

if not path.exists():
    print(f"skip missing: {path}")
    raise SystemExit(0)

s = path.read_text(encoding='utf-8', errors='ignore')
orig = s

if mode == 'index':
    if script_tag not in s:
        anchor = '</span> <button aria-label="Folder" class="btn folder-view-btn" href="#library-panel">'
        if anchor in s:
            s = s.replace(anchor, '</span> ' + script_tag + ' <button aria-label="Folder" class="btn folder-view-btn" href="#library-panel">', 1)
        elif '</body>' in s:
            s = s.replace('</body>', script_tag + '\n</body>', 1)
        else:
            s += '\n' + script_tag + '\n'
elif mode == 'header':
    if script_tag not in s:
        nav_anchor = '</div><!--main-menu-->'
        if nav_anchor in s:
            s = s.replace(nav_anchor, script_tag + '\n' + nav_anchor, 1)
        elif '</head>' in s:
            s = s.replace('</head>', script_tag + '\n</head>', 1)
        elif '</body>' in s:
            s = s.replace('</body>', script_tag + '\n</body>', 1)
        else:
            s += '\n' + script_tag + '\n'
elif mode == 'footer':
    if script_tag not in s:
        if '</body>' in s:
            s = s.replace('</body>', script_tag + '\n</body>', 1)
        elif '</html>' in s:
            s = s.replace('</html>', script_tag + '\n</html>', 1)
        else:
            s += '\n' + script_tag + '\n'

if s != orig:
    path.write_text(s, encoding='utf-8')
    print(f"patched: {path}")
else:
    print(f"no-change: {path}")
PY
}

echo "[1/5] backup relevant files"
backup_if_exists "$INDEX_TEMPLATE_FILE"
backup_if_exists "$HEADER_FILE"
backup_if_exists "$FOOTER_MIN_FILE"
backup_if_exists "$FOOTER_FILE"

echo "[2/5] patch index template"
patch_with_python "$INDEX_TEMPLATE_FILE" "index"

echo "[3/5] patch header"
patch_with_python "$HEADER_FILE" "header"

echo "[4/5] patch footers"
patch_with_python "$FOOTER_MIN_FILE" "footer"
patch_with_python "$FOOTER_FILE" "footer"

echo "[5/5] quick verify"
if command -v curl >/dev/null 2>&1; then
  if curl -s http://localhost/ | grep -q 'ext-mgr-hover-menu.js'; then
    echo "OK: helper script visible in homepage output"
  else
    echo "WARN: helper script not found in homepage output yet (cache or moode variant)"
  fi
else
  echo "INFO: curl not available, skipped HTTP verify"
fi

echo "done"
