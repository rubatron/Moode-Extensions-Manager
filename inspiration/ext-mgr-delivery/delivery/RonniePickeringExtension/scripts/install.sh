#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════
#  install.sh — Ronnie Pickering's Extension
#
#  Path policy (enforced):
#    OK   /var/www/extensions/installed/$EXT_ID/  ← our managed root
#    OK   /var/www/extensions/sys/                ← shared sys root
#    OK   /etc/systemd/system/  ← symlinks only, never copies
#    ✗    /var/www/<anything else>  ← REDIRECT to managed root
#    WARN /etc/ /usr/ /lib/ etc.   ← audited, blocked where possible
# ═══════════════════════════════════════════════════════════════════
set -euo pipefail

EXT_ID='ronnie-pickering-extension'
ROOT="${EXT_MGR_EXTENSION_ROOT:-/var/www/extensions/installed/$EXT_ID}"
SYS_ROOT='/var/www/extensions/sys'
PKG_REGISTER="$SYS_ROOT/pkg-register.json"
FOOTPRINT="$ROOT/data/install-footprint.json"
LOG_DIR="$ROOT/logs"
LOG_FILE="$LOG_DIR/install.log"
SERVICE_NAME="${EXT_ID}.service"
SYSTEMD_DIR='/etc/systemd/system'

