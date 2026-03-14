#!/usr/bin/env bash
set -euo pipefail

SECURITY_GROUP="moode-extmgr"
SECURITY_USER="moode-extmgrusr"
WEB_USER="www-data"
INSTALLED_ROOT="/var/www/extensions/installed"
LEGACY_EXTENSION_ROOT="/var/www/extensions"
EXTMGR_RUNTIME_ROOT="/var/www/extensions/sys/.ext-mgr"
EXTMGR_LOG_DIR="$EXTMGR_RUNTIME_ROOT/logs"
HELPER_LOG="$EXTMGR_LOG_DIR/install-helper.log"
SYS_LOG_ROOT="/var/www/extensions/sys/logs"
EXT_LOG_ROOT="$SYS_LOG_ROOT/extensionslogs"
MGR_LOG_DIR="$SYS_LOG_ROOT/ext-mgr logs"
CANONICAL_LINK_PATTERN="/var/www/%s.php"
VARS_FILE=""
DRY_RUN=0
TARGET_DIR=""

ADDON_ROOTS=(
  "/var/www/extensions"
  "/var/www/addons"
  "/var/www/extensions/addons"
)

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
  php -r '$f=$argv[1]; $k=$argv[2]; $j=@json_decode(@file_get_contents($f), true); if(!is_array($j)){exit(2);} $v=$j; foreach(explode(".",$k) as $p){ if(!is_array($v) || !array_key_exists($p,$v)){ exit(3);} $v=$v[$p]; } if(!is_array($v)){ exit(4);} foreach($v as $item){ if(is_scalar($item)){ echo (string)$item, PHP_EOL; } }' "$file" "$key" 2>/dev/null || true
}

is_safe_rel() {
  local path="$1"
  [[ -n "$path" && "$path" != /* && "$path" != *..* ]]
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
  mkdir -p "$EXTMGR_RUNTIME_ROOT" "$EXTMGR_LOG_DIR" "$SYS_LOG_ROOT" "$EXT_LOG_ROOT" "$MGR_LOG_DIR"
  if [[ $DRY_RUN -eq 0 ]]; then
    chown -R "$SECURITY_USER:$SECURITY_GROUP" "$EXTMGR_RUNTIME_ROOT" 2>/dev/null || true
    chmod 2775 "$EXTMGR_RUNTIME_ROOT" "$EXTMGR_LOG_DIR" 2>/dev/null || true
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

  if [[ ${#packages[@]} -eq 0 ]]; then
    return 0
  fi

  info "Installing additional packages declared by manifest: ${packages[*]}"
  if [[ $DRY_RUN -eq 1 ]]; then
    return 0
  fi

  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y >/dev/null 2>&1 || true
  apt-get install -y --no-install-recommends "${packages[@]}"
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
    EXT_MGR_RUNTIME_ROOT="$EXTMGR_RUNTIME_ROOT" \
    bash -lc "$shell_cmd"
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
  install_additional_packages "$manifest"
  run_install_script "$manifest" "$TARGET_DIR" "$ext_id" "$main_entry"
  normalize_var_www_layout "$ext_id" "$TARGET_DIR" "$main_entry"
  rewrite_legacy_paths "$TARGET_DIR" "$ext_id"
  extension_install_log "$ext_id" "install-helper completed"
  manager_install_log "install-helper completed for $ext_id"
  info "Install helper completed for $ext_id"
}

main "$@"
