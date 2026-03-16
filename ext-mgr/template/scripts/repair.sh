#!/usr/bin/env bash
set -euo pipefail

EXT_ID='template-extension'
ROOT="${EXT_MGR_EXTENSION_ROOT:-/var/www/extensions/installed/$EXT_ID}"

case "$ROOT" in
  /var/www/extensions/installed/*) ;;
  *)
    echo "[$EXT_ID] skip repair script: unsafe ROOT='$ROOT'"
    exit 0
    ;;
esac

# Repair may recreate runtime dirs, but only inside managed extension root.
mkdir -p "$ROOT/logs" "$ROOT/cache" "$ROOT/data" 2>/dev/null || true
find "$ROOT/scripts" -maxdepth 1 -type f \( -name '*.sh' -o -name '*.service' \) -print >/dev/null 2>&1 || true

echo "[$EXT_ID] default repair completed"
