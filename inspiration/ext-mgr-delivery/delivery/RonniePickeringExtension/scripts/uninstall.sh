#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════
#  uninstall.sh — Ronnie Pickering's Extension
#  Reads install-footprint.json and surgically removes only what
#  this extension installed. Never touches shared packages still
#  claimed by other extensions in pkg-register.json.
# ═══════════════════════════════════════════════════════════════════
set -euo pipefail

EXT_ID='ronnie-pickering-extension'
ROOT="/var/www/extensions/installed/$EXT_ID"
SYS_ROOT='/var/www/extensions/sys'
PKG_REGISTER="$SYS_ROOT/pkg-register.json"
FOOTPRINT="$ROOT/data/install-footprint.json"
LOG_DIR="$ROOT/logs"
LOG_FILE="$LOG_DIR/install.log"

log() { echo "[$(date +'%Y-%m-%d %H:%M:%S')] [$EXT_ID] $*" | tee -a "$LOG_FILE" 2>/dev/null || true; }

log "=== uninstall started ==="

# ── Remove symlinks listed in footprint ───────────────────────────
remove_symlinks() {
  python3 - <<PYEOF
import json, os, subprocess
fp_path = "$FOOTPRINT"
if not os.path.exists(fp_path):
    print("  no footprint found — falling back to best-guess cleanup")
    # Fallback: remove well-known targets
    for name in ["$EXT_ID.service", "ronnie-pickering-helper.service"]:
        t = f"/etc/systemd/system/{name}"
        if os.path.islink(t):
            os.unlink(t)
            print(f"  removed symlink (fallback): {t}")
    exit(0)

fp = json.load(open(fp_path))
for symlink in fp.get("symlinks", []):
    if os.path.islink(symlink):
        os.unlink(symlink)
        print(f"  removed symlink: {symlink}")
    else:
        print(f"  symlink already gone: {symlink}")
PYEOF
}

# ── Unregister and optionally remove apt packages ─────────────────
remove_apt_packages() {
  python3 - <<PYEOF
import json, os, subprocess

reg_path = "$PKG_REGISTER"
fp_path  = "$FOOTPRINT"
ext_id   = "$EXT_ID"

if not os.path.exists(fp_path):
    print("  no footprint — skipping apt cleanup")
    exit(0)
fp = json.load(open(fp_path))
apt_pkgs = fp.get("apt_packages", [])
if not apt_pkgs:
    print("  no apt packages to remove")
    exit(0)

if not os.path.exists(reg_path):
    print("  no pkg-register found — skipping apt cleanup")
    exit(0)

reg = json.load(open(reg_path))
packages = reg.get("packages", {})
to_remove = []

for pkg in apt_pkgs:
    if pkg not in packages:
        continue
    owners = packages[pkg]
    if ext_id in owners:
        owners.remove(ext_id)
    if len(owners) == 0:
        del packages[pkg]
        to_remove.append(pkg)
        print(f"  will remove apt package: {pkg} (no other owners)")
    else:
        print(f"  keeping apt package: {pkg} (still used by: {', '.join(owners)})")

# Write updated register
json.dump(reg, open(reg_path, "w"), indent=2)

# Remove unclaimed packages
if to_remove and os.path.exists("/usr/bin/apt-get"):
    subprocess.run(["apt-get", "remove", "-y", "--auto-remove"] + to_remove, check=False)
PYEOF
}

# ── Disable and stop services ─────────────────────────────────────
stop_services() {
  if [[ ! -x /usr/bin/systemctl ]]; then return 0; fi
  python3 - <<PYEOF
import json, os, subprocess

fp_path = "$FOOTPRINT"
symlinks = []
if os.path.exists(fp_path):
    symlinks = json.load(open(fp_path)).get("symlinks", [])

# Extract service names from symlink paths
service_names = [os.path.basename(s) for s in symlinks if s.endswith(".service")]
if not service_names:
    service_names = ["$EXT_ID.service"]

for svc in service_names:
    subprocess.run(["systemctl", "disable", "--now", svc],
                   stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    print(f"  disabled: {svc}")
PYEOF
  systemctl daemon-reload >/dev/null 2>&1 || true
  log "daemon-reload done"
}

# ── Main ──────────────────────────────────────────────────────────
stop_services
remove_symlinks
remove_apt_packages

if [[ -x /usr/bin/systemctl ]]; then
  systemctl daemon-reload >/dev/null 2>&1 || true
fi

# Remove runtime dirs (not the extension files themselves — ext-mgr handles that)
rm -rf "$ROOT/cache" "$ROOT/data" "$ROOT/logs"

log "=== uninstall completed ==="
echo "[$EXT_ID] uninstall completed"
