#!/usr/bin/env bash
set -euo pipefail

TARGET="${1:-}"
if [[ -z "$TARGET" ]]; then
  echo "missing target path" >&2
  exit 2
fi

# Restrict deletions to ext-mgr managed tree.
case "$TARGET" in
  /var/www/extensions/installed/*|/var/www/extensions/sys/logs/extensionslogs/*|/var/www/extensions/logs/*|/var/www/extensions/cache/*|/var/www/*.php|/var/www/extensions/*.php)
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
