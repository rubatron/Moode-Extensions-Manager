#!/usr/bin/env bash
set -euo pipefail

# ═══════════════════════════════════════════════════════════════════════════════
# Source centralized configuration (provides EXTMGR_* variables)
# ═══════════════════════════════════════════════════════════════════════════════
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -f "$SCRIPT_DIR/ext-mgr-config.sh" ]]; then
    source "$SCRIPT_DIR/ext-mgr-config.sh"
fi

TARGET="${1:-}"
if [[ -z "$TARGET" ]]; then
  echo "missing target path" >&2
  exit 2
fi

# Restrict deletions to ext-mgr managed tree.
# Uses EXTMGR_* variables from config or defaults
EXT_ROOT="${EXTMGR_EXTENSIONS_ROOT:-/var/www/extensions}"
MOODE_ROOT="${EXTMGR_MOODE_ROOT:-/var/www}"

case "$TARGET" in
  "$EXT_ROOT"/installed/*|"$EXT_ROOT"/sys/logs/extensionslogs/*|"$EXT_ROOT"/logs/*|"$EXT_ROOT"/cache/*|"$MOODE_ROOT"/*.php|"$EXT_ROOT"/*.php)
    ;;
  *)
    echo "unsafe target path: $TARGET" >&2
    exit 3
    ;;
esac

if [[ ! -e "$TARGET" && ! -L "$TARGET" ]]; then
  exit 0
fi

if [[ -d "$TARGET" && ! -L "$TARGET" ]]; then
  rm -rf -- "$TARGET"
else
  rm -f -- "$TARGET"
fi

echo "removed: $TARGET"
