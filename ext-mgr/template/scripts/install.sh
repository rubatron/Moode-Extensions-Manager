#!/usr/bin/env bash
set -euo pipefail

EXT_ID='template-extension'
ROOT="${EXT_MGR_EXTENSION_ROOT:-/var/www/extensions/installed/$EXT_ID}"
SERVICE_NAME="ext-${EXT_ID}.service"
SERVICE_FILE="$ROOT/scripts/$SERVICE_NAME"

case "$ROOT" in
  /var/www/extensions/installed/*) ;;
  *)
    echo "[$EXT_ID] skip install script: unsafe ROOT='$ROOT'"
    exit 0
    ;;
esac

# Keep install hook non-destructive by default: no extra folder creation here.
if [[ -f "$SERVICE_FILE" && -d /etc/systemd/system && -w /etc/systemd/system && -x /usr/bin/systemctl ]]; then
  install -m 0644 "$SERVICE_FILE" "/etc/systemd/system/$SERVICE_NAME"
  systemctl daemon-reload
  systemctl enable --now moode-extmgr.service >/dev/null 2>&1 || true
  systemctl enable --now "$SERVICE_NAME" >/dev/null 2>&1 || true
fi

echo "[$EXT_ID] default install completed"
