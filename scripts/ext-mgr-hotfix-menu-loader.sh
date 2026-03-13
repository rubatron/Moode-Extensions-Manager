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
GUARD_JS="/var/www/extensions/sys/assets/js/ext-mgr-configure-modal-guard.js"
HOVER_SCRIPT_TAG='<script src="/extensions/sys/assets/js/ext-mgr-hover-menu.js" defer></script>'
GUARD_SCRIPT_TAG='<script src="/extensions/sys/assets/js/ext-mgr-configure-modal-guard.js" defer></script>'
STAMP="$(date +%Y%m%d-%H%M%S)"

backup_if_exists() {
  local f="$1"
  if [[ -f "$f" ]]; then
    $SUDO cp -a "$f" "$f.bak-extmgr-hotfix-$STAMP"
  fi
}

restore_latest_hotfix_backup_if_present() {
  local f="$1"
  local latest
  latest="$(ls -1t "$f".bak-extmgr-hotfix-* 2>/dev/null | head -n 1 || true)"
  if [[ -n "$latest" && -f "$latest" ]]; then
    echo "restoring latest backup for $f from $latest"
    $SUDO cp -a "$latest" "$f"
  fi
}

write_configure_guard_js() {
  $SUDO mkdir -p "$(dirname "$GUARD_JS")"
  cat <<'JS' | $SUDO tee "$GUARD_JS" >/dev/null
(function (window, document) {
  'use strict';

  function openConfigureModal(e) {
    var modal = document.getElementById('configure-modal');
    if (!modal) {
      return;
    }

    if (e && typeof e.preventDefault === 'function') {
      e.preventDefault();
      e.stopPropagation();
    }

    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
      window.jQuery(modal).removeClass('hide').modal('show');
      return;
    }

    modal.classList.remove('hide');
    modal.style.display = 'block';
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
  }

  document.addEventListener('click', function (e) {
    var t = e.target && e.target.closest
      ? e.target.closest('a[href="#configure-modal"], a[href*="configure-modal"], [data-target="#configure-modal"], [href*="open-configure"]')
      : null;
    if (!t) {
      return;
    }
    openConfigureModal(e);
  }, true);
})(window, document);
JS
  $SUDO chmod 0644 "$GUARD_JS"
}

inject_scripts_safe() {
  local target="$1"
  $SUDO python3 - "$target" "$HOVER_SCRIPT_TAG" "$GUARD_SCRIPT_TAG" <<'PY'
from pathlib import Path
import sys

path = Path(sys.argv[1])
tags = [sys.argv[2], sys.argv[3]]

if not path.exists():
    print(f"skip missing: {path}")
    raise SystemExit(0)

s = path.read_text(encoding='utf-8', errors='ignore')
orig = s

for tag in tags:
    if tag in s:
        continue
    if '</body>' in s:
        s = s.replace('</body>', tag + '\n</body>', 1)
    elif '</html>' in s:
        s = s.replace('</html>', tag + '\n</html>', 1)
    elif '</head>' in s:
        s = s.replace('</head>', tag + '\n</head>', 1)
    else:
        s += '\n' + tag + '\n'

if s != orig:
    path.write_text(s, encoding='utf-8')
    print(f"patched: {path}")
else:
    print(f"no-change: {path}")
PY
}

echo "[1/6] restore prior hotfix backup where available (safe rollback)"
restore_latest_hotfix_backup_if_present "$INDEX_TEMPLATE_FILE"
restore_latest_hotfix_backup_if_present "$HEADER_FILE"
restore_latest_hotfix_backup_if_present "$FOOTER_MIN_FILE"
restore_latest_hotfix_backup_if_present "$FOOTER_FILE"

echo "[2/6] backup current files"
backup_if_exists "$INDEX_TEMPLATE_FILE"
backup_if_exists "$HEADER_FILE"
backup_if_exists "$FOOTER_MIN_FILE"
backup_if_exists "$FOOTER_FILE"

echo "[3/6] write configure modal guard js"
write_configure_guard_js

echo "[4/6] inject helper scripts safely into shell files"
inject_scripts_safe "$INDEX_TEMPLATE_FILE"
inject_scripts_safe "$HEADER_FILE"
inject_scripts_safe "$FOOTER_MIN_FILE"
inject_scripts_safe "$FOOTER_FILE"

echo "[5/6] quick verify"
if command -v curl >/dev/null 2>&1; then
  if curl -s http://localhost/ | grep -q 'ext-mgr-hover-menu.js'; then
    echo "OK: hover helper script visible in homepage output"
  else
    echo "WARN: hover helper script still not visible in homepage output"
  fi

  if curl -s http://localhost/ | grep -q 'ext-mgr-configure-modal-guard.js'; then
    echo "OK: configure modal guard script visible in homepage output"
  else
    echo "WARN: configure modal guard script not visible in homepage output"
  fi
else
  echo "INFO: curl not available, skipped HTTP verify"
fi

echo "[6/6] done"
