#!/usr/bin/env bash
set -euo pipefail

# ═══════════════════════════════════════════════════════════════════════════════
# Source centralized configuration (provides EXTMGR_* variables)
# ═══════════════════════════════════════════════════════════════════════════════
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -f "$SCRIPT_DIR/ext-mgr-config.sh" ]]; then
    source "$SCRIPT_DIR/ext-mgr-config.sh"
fi

# ═══════════════════════════════════════════════════════════════════════════════
# LEGACY ALIASES - Map old variable names to new EXTMGR_* names
# These are kept for backwards compatibility with existing scripts
# ═══════════════════════════════════════════════════════════════════════════════
SECURITY_GROUP="${EXTMGR_GROUP:-moode-extmgr}"
SECURITY_USER="${EXTMGR_USER:-moode-extmgrusr}"
WEB_USER="${EXTMGR_WEB_USER:-www-data}"
INSTALLED_ROOT="${EXTMGR_INSTALLED_ROOT:-/var/www/extensions/installed}"
LEGACY_EXTENSION_ROOT="${EXTMGR_EXTENSIONS_ROOT:-/var/www/extensions}"
EXTMGR_RUNTIME_ROOT="${EXTMGR_RUNTIME_ROOT:-/var/www/extensions/sys/.ext-mgr}"
EXTMGR_LOG_DIR="$EXTMGR_RUNTIME_ROOT/logs"
HELPER_LOG="$EXTMGR_LOG_DIR/install-helper.log"
SYS_LOG_ROOT="${EXTMGR_LOGS_ROOT:-/var/www/extensions/sys/logs}"
EXT_LOG_ROOT="${EXTMGR_EXTENSION_LOGS_ROOT:-$SYS_LOG_ROOT/extensionslogs}"
MGR_LOG_DIR="${EXTMGR_MGR_LOGS_ROOT:-$SYS_LOG_ROOT/ext-mgr logs}"
CANONICAL_LINK_PATTERN="${EXTMGR_CANONICAL_LINK_PATTERN:-/var/www/%s.php}"
VARS_FILE=""
DRY_RUN=0
TARGET_DIR=""
EXTMGR_METADATA_DIR_NAME=".ext-mgr"
EXTMGR_METADATA_FILE_NAME="install-metadata.json"
EXTMGR_PACKAGE_RUNTIME_ROOT="$EXTMGR_RUNTIME_ROOT/packages"

ADDON_ROOTS=(
  "${EXTMGR_EXTENSIONS_ROOT:-/var/www/extensions}"
  "/var/www/addons"
  "${EXTMGR_EXTENSIONS_ROOT:-/var/www/extensions}/addons"
)

DECLARED_APT_PACKAGES=()
INSTALLED_APT_PACKAGES=()
BUNDLED_PACKAGE_FILES=()
INSTALLED_BUNDLED_PACKAGES=()
DISCOVERED_SERVICE_UNITS=()
INSTALLED_SERVICE_UNITS=()
INJECTED_SERVICE_DEPENDENCIES=()
PACKAGE_RUNTIME_LINKS=()

log() {
  mkdir -p "$EXTMGR_LOG_DIR"
  printf '[%s] %s\n' "$(date +'%Y-%m-%d %H:%M:%S')" "$*" | tee -a "$HELPER_LOG" >/dev/null
}

manager_install_log() {
  local message="$*"
  [[ $DRY_RUN -eq 1 ]] && return 0
  mkdir -p "$MGR_LOG_DIR"
  printf '[%s] %s\n' "$(date +'%Y-%m-%d %H:%M:%S')" "$message" >> "$MGR_LOG_DIR/install.log"
}