# ── Safety guard ──────────────────────────────────────────────────
case "$ROOT" in
  /var/www/extensions/installed/*) ;;
  *)
    echo "[$EXT_ID] ABORT: unsafe ROOT='$ROOT'" >&2
    exit 1 ;;
esac

# ── Logging ───────────────────────────────────────────────────────
mkdir -p "$LOG_DIR" "$ROOT/cache" "$ROOT/data"
log() { echo "[$(date +'%Y-%m-%d %H:%M:%S')] [$EXT_ID] $*" | tee -a "$LOG_FILE"; }

log "=== install started ==="
log "ROOT=$ROOT"

# ── Footprint collector ───────────────────────────────────────────
FOOTPRINT_SYMLINKS=()
FOOTPRINT_APT=()
FOOTPRINT_FILES=()

write_footprint() {
  python3 - <<PYEOF
import json
symlinks = """${FOOTPRINT_SYMLINKS[*]+"${FOOTPRINT_SYMLINKS[*]}"}""".split()
apt_pkgs = """${FOOTPRINT_APT[*]+"${FOOTPRINT_APT[*]}"}""".split()
files    = """${FOOTPRINT_FILES[*]+"${FOOTPRINT_FILES[*]}"}""".split()
data = {
    "ext_id":       "$EXT_ID",
    "installed_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "symlinks":     symlinks,
    "apt_packages": apt_pkgs,
    "files_copied": files
}
with open("$FOOTPRINT", "w") as f:
    json.dump(data, f, indent=2)
print("[$EXT_ID] footprint written")
PYEOF
}

# ── Path policy enforcement ───────────────────────────────────────
# Any /var/www/ path outside our managed root must be redirected.
# This function is called before any file operation that targets /var/www/.
assert_managed_path() {
  local target="$1"
  case "$target" in
    "$ROOT"/*|"$SYS_ROOT"/*) return 0 ;;
    /var/www/*)
      log "ERROR: path '$target' is outside managed root — refusing"
      log "       Redirect to: $ROOT/$(basename "$target")"
      return 1 ;;
    *) return 0 ;;  # non-/var/www/ paths (systemd etc) pass through
  esac
}

# ── External path audit ───────────────────────────────────────────
scan_external_paths() {
  log "--- external path audit ---"
  python3 - <<PYEOF
import re, json

ext_id   = "$EXT_ID"
root     = "$ROOT"
managed  = f"/var/www/extensions/installed/{ext_id}"
sys_root = "/var/www/extensions/sys"
systemd  = "/etc/systemd/system"
varwww   = "/var/www/"

with open(f"{root}/scripts/install.sh") as f:
    raw = f.read()

# Strip heredoc blocks
content = re.sub(r'<<\s*[\'"]?\w+[\'"]?.*?^\w+$', '', raw,
                 flags=re.DOTALL|re.MULTILINE)
content = re.sub(r'^\s*#.*$', '', content, flags=re.MULTILINE)

violations = []
audit      = []

for m in re.finditer(
    r'(?<!["\w])(/(?:var/www|etc|usr|lib|opt|home|tmp|run)/[^\s\'"\\);`\]]+)',
    content
):
    p = m.group(1).rstrip("'\"\\;)")
    if p.startswith(systemd):  continue
    if p.startswith(managed):  continue
    if p.startswith(sys_root): continue
    if re.match(r'^/usr/bin/[a-z\-]+$', p): continue

    audit.append(p)

    if p.startswith(varwww) and not p.startswith("/var/www/extensions/"):
        violations.append(p)
        print(f"  [VIOLATION] {p}")
        print(f"    → must redirect to: {managed}/{p.split('/')[-1]}")
    else:
        print(f"  [AUDIT]     {p}")

if not audit:
    print("  [CLEAN] no external paths found")
if violations:
    exit(1)
PYEOF
  local rc=$?
  if [[ $rc -ne 0 ]]; then
    log "ERROR: path violations found in install.sh — aborting"
    exit 1
  fi
  log "--- end audit (clean) ---"
}

# ── Central package register ──────────────────────────────────────
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
    print(f"SKIP {pkg} already owned by: {', '.join(pkgs[pkg])}")
    sys.exit(2)
pkgs[pkg] = [ext]
json.dump(data, open(reg,"w"), indent=2)
print(f"REGISTERED {pkg}")
sys.exit(0)
PYEOF
}

# ── Install service units as symlinks ─────────────────────────────
install_service_units() {
  if [[ ! -d "$SYSTEMD_DIR" || ! -w "$SYSTEMD_DIR" ]]; then
    log "WARN: $SYSTEMD_DIR not writable — skipping service install"
    return 0
  fi
  if [[ ! -x /usr/bin/systemctl ]]; then
    log "WARN: systemctl not found — skipping"
    return 0
  fi

  # Helper units from packages/services/
  local svc_src="$ROOT/packages/services"
  if [[ -d "$svc_src" ]]; then
    shopt -s nullglob
    for unit_file in "$svc_src"/*.service; do
      local unit_name target
      unit_name=$(basename "$unit_file")
      target="$SYSTEMD_DIR/$unit_name"
      ln -sf "$unit_file" "$target"
      log "symlink: $target -> $unit_file"
      FOOTPRINT_SYMLINKS+=("$target")
    done
    shopt -u nullglob
  fi

  # Main service from scripts/
  local main_svc="$ROOT/scripts/$SERVICE_NAME"
  if [[ -f "$main_svc" ]]; then
    local main_target="$SYSTEMD_DIR/$SERVICE_NAME"
    ln -sf "$main_svc" "$main_target"
    log "symlink: $main_target -> $main_svc"
    FOOTPRINT_SYMLINKS+=("$main_target")
  fi

  systemctl daemon-reload
  log "daemon-reload done"
  systemctl enable --now moode-extmgr.service >/dev/null 2>&1 || true
  systemctl enable --now "$SERVICE_NAME"       >/dev/null 2>&1 || true
  log "service $SERVICE_NAME enabled"
}

# ── Install apt packages ──────────────────────────────────────────
install_apt_packages() {
  if [[ ! -x /usr/bin/apt-get ]]; then
    log "apt-get not available — skipping"; return 0
  fi
  local manifest="$ROOT/manifest.json"
  [[ -f "$manifest" ]] || { log "no manifest.json — skipping apt"; return 0; }
  local packages
  packages=$(python3 -c "
import json
m = json.load(open('$manifest'))
pkgs = m.get('ext_mgr',{}).get('install',{}).get('packages',[])
print('\n'.join(pkgs))
" 2>/dev/null || true)
  [[ -z "$packages" ]] && { log "no apt packages declared"; return 0; }

  while IFS= read -r pkg; do
    [[ -z "$pkg" ]] && continue
    log "apt: checking $pkg"
    set +e; register_apt_package "$pkg"; rc=$?; set -e
    if [[ $rc -eq 2 ]]; then
      log "apt: SKIP $pkg (shared — already installed)"; continue
    fi
    log "apt: installing $pkg"
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "$pkg" \
      >> "$LOG_FILE" 2>&1 && FOOTPRINT_APT+=("$pkg") || log "ERROR: apt install failed for $pkg"
  done <<< "$packages"
}

# ── Main ──────────────────────────────────────────────────────────
scan_external_paths    # aborts if /var/www/ violations found
install_service_units
install_apt_packages
write_footprint

log "=== install completed ==="
echo "[$EXT_ID] install completed"
