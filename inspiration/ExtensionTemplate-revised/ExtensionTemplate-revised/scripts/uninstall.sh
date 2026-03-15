#!/usr/bin/env bash
set -euo pipefail

EXT_ID='template-extension'
ROOT="/var/www/extensions/installed/$EXT_ID"
SYS_ROOT='/var/www/extensions/sys'
PKG_REGISTER="$SYS_ROOT/pkg-register.json"
FOOTPRINT="$ROOT/data/install-footprint.json"
LOG_FILE="$ROOT/logs/install.log"

log() { echo "[$(date +'%Y-%m-%d %H:%M:%S')] [$EXT_ID] $*" | tee -a "$LOG_FILE" 2>/dev/null || true; }

log "=== uninstall started ==="

python3 - <<PYEOF
import json, os, subprocess

fp_path = "$FOOTPRINT"
reg_path = "$PKG_REGISTER"
ext_id = "$EXT_ID"

# Stop services + remove symlinks
if os.path.exists(fp_path):
    fp = json.load(open(fp_path))
    for svc in [os.path.basename(s) for s in fp.get("symlinks",[]) if s.endswith(".service")]:
        subprocess.run(["systemctl","disable","--now",svc],
                       stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    for symlink in fp.get("symlinks",[]):
        if os.path.islink(symlink):
            os.unlink(symlink)
            print(f"  removed: {symlink}")
    subprocess.run(["systemctl","daemon-reload"],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)

    # Release apt packages
    if os.path.exists(reg_path):
        reg = json.load(open(reg_path))
        pkgs = reg.get("packages",{})
        orphans = []
        for pkg in fp.get("apt_packages",[]):
            if pkg in pkgs:
                if ext_id in pkgs[pkg]: pkgs[pkg].remove(ext_id)
                if not pkgs[pkg]:
                    del pkgs[pkg]; orphans.append(pkg)
        json.dump(reg, open(reg_path,"w"), indent=2)
        if orphans and os.path.exists("/usr/bin/apt-get"):
            subprocess.run(["apt-get","remove","-y","--auto-remove"]+orphans, check=False)
else:
    print("  no footprint — best-guess cleanup")
    import glob
    for s in glob.glob(f"/etc/systemd/system/{ext_id}*.service"):
        if os.path.islink(s):
            os.unlink(s)
PYEOF

rm -rf "$ROOT/cache" "$ROOT/data" "$ROOT/logs"
log "=== uninstall completed ==="
echo "[$EXT_ID] uninstall completed"
