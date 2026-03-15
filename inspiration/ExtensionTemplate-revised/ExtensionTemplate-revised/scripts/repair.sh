#!/usr/bin/env bash
set -euo pipefail

EXT_ID='template-extension'
ROOT="${EXT_MGR_EXTENSION_ROOT:-/var/www/extensions/installed/$EXT_ID}"
FOOTPRINT="$ROOT/data/install-footprint.json"
LOG_DIR="$ROOT/logs"

case "$ROOT" in
  /var/www/extensions/installed/*) ;;
  *) echo "[$EXT_ID] ABORT: unsafe ROOT" >&2; exit 1 ;;
esac

mkdir -p "$LOG_DIR" "$ROOT/cache" "$ROOT/data"
log() { echo "[$(date +'%Y-%m-%d %H:%M:%S')] [$EXT_ID] $*" | tee -a "$LOG_DIR/install.log"; }

log "=== repair started ==="

python3 - <<PYEOF
import json, os

fp_path = "$FOOTPRINT"
if not os.path.exists(fp_path):
    print("  no footprint — re-run install recommended")
    exit(0)

fp = json.load(open(fp_path))
repaired = 0
for symlink in fp.get("symlinks",[]):
    unit = os.path.basename(symlink)
    candidates = [
        f"$ROOT/packages/services/{unit}",
        f"$ROOT/scripts/{unit}",
    ]
    src = next((c for c in candidates if os.path.exists(c)), None)
    if not src:
        print(f"  WARN: source for {unit} not found")
        continue
    if os.path.islink(symlink) and os.readlink(symlink) == src:
        print(f"  OK: {symlink}")
    else:
        if os.path.islink(symlink): os.unlink(symlink)
        if os.access(os.path.dirname(symlink), os.W_OK):
            os.symlink(src, symlink)
            print(f"  repaired: {symlink}")
            repaired += 1
print(f"  symlinks repaired: {repaired}")
PYEOF

if [[ -x /usr/bin/systemctl ]]; then
  state=$(systemctl is-active "template-extension.service" 2>/dev/null || true)
  log "service state: $state"
  if [[ "$state" != "active" ]]; then
    systemctl daemon-reload >/dev/null 2>&1 || true
    systemctl restart "template-extension.service" >/dev/null 2>&1 || true
  fi
fi

log "=== repair completed ==="