extension_install_log() {
  local ext_id="$1"
  shift
  local message="$*"
  [[ $DRY_RUN -eq 1 ]] && return 0
  [[ -n "$ext_id" ]] || return 0

  local global_dir="$EXT_LOG_ROOT/$ext_id"
  local local_dir="$INSTALLED_ROOT/$ext_id/logs"
  mkdir -p "$global_dir" "$local_dir"
  printf '[%s] %s\n' "$(date +'%Y-%m-%d %H:%M:%S')" "$message" >> "$global_dir/install.log"
  printf '[%s] %s\n' "$(date +'%Y-%m-%d %H:%M:%S')" "$message" >> "$local_dir/install.log"
}

info() {
  if [[ $DRY_RUN -eq 1 ]]; then
    log "[dry-run] $*"
  else
    log "$*"
  fi
}

err() {
  printf 'ERROR: %s\n' "$*" >&2
  log "ERROR: $*"
}

require_root() {
  if [[ $EUID -ne 0 ]]; then
    err "Run as root."
    exit 1
  fi
}

require_command() {
  local cmd="$1"
  if ! command -v "$cmd" >/dev/null 2>&1; then
    err "Required command not found: $cmd"
    exit 1
  fi
}

json_get() {
  local file="$1" key="$2"
  php -r '$f=$argv[1]; $k=$argv[2]; $j=@json_decode(@file_get_contents($f), true); if(!is_array($j)){exit(2);} $v=$j; foreach(explode(".",$k) as $p){ if(!is_array($v) || !array_key_exists($p,$v)){ exit(3);} $v=$v[$p]; } if(is_bool($v)){ echo $v?"true":"false"; } else if(is_array($v)){ echo json_encode($v); } else { echo (string)$v; }' "$file" "$key" 2>/dev/null || true
}

json_array_lines() {
  local file="$1" key="$2"
  php -r '$f=$argv[1]; $k=$argv[2]; $j=@json_decode(@file_get_contents($f), true); if(!is_array($j)){exit(2);} $v=$j; foreach(explode(".",$k) as $p){ if(!is_array($v) || !array_key_exists($p,$v)){ exit(3);} $v=$v[$p]; } if(!is_array($v)){ exit(4);} foreach($v as $item){ if(is_scalar($item)){ $s=trim((string)$item); if($s!==""){ echo $s, PHP_EOL; } } }' "$file" "$key" 2>/dev/null || true
}

