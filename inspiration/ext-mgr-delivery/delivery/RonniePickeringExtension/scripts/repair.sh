#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════
#  repair.sh — Ronnie Pickering's Extension
#  Restores runtime dirs, re-creates any missing symlinks from
#  footprint, and verifies service is active. Safe to run at any time.
# ═══════════════════════════════════════════════════════════════════
set -euo pipefail

EXT_ID='ronnie-pickering-extension'
ROOT="${EXT_MGR_EXTENSION_ROOT:-/var/www/extensions/installed/$EXT_ID}"
FOOTPRINT="$ROOT/data/install-footprint.json"
LOG_DIR="$ROOT/logs"
LOG_FILE="$LOG_DIR/install.log"
SYSTEMD_DIR='/etc/systemd/system'

case "$ROOT" in
  /var/www/extensions/installed/*) ;;
  *)
    echo "[$EXT_ID] ABORT: unsafe ROOT='$ROOT'" >&2
    exit 1 ;;
esac

mkdir -p "$LOG_DIR" "$ROOT/cache" "$ROOT/data"
log() { echo "[$(date +'%Y-%m-%d %H:%M:%S')] [$EXT_ID] $*" | tee -a "$LOG_FILE"; }

log "=== repair started ==="

# ── Re-create missing symlinks from footprint ─────────────────────
repair_symlinks() {
  python3 - <<PYEOF
import json, os

fp_path = "$FOOTPRINT"
if not os.path.exists(fp_path):
    print("  no footprint found — re-running install recommended")
    exit(0)

fp = json.load(open(fp_path))
repaired = 0
for symlink in fp.get("symlinks", []):
    # Derive the source path from the unit name
    unit_name = os.path.basename(symlink)
    # Check packages/services/ first, then scripts/
    candidates = [
        f"$ROOT/packages/services/{unit_name}",
        f"$ROOT/scripts/{unit_name}",
    ]
    src = next((c for c in candidates if os.path.exists(c)), None)
    if not src:
        print(f"  WARN: source for {unit_name} not found — skipping")
        continue
    if os.path.islink(symlink) and os.readlink(symlink) == src:
        print(f"  OK: {symlink}")
    else:
        if os.path.islink(symlink):
            os.unlink(symlink)
        if os.path.isdir(os.path.dirname(symlink)) and os.access(os.path.dirname(symlink), os.W_OK):
            os.symlink(src, symlink)
            print(f"  repaired: {symlink} -> {src}")
            repaired += 1
        else:
            print(f"  SKIP: {os.path.dirname(symlink)} not writable")

print(f"  symlinks repaired: {repaired}")
PYEOF
}

# ── Verify service state ──────────────────────────────────────────
check_service() {
  if [[ ! -x /usr/bin/systemctl ]]; then return 0; fi
  local state
  state=$(systemctl is-active "${EXT_ID}.service" 2>/dev/null || true)
  log "service ${EXT_ID}.service state: $state"
  if [[ "$state" != "active" ]]; then
    log "attempting service restart..."
    systemctl daemon-reload >/dev/null 2>&1 || true
    systemctl restart "${EXT_ID}.service" >/dev/null 2>&1 \
      && log "service restarted" \
      || log "WARN: restart failed (may be expected for one-shot services)"
  fi
}

repair_symlinks
check_service

log "=== repair completed ==="
echo "[$EXT_ID] repair completed"
