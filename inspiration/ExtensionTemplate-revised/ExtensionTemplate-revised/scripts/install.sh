#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════
#  install.sh — ExtensionTemplate
#  Intelligent installer — copy this pattern for all ext-mgr extensions
#
#  Path policy:
#    OK   /var/www/extensions/installed/$EXT_ID/  ← managed root
#    OK   /var/www/extensions/sys/                ← shared sys root
#    OK   /etc/systemd/system/  ← symlinks ONLY
#    ✗    Any other /var/www/ path → VIOLATION (redirect to managed root)
# ═══════════════════════════════════════════════════════════════════
set -euo pipefail

EXT_ID='template-extension'
ROOT="${EXT_MGR_EXTENSION_ROOT:-/var/www/extensions/installed/$EXT_ID}"
SYS_ROOT='/var/www/extensions/sys'
PKG_REGISTER="$SYS_ROOT/pkg-register.json"
FOOTPRINT="$ROOT/data/install-footprint.json"
LOG_DIR="$ROOT/logs"
LOG_FILE="$LOG_DIR/install.log"
SERVICE_NAME="${EXT_ID}.service"
SYSTEMD_DIR='/etc/systemd/system'

case "$ROOT" in
  /var/www/extensions/installed/*) ;;
  *)
    echo "[$EXT_ID] ABORT: unsafe ROOT='$ROOT'" >&2
    exit 1 ;;
esac

mkdir -p "$LOG_DIR" "$ROOT/cache" "$ROOT/data"
log() { echo "[$(date +'%Y-%m-%d %H:%M:%S')] [$EXT_ID] $*" | tee -a "$LOG_FILE"; }

log "=== install started === ROOT=$ROOT"

FOOTPRINT_SYMLINKS=()
FOOTPRINT_APT=()

write_footprint() {
  python3 - <<PYEOF
import json
symlinks = """${FOOTPRINT_SYMLINKS[*]+"${FOOTPRINT_SYMLINKS[*]}"}""".split()
apt_pkgs = """${FOOTPRINT_APT[*]+"${FOOTPRINT_APT[*]}"}""".split()
data = {
    "ext_id":       "$EXT_ID",
    "installed_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "symlinks":     symlinks,
    "apt_packages": apt_pkgs,
}
with open("$FOOTPRINT", "w") as f:
    json.dump(data, f, indent=2)
print("[$EXT_ID] footprint written")
PYEOF
}

register_apt_package() {
  local pkg="$1"
  python3 - <<PYEOF
import json, os, sys
reg  = "$PKG_REGISTER"
ext  = "$EXT_ID"
pkg  = "$pkg"
os.makedirs(os.path.dirname(reg), exist_ok=True)
data = json.load(open(reg)) if os.path.exists(reg) else {"packages": {}}
pkgs = data.setdefault("packages", {})
if pkg in pkgs:
    if ext not in pkgs[pkg]:
        pkgs[pkg].append(ext)
        json.dump(data, open(reg,"w"), indent=2)
    print(f"SKIP {pkg}")
    sys.exit(2)
pkgs[pkg] = [ext]
json.dump(data, open(reg,"w"), indent=2)
print(f"REGISTERED {pkg}")
sys.exit(0)
PYEOF
}

install_service_units() {
  if [[ ! -d "$SYSTEMD_DIR" || ! -w "$SYSTEMD_DIR" ]]; then
    log "WARN: $SYSTEMD_DIR not writable — skipping"; return 0
  fi
  if [[ ! -x /usr/bin/systemctl ]]; then
    log "WARN: systemctl not found — skipping"; return 0
  fi
  shopt -s nullglob
  for unit_file in "$ROOT/packages/services"/*.service; do
    local unit_name target
    unit_name=$(basename "$unit_file")
    target="$SYSTEMD_DIR/$unit_name"
    ln -sf "$unit_file" "$target"
    log "symlink: $target -> $unit_file"
    FOOTPRINT_SYMLINKS+=("$target")
  done
  shopt -u nullglob
  local main_svc="$ROOT/scripts/$SERVICE_NAME"
  if [[ -f "$main_svc" ]]; then
    ln -sf "$main_svc" "$SYSTEMD_DIR/$SERVICE_NAME"
    log "symlink: $SYSTEMD_DIR/$SERVICE_NAME -> $main_svc"
    FOOTPRINT_SYMLINKS+=("$SYSTEMD_DIR/$SERVICE_NAME")
  fi
  systemctl daemon-reload
  systemctl enable --now moode-extmgr.service >/dev/null 2>&1 || true
  systemctl enable --now "$SERVICE_NAME"       >/dev/null 2>&1 || true
  log "service $SERVICE_NAME enabled"
}

install_apt_packages() {
  [[ -x /usr/bin/apt-get ]] || { log "no apt-get — skip"; return 0; }
  local packages
  packages=$(python3 -c "
import json
m = json.load(open('$ROOT/manifest.json'))
print('\n'.join(m.get('ext_mgr',{}).get('install',{}).get('packages',[])))
" 2>/dev/null || true)
  [[ -z "$packages" ]] && { log "no packages declared"; return 0; }
  while IFS= read -r pkg; do
    [[ -z "$pkg" ]] && continue
    set +e; register_apt_package "$pkg"; rc=$?; set -e
    [[ $rc -eq 2 ]] && continue
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "$pkg" \
      >> "$LOG_FILE" 2>&1 && FOOTPRINT_APT+=("$pkg") || log "ERROR: apt install failed for $pkg"
  done <<< "$packages"
}

install_service_units
install_apt_packages
write_footprint

log "=== install completed ==="
echo "[$EXT_ID] install completed"
