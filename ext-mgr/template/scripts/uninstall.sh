#!/usr/bin/env bash
set -euo pipefail

EXT_ID='template-extension'
ROOT="/var/www/extensions/installed/$EXT_ID"
SERVICE_NAME="ext-${EXT_ID}.service"

if [[ -x /usr/bin/systemctl ]]; then
  systemctl disable --now "$SERVICE_NAME" >/dev/null 2>&1 || true
fi
if [[ -w /etc/systemd/system ]]; then
  rm -f "/etc/systemd/system/$SERVICE_NAME"
  if [[ -x /usr/bin/systemctl ]]; then
    systemctl daemon-reload >/dev/null 2>&1 || true
  fi
fi
rm -rf "$ROOT/cache" "$ROOT/data" "$ROOT/logs"

echo "[$EXT_ID] default uninstall completed"