is_safe_rel() {
  local path="$1"
  [[ -n "$path" && "$path" != /* && "$path" != *..* ]]
}

join_lines() {
  local IFS=$'\n'
  printf '%s' "$*"
}

relative_to_target() {
  local base="$1" path="$2"
  printf '%s' "${path#${base}/}"
}

load_vars_file() {
  local path="$1"
  [[ -f "$path" ]] || return 0

  local configured_installed_root configured_legacy_root configured_runtime_root
  configured_installed_root="$(json_get "$path" "paths.installedRoot")"
  configured_legacy_root="$(json_get "$path" "paths.legacyExtensionRoot")"
  configured_runtime_root="$(json_get "$path" "paths.runtimeRoot")"
  local configured_sys_logs_root configured_extensions_logs_root configured_manager_logs_root
  configured_sys_logs_root="$(json_get "$path" "paths.sysLogsRoot")"
  configured_extensions_logs_root="$(json_get "$path" "paths.extensionsLogsRoot")"
  configured_manager_logs_root="$(json_get "$path" "paths.extMgrLogsRoot")"

  [[ -n "$configured_installed_root" ]] && INSTALLED_ROOT="$configured_installed_root"
  [[ -n "$configured_legacy_root" ]] && LEGACY_EXTENSION_ROOT="$configured_legacy_root"
  if [[ -n "$configured_runtime_root" ]]; then
    EXTMGR_RUNTIME_ROOT="$configured_runtime_root"
    EXTMGR_LOG_DIR="$EXTMGR_RUNTIME_ROOT/logs"
    HELPER_LOG="$EXTMGR_LOG_DIR/install-helper.log"
    EXTMGR_PACKAGE_RUNTIME_ROOT="$EXTMGR_RUNTIME_ROOT/packages"
  fi

  [[ -n "$configured_sys_logs_root" ]] && SYS_LOG_ROOT="$configured_sys_logs_root"
  [[ -n "$configured_extensions_logs_root" ]] && EXT_LOG_ROOT="$configured_extensions_logs_root"
  [[ -n "$configured_manager_logs_root" ]] && MGR_LOG_DIR="$configured_manager_logs_root"

  local configured_canonical
  configured_canonical="$(json_get "$path" "defaults.canonicalLinkPattern")"
  [[ -n "$configured_canonical" ]] && CANONICAL_LINK_PATTERN="$configured_canonical"

  local roots=()
  while IFS= read -r line; do
    [[ -n "$line" ]] && roots+=("$line")
  done < <(json_array_lines "$path" "addons.candidateRoots")

  if [[ ${#roots[@]} -gt 0 ]]; then
    ADDON_ROOTS=("${roots[@]}")
  fi

  info "Loaded install vars from $path"
}

canonical_link_for() {
  local ext_id="$1"
  printf "$CANONICAL_LINK_PATTERN" "$ext_id"
}

ensure_security_context() {
  mkdir -p "$EXTMGR_RUNTIME_ROOT" "$EXTMGR_LOG_DIR" "$SYS_LOG_ROOT" "$EXT_LOG_ROOT" "$MGR_LOG_DIR" "$EXTMGR_PACKAGE_RUNTIME_ROOT"
  if [[ $DRY_RUN -eq 0 ]]; then
    chown -R "$SECURITY_USER:$SECURITY_GROUP" "$EXTMGR_RUNTIME_ROOT" 2>/dev/null || true
    chmod 2775 "$EXTMGR_RUNTIME_ROOT" "$EXTMGR_LOG_DIR" "$EXTMGR_PACKAGE_RUNTIME_ROOT" 2>/dev/null || true
    chown -R "$SECURITY_USER:$SECURITY_GROUP" "$SYS_LOG_ROOT" 2>/dev/null || true
    chmod 2775 "$SYS_LOG_ROOT" "$EXT_LOG_ROOT" "$MGR_LOG_DIR" 2>/dev/null || true
  fi
}

install_additional_packages() {
  local manifest="$1"
  local packages=()
  while IFS= read -r line; do
    [[ -n "$line" ]] && packages+=("$line")
  done < <(json_array_lines "$manifest" "ext_mgr.install.packages")

  DECLARED_APT_PACKAGES=("${packages[@]}")
  if [[ ${#packages[@]} -eq 0 ]]; then
    return 0
  fi

  info "Installing additional packages declared by manifest: ${packages[*]}"
  if [[ $DRY_RUN -eq 1 ]]; then
    return 0
  fi

  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y >/dev/null 2>&1 || true
  if ! apt-get install -y --no-install-recommends "${packages[@]}" >/dev/null 2>&1; then
    err "Failed to install packages: ${packages[*]}. Check dpkg state."
    return 1
  fi
  INSTALLED_APT_PACKAGES=("${packages[@]}")
}

scan_bundled_package_files() {
  local target_dir="$1"
  BUNDLED_PACKAGE_FILES=()
  [[ -d "$target_dir/packages" ]] || return 0

  while IFS= read -r file; do
    [[ -n "$file" ]] || continue
    BUNDLED_PACKAGE_FILES+=("$(relative_to_target "$target_dir" "$file")")
  done < <(find "$target_dir/packages" -type f | sort)
}

create_package_runtime_links() {
  local target_dir="$1" ext_id="$2"
  local package_dir="$target_dir/packages"
  local metadata_dir="$target_dir/$EXTMGR_METADATA_DIR_NAME"
  local runtime_link="$EXTMGR_PACKAGE_RUNTIME_ROOT/$ext_id"
  local local_link="$metadata_dir/packages-runtime"

  PACKAGE_RUNTIME_LINKS=()
  [[ -d "$package_dir" ]] || return 0

  info "Preparing package runtime symlinks for $ext_id"
  if [[ $DRY_RUN -eq 1 ]]; then
    PACKAGE_RUNTIME_LINKS+=("$runtime_link" "$local_link")
    return 0
  fi

  mkdir -p "$EXTMGR_PACKAGE_RUNTIME_ROOT" "$metadata_dir"
  ln -sfn "$package_dir" "$runtime_link"
  ln -sfn "$runtime_link" "$local_link"
  PACKAGE_RUNTIME_LINKS+=("$runtime_link" "$local_link")
}

install_bundled_packages() {
  local target_dir="$1"
  scan_bundled_package_files "$target_dir"
  [[ ${#BUNDLED_PACKAGE_FILES[@]} -gt 0 ]] || return 0

  info "Scanning bundled package payloads under $target_dir/packages"
  if [[ $DRY_RUN -eq 1 ]]; then
    return 0
  fi

  export DEBIAN_FRONTEND=noninteractive
  local rel abs
  for rel in "${BUNDLED_PACKAGE_FILES[@]}"; do
    abs="$target_dir/$rel"
    case "$rel" in
      *.deb)
        info "Installing bundled Debian package: $rel"
        if apt-get install -y --no-install-recommends "$abs" >/dev/null 2>&1; then
          INSTALLED_BUNDLED_PACKAGES+=("$rel")
        else
          dpkg -i "$abs" >/dev/null 2>&1 || apt-get install -f -y >/dev/null 2>&1 || true
          INSTALLED_BUNDLED_PACKAGES+=("$rel")
        fi
        ;;
      *)
        info "Leaving bundled artifact staged only: $rel"
        ;;
    esac
  done
}

rewrite_legacy_paths_in_file() {
  local file="$1" ext_id="$2" target_dir="$3"
  [[ -f "$file" ]] || return 0

  case "$file" in
    *.php|*.sh|*.js|*.css|*.json|*.md|*.txt|*.service|*.ini|*.conf)
      ;;
    *)
      return 0
      ;;
  esac

  local canonical_root="$INSTALLED_ROOT/$ext_id"
  local legacy_root="$LEGACY_EXTENSION_ROOT/$ext_id"

  if grep -q "$legacy_root" "$file" 2>/dev/null; then
    if [[ $DRY_RUN -eq 1 ]]; then
      info "Would rewrite legacy root in $file"
    else
      sed -i "s#$legacy_root#$canonical_root#g" "$file"
    fi
  fi
  if grep -q "$target_dir" "$file" 2>/dev/null && [[ "$target_dir" != "$canonical_root" ]]; then
    if [[ $DRY_RUN -eq 1 ]]; then
      info "Would rewrite staged root in $file"
    else
      sed -i "s#$target_dir#$canonical_root#g" "$file"
    fi
  fi
}

rewrite_legacy_paths() {
  local target_dir="$1" ext_id="$2"
  while IFS= read -r file; do
    rewrite_legacy_paths_in_file "$file" "$ext_id" "$target_dir"
  done < <(find "$target_dir" -type f 2>/dev/null)
}

move_tree_contents() {
  local src="$1" dst="$2"
  [[ -d "$src" ]] || return 0
  mkdir -p "$dst"
  shopt -s dotglob nullglob
  local item
  for item in "$src"/*; do
    if [[ $DRY_RUN -eq 1 ]]; then
      info "Would move $item -> $dst/"
    else
      mv "$item" "$dst/"
    fi
  done
  shopt -u dotglob nullglob
  if [[ $DRY_RUN -eq 0 ]]; then
    rmdir "$src" 2>/dev/null || true
  fi
}

normalize_var_www_layout() {
  local ext_id="$1" target_dir="$2" main_entry="$3"
  local legacy_root="$LEGACY_EXTENSION_ROOT/$ext_id"
  local canonical_link
  canonical_link="$(canonical_link_for "$ext_id")"

  if [[ -d "$legacy_root" && "$legacy_root" != "$target_dir" ]]; then
    info "Relocating legacy extension root $legacy_root -> $target_dir"
    move_tree_contents "$legacy_root" "$target_dir"
  fi

  local legacy_main="/var/www/$main_entry"
  if [[ -f "$legacy_main" && "$legacy_main" != "$canonical_link" && ! -L "$legacy_main" ]]; then
    info "Relocating legacy main entry $legacy_main -> $target_dir/$main_entry"
    if [[ $DRY_RUN -eq 0 ]]; then
      mv "$legacy_main" "$target_dir/$main_entry"
    fi
  fi

  local stray
  while IFS= read -r stray; do
    [[ -n "$stray" ]] || continue
    if [[ "$stray" == "$canonical_link" ]]; then
      continue
    fi
    if [[ "$stray" == "$INSTALLED_ROOT"/* ]]; then
      continue
    fi
    if [[ "$(basename "$stray")" == "$ext_id"* ]]; then
      info "Relocating stray /var/www artifact $stray -> $target_dir/$(basename "$stray")"
      if [[ $DRY_RUN -eq 0 ]]; then
        mv "$stray" "$target_dir/$(basename "$stray")"
      fi
    fi
  done < <(find /var/www -maxdepth 1 \( -type f -o -type d \) -name "$ext_id*" 2>/dev/null || true)

  local candidate_root
  for candidate_root in "${ADDON_ROOTS[@]}"; do
    [[ -d "$candidate_root" ]] || continue
    local candidate="$candidate_root/$ext_id"
    if [[ -d "$candidate" && "$candidate" != "$target_dir" && "$candidate" != "$legacy_root" ]]; then
      info "Relocating addon root $candidate -> $target_dir"
      move_tree_contents "$candidate" "$target_dir"
    fi
  done
}

run_install_script() {
  local manifest="$1" target_dir="$2" ext_id="$3" main_entry="$4"
  local script_rel
  script_rel="$(json_get "$manifest" "ext_mgr.install.script")"
  if ! is_safe_rel "$script_rel"; then
    info "No ext_mgr.install.script declared; skipping staged install hook"
    return 0
  fi

  local script_path="$target_dir/$script_rel"
  if [[ ! -f "$script_path" ]]; then
    err "Declared install script not found: $script_path"
    exit 1
  fi

  if [[ $DRY_RUN -eq 1 ]]; then
    info "Would execute package install script as $SECURITY_USER: $script_rel"
    return 0
  fi

  chmod 0755 "$script_path" 2>/dev/null || true
  rewrite_legacy_paths "$target_dir" "$ext_id"

  local shell_cmd
  shell_cmd="cd '$target_dir' && '$script_path'"
  info "Executing package install script as $SECURITY_USER: $script_rel"
  runuser -u "$SECURITY_USER" -- env \
    EXT_MGR_EXTENSION_ID="$ext_id" \
    EXT_MGR_EXTENSION_ROOT="$INSTALLED_ROOT/$ext_id" \
    EXT_MGR_EXTENSION_DIR="$INSTALLED_ROOT/$ext_id" \
    EXT_MGR_EXTENSION_CANONICAL_LINK="$(canonical_link_for "$ext_id")" \
    EXT_MGR_EXTENSION_MAIN="$main_entry" \
    EXT_MGR_EXTENSION_PACKAGES_DIR="$target_dir/$EXTMGR_METADATA_DIR_NAME/packages-runtime" \
    EXT_MGR_RUNTIME_ROOT="$EXTMGR_RUNTIME_ROOT" \
    bash -lc "$shell_cmd"
}

discover_service_unit_sources() {
  local target_dir="$1"
  {
    find "$target_dir/scripts" -maxdepth 1 -type f -name '*.service' 2>/dev/null
    find "$target_dir/packages/services" -maxdepth 1 -type f -name '*.service' 2>/dev/null
  } | sort -u
}

normalize_service_unit_file() {
  local src="$1" dst="$2" user="$3" group="$4" working_dir="$5" requires_csv="$6" after_csv="$7" partof_csv="$8"
  python3 - "$src" "$dst" "$user" "$group" "$working_dir" "$requires_csv" "$after_csv" "$partof_csv" <<'PY'
import sys
from pathlib import Path

src, dst, user, group, working_dir, requires_csv, after_csv, partof_csv = sys.argv[1:9]
sections = []
current_name = None
current_lines = []

for line in Path(src).read_text(encoding='utf-8', errors='ignore').splitlines():
    stripped = line.strip()
    if stripped.startswith('[') and stripped.endswith(']'):
        if current_name is not None:
            sections.append((current_name, current_lines))
        current_name = stripped[1:-1]
        current_lines = []
    else:
        if current_name is None:
            current_name = 'Unit'
            current_lines = []
        current_lines.append(line)

if current_name is not None:
    sections.append((current_name, current_lines))

names = [name for name, _ in sections]
if 'Unit' not in names:
    sections.insert(0, ('Unit', []))
if 'Service' not in names:
    sections.append(('Service', []))

def split_values(lines, key):
    values = []
    remainder = []
    for line in lines:
        stripped = line.strip()
        if stripped.startswith(key + '='):
            values.extend([part.strip() for part in stripped.split('=', 1)[1].split() if part.strip()])
        else:
            remainder.append(line)
    return values, remainder

def merge_values(existing, csv_text):
    merged = []
    for value in existing + [item.strip() for item in csv_text.split(',') if item.strip()]:
        if value and value not in merged:
            merged.append(value)
    return merged

result = []
for name, lines in sections:
    if name == 'Unit':
        requires, lines = split_values(lines, 'Requires')
        after, lines = split_values(lines, 'After')
        partof, lines = split_values(lines, 'PartOf')
        requires = merge_values(requires, requires_csv)
        after = merge_values(after, after_csv)
        partof = merge_values(partof, partof_csv)
        if requires:
            lines.append('Requires=' + ' '.join(requires))
        if after:
            lines.append('After=' + ' '.join(after))
        if partof:
            lines.append('PartOf=' + ' '.join(partof))
    elif name == 'Service':
        remainder = []
        for line in lines:
            stripped = line.strip()
            if stripped.startswith('User=') or stripped.startswith('Group=') or stripped.startswith('WorkingDirectory='):
                continue
            remainder.append(line)
        lines = remainder
        lines.append('User=' + user)
        lines.append('Group=' + group)
        lines.append('WorkingDirectory=' + working_dir)
    result.append((name, lines))

out_lines = []
for name, lines in result:
    out_lines.append(f'[{name}]')
    out_lines.extend(lines)
    out_lines.append('')

Path(dst).write_text('\n'.join(out_lines).rstrip() + '\n', encoding='utf-8')
PY
}

install_service_units() {
  local manifest="$1" target_dir="$2" ext_id="$3"
  local main_service_name
  main_service_name="$(json_get "$manifest" "ext_mgr.service.name")"
  [[ -n "$main_service_name" ]] || main_service_name="$ext_id.service"

  local sources=()
  local source unit_name rel
  while IFS= read -r source; do
    [[ -n "$source" ]] || continue
    sources+=("$source")
    rel="$(relative_to_target "$target_dir" "$source")"
    DISCOVERED_SERVICE_UNITS+=("$rel")
  done < <(discover_service_unit_sources "$target_dir")

  [[ ${#sources[@]} -gt 0 ]] || return 0

  local manifest_deps=()
  while IFS= read -r line; do
    [[ -n "$line" ]] && manifest_deps+=("$line")
  done < <(json_array_lines "$manifest" "ext_mgr.service.dependencies")

  local additional_units=()
  for source in "${sources[@]}"; do
    unit_name="$(basename "$source")"
    if [[ "$unit_name" != "$main_service_name" ]]; then
      additional_units+=("$unit_name")
    fi
  done

  local extra_deps=()
  extra_deps=("${manifest_deps[@]}" "${additional_units[@]}")
  INJECTED_SERVICE_DEPENDENCIES=()
  for unit_name in "${extra_deps[@]}"; do
    [[ -n "$unit_name" ]] || continue
    if [[ "$unit_name" != "moode-extmgr.service" ]]; then
      INJECTED_SERVICE_DEPENDENCIES+=("$unit_name")
    fi
  done

  if [[ $DRY_RUN -eq 1 ]]; then
    info "Would normalize and install service units: ${DISCOVERED_SERVICE_UNITS[*]}"
    for source in "${sources[@]}"; do
      INSTALLED_SERVICE_UNITS+=("$(basename "$source")")
    done
    return 0
  fi

  local reqs afters partofs req_csv after_csv partof_csv dst
  for source in "${sources[@]}"; do
    unit_name="$(basename "$source")"
    reqs=("moode-extmgr.service")
    afters=("moode-extmgr.service" "network.target")
    partofs=("moode-extmgr.service")

    if [[ "$unit_name" == "$main_service_name" ]]; then
      reqs+=("${extra_deps[@]}")
      afters+=("${extra_deps[@]}")
    fi

    req_csv="$(IFS=,; echo "${reqs[*]}")"
    after_csv="$(IFS=,; echo "${afters[*]}")"
    partof_csv="$(IFS=,; echo "${partofs[*]}")"
    dst="/etc/systemd/system/$unit_name"
    normalize_service_unit_file "$source" "$dst" "$SECURITY_USER" "$SECURITY_GROUP" "$INSTALLED_ROOT/$ext_id" "$req_csv" "$after_csv" "$partof_csv"
    INSTALLED_SERVICE_UNITS+=("$unit_name")
  done

  systemctl daemon-reload >/dev/null 2>&1 || true
  for unit_name in "${INSTALLED_SERVICE_UNITS[@]}"; do
    systemctl enable --now "$unit_name" >/dev/null 2>&1 || true
  done
}

write_install_metadata() {
  local manifest="$1" target_dir="$2" ext_id="$3" main_entry="$4"
  local metadata_dir="$target_dir/$EXTMGR_METADATA_DIR_NAME"
  local metadata_file="$metadata_dir/$EXTMGR_METADATA_FILE_NAME"
  local install_script="false" repair_script="false" uninstall_script="false"

  [[ -f "$target_dir/scripts/install.sh" ]] && install_script="true"
  [[ -f "$target_dir/scripts/repair.sh" ]] && repair_script="true"
  [[ -f "$target_dir/scripts/uninstall.sh" ]] && uninstall_script="true"

  if [[ $DRY_RUN -eq 1 ]]; then
    info "Would write install metadata to $metadata_file"
    return 0
  fi

  mkdir -p "$metadata_dir"
  python3 - "$metadata_file" "$ext_id" "$main_entry" "$(canonical_link_for "$ext_id")" "$SECURITY_USER" "$SECURITY_GROUP" \
    "$(join_lines "${DECLARED_APT_PACKAGES[@]}")" \
    "$(join_lines "${INSTALLED_APT_PACKAGES[@]}")" \
    "$(join_lines "${BUNDLED_PACKAGE_FILES[@]}")" \
    "$(join_lines "${INSTALLED_BUNDLED_PACKAGES[@]}")" \
    "$(join_lines "${DISCOVERED_SERVICE_UNITS[@]}")" \
    "$(join_lines "${INSTALLED_SERVICE_UNITS[@]}")" \
    "$(join_lines "${INJECTED_SERVICE_DEPENDENCIES[@]}")" \
    "$(join_lines "${PACKAGE_RUNTIME_LINKS[@]}")" \
    "$install_script" "$repair_script" "$uninstall_script" <<'PY'
import json
import sys
from datetime import datetime, timezone

def split_lines(value):
    return [line.strip() for line in (value or '').splitlines() if line.strip()]

(metadata_file, ext_id, main_entry, canonical_link, user, group,
 declared_packages, installed_apt, bundled_files, installed_bundles,
 discovered_services, installed_services, injected_dependencies,
 package_links, install_script, repair_script, uninstall_script) = sys.argv[1:18]

payload = {
    'schemaVersion': 1,
    'generatedAt': datetime.now(timezone.utc).isoformat(),
    'extensionId': ext_id,
    'mainEntry': main_entry,
    'canonicalLink': canonical_link,
    'runtime': {
        'user': user,
        'group': group,
    },
    'packages': {
        'declared': split_lines(declared_packages),
        'installedApt': split_lines(installed_apt),
        'bundledFiles': split_lines(bundled_files),
        'installedBundles': split_lines(installed_bundles),
    },
    'services': {
        'discovered': split_lines(discovered_services),
        'installed': split_lines(installed_services),
        'dependenciesInjected': split_lines(injected_dependencies),
    },
    'links': {
        'packageRuntimeLinks': split_lines(package_links),
    },
    'scripts': {
        'install': install_script == 'true',
        'repair': repair_script == 'true',
        'uninstall': uninstall_script == 'true',
    },
}

with open(metadata_file, 'w', encoding='utf-8') as handle:
    json.dump(payload, handle, indent=2)
    handle.write('\n')
PY
}

parse_args() {
  while [[ $# -gt 0 ]]; do
    case "$1" in
      --dry-run|-n)
        DRY_RUN=1
        ;;
      --vars-file)
        shift
        VARS_FILE="${1:-}"
        ;;
      --help|-h)
        cat <<'TXT'
ext-mgr-install-helper

Usage:
  ext-mgr-install-helper.sh [--dry-run] [--vars-file <json>] <installed-extension-dir>
TXT
        exit 0
        ;;
      -* )
        err "Unsupported option: $1"
        exit 1
        ;;
      *)
        if [[ -z "$TARGET_DIR" ]]; then
          TARGET_DIR="$1"
        else
          err "Unexpected argument: $1"
          exit 1
        fi
        ;;
    esac
    shift || true
  done
}

main() {
  parse_args "$@"

  require_root
  require_command php
  require_command python3
  require_command runuser

  if [[ -n "$VARS_FILE" ]]; then
    load_vars_file "$VARS_FILE"
  fi

  [[ -n "$TARGET_DIR" ]] || { err "Usage: $0 [--dry-run] [--vars-file <json>] <installed-extension-dir>"; exit 1; }
  [[ -d "$TARGET_DIR" ]] || { err "Target dir not found: $TARGET_DIR"; exit 1; }

  local manifest="$TARGET_DIR/manifest.json"
  [[ -f "$manifest" ]] || { err "manifest.json missing in: $TARGET_DIR"; exit 1; }

  local ext_id main_entry
  ext_id="$(json_get "$manifest" "id")"
  main_entry="$(json_get "$manifest" "main")"
  [[ -n "$ext_id" ]] || { err "manifest id missing"; exit 1; }
  [[ -n "$main_entry" ]] || { err "manifest main missing"; exit 1; }

  ensure_security_context
  manager_install_log "install-helper start for $ext_id"
  extension_install_log "$ext_id" "install-helper start"

  create_package_runtime_links "$TARGET_DIR" "$ext_id"
  install_additional_packages "$manifest"
  install_bundled_packages "$TARGET_DIR"
  run_install_script "$manifest" "$TARGET_DIR" "$ext_id" "$main_entry"
  normalize_var_www_layout "$ext_id" "$TARGET_DIR" "$main_entry"
  rewrite_legacy_paths "$TARGET_DIR" "$ext_id"
  install_service_units "$manifest" "$TARGET_DIR" "$ext_id"
  write_install_metadata "$manifest" "$TARGET_DIR" "$ext_id" "$main_entry"

  extension_install_log "$ext_id" "install-helper completed"
  manager_install_log "install-helper completed for $ext_id"
  info "Install helper completed for $ext_id"
}

main "$@"
