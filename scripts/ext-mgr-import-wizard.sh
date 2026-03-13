#!/usr/bin/env bash
set -euo pipefail

# ext-mgr import wizard
# - Imports an extension package into /var/www/extensions/installed/<id>
# - Applies ext-mgr security principal and permission model
# - Registers extension in /var/www/extensions/sys/registry.json
# - Optionally enables/disables extension service from manifest

SECURITY_GROUP="moode-extmgr"
SECURITY_USER="moode-extmgrusr"
WEB_USER="www-data"
WEB_GROUP="www-data"
SQLITE_DB="/var/local/www/db/moode-sqlite3.db"
MYSQL_SOCKET="/var/run/mysqld/mysqld.sock"
REGISTRY_PATH="/var/www/extensions/sys/registry.json"
INSTALLED_ROOT="/var/www/extensions/installed"
WATCHDOG_SCRIPT="/usr/local/bin/moode-extmgr-watchdog.sh"
WATCHDOG_SERVICE="/etc/systemd/system/moode-extmgr-watchdog.service"

SOURCE_DIR="${1:-}"
MODE="import"
if [[ "${1:-}" == "--enable" || "${1:-}" == "--disable" ]]; then
  MODE="${1#--}"
  SOURCE_DIR="${2:-}"
fi

if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
  MODE="help"
fi

log() { printf '[%s] %s\n' "$(date +'%Y-%m-%d %H:%M:%S')" "$*"; }
err() { printf 'ERROR: %s\n' "$*" >&2; }

require_root() {
  if [[ $EUID -ne 0 ]]; then
    err "Run as root (sudo)."
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

group_exists() { getent group "$1" >/dev/null 2>&1; }
user_exists() { id -u "$1" >/dev/null 2>&1; }

add_user_to_group_safe() {
  local user="$1" group="$2"
  user_exists "$user" || return 0
  group_exists "$group" || return 0
  id -nG "$user" | tr ' ' '\n' | grep -Fxq "$group" && return 0
  usermod -aG "$group" "$user"
}

detect_default_ssh_user() {
  if [[ -n "${SUDO_USER:-}" && "${SUDO_USER}" != "root" ]]; then
    echo "$SUDO_USER"
    return
  fi
  if user_exists moode; then echo moode; return; fi
  if user_exists pi; then echo pi; return; fi
  awk -F: '$6 ~ /^\/home\// {print $1; exit}' /etc/passwd
}

setup_security_principal() {
  log "Configuring security principal ${SECURITY_USER}:${SECURITY_GROUP}"

  group_exists "$SECURITY_GROUP" || groupadd --system "$SECURITY_GROUP"
  user_exists "$SECURITY_USER" || useradd --system --no-create-home --shell /usr/sbin/nologin --gid "$SECURITY_GROUP" "$SECURITY_USER"

  local ssh_user
  ssh_user="$(detect_default_ssh_user || true)"
  if [[ -n "$ssh_user" ]] && user_exists "$ssh_user"; then
    local g
    for g in $(id -nG "$ssh_user"); do
      add_user_to_group_safe "$SECURITY_USER" "$g"
    done
  fi

  add_user_to_group_safe "$SECURITY_USER" "$WEB_GROUP"
  add_user_to_group_safe "$WEB_USER" "$SECURITY_GROUP"
  group_exists nginx && add_user_to_group_safe "$SECURITY_USER" nginx || true
  group_exists mysql && add_user_to_group_safe "$SECURITY_USER" mysql || true
}

grant_database_access() {
  log "Applying DB access policy"

  if [[ -f "$SQLITE_DB" ]]; then
    if command -v setfacl >/dev/null 2>&1; then
      setfacl -m "u:${SECURITY_USER}:rw" "$SQLITE_DB" || true
      setfacl -m "u:${SECURITY_USER}:rwx" "$(dirname "$SQLITE_DB")" || true
    else
      chgrp "$SECURITY_GROUP" "$SQLITE_DB" || true
      chmod g+rw "$SQLITE_DB" || true
    fi
  fi

  if [[ -S "$MYSQL_SOCKET" ]] && group_exists mysql; then
    add_user_to_group_safe "$SECURITY_USER" mysql
  fi
}

install_watchdog_service() {
  log "Ensuring ext-mgr watchdog service exists"

  cat > "$WATCHDOG_SCRIPT" <<'SH'
#!/bin/bash
set -euo pipefail
LOG_FILE="/var/log/moode_extmgr.log"
REGISTRY="/var/www/extensions/sys/registry.json"
touch "$LOG_FILE" 2>/dev/null || true
while true; do
  if [[ ! -f "$REGISTRY" ]]; then
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] [extmgr-watchdog] missing registry: $REGISTRY" >> "$LOG_FILE"
  fi
  sleep 60
done
SH
  chmod 0755 "$WATCHDOG_SCRIPT"

  cat > "$WATCHDOG_SERVICE" <<EOF
[Unit]
Description=moOde ext-mgr watchdog
After=network.target

[Service]
Type=simple
User=${SECURITY_USER}
Group=${SECURITY_GROUP}
ExecStart=${WATCHDOG_SCRIPT}
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

  systemctl daemon-reload
  systemctl enable --now "$(basename "$WATCHDOG_SERVICE")" >/dev/null 2>&1 || true
}

json_get() {
  local file="$1" key="$2"
  php -r '$f=$argv[1]; $k=$argv[2]; $j=json_decode(file_get_contents($f), true); if(!is_array($j)){exit(2);} $v=$j; foreach(explode(".",$k) as $p){ if(!is_array($v) || !array_key_exists($p,$v)){ exit(3);} $v=$v[$p]; } if(is_bool($v)){ echo $v?"true":"false"; } else if(is_array($v)){ echo json_encode($v); } else { echo (string)$v; }' "$file" "$key" 2>/dev/null || true
}

ensure_registry() {
  mkdir -p "$(dirname "$REGISTRY_PATH")"
  if [[ ! -f "$REGISTRY_PATH" ]]; then
    cat > "$REGISTRY_PATH" <<'JSON'
{
  "generated_at": "",
  "extensions": []
}
JSON
  fi

  chown "$SECURITY_USER:$SECURITY_GROUP" "$REGISTRY_PATH" 2>/dev/null || true
  chmod 0664 "$REGISTRY_PATH" 2>/dev/null || true
}

update_registry_entry() {
  local ext_id="$1" ext_name="$2" ext_entry="$3" enabled="$4"
  local ext_version="${5:-unknown}" version_source="${6:-registry}"
  ensure_registry
  php -r '
$path=$argv[1]; $id=$argv[2]; $name=$argv[3]; $entry=$argv[4]; $enabled=($argv[5]==="true"); $ver=$argv[6]; $verSrc=$argv[7];
$data=[];
if (file_exists($path)) { $data=json_decode(file_get_contents($path), true); }
if (!is_array($data)) { $data=[]; }
if (!isset($data["extensions"]) || !is_array($data["extensions"])) { $data["extensions"]=[]; }
$found=false;
foreach ($data["extensions"] as &$ext) {
  if (($ext["id"] ?? "") === $id) {
    $ext["name"]=$name;
    $ext["entry"]=$entry;
    $ext["path"]=$entry;
    $ext["enabled"]=$enabled;
    $ext["state"]=$enabled ? "active" : "inactive";
    if ($ver !== "") { $ext["version"]=$ver; }
    if ($verSrc !== "") { $ext["versionSource"]=$verSrc; }
    if (!isset($ext["pinned"])) { $ext["pinned"]=false; }
    if (!isset($ext["menuVisibility"]) || !is_array($ext["menuVisibility"])) { $ext["menuVisibility"]=["m"=>false,"library"=>false]; }
    if (!array_key_exists("m", $ext["menuVisibility"])) { $ext["menuVisibility"]["m"]=false; }
    if (!array_key_exists("library", $ext["menuVisibility"])) { $ext["menuVisibility"]["library"]=false; }
    if (!isset($ext["settingsCardOnly"])) { $ext["settingsCardOnly"]=false; }
    $ext["showInMMenu"] = (bool)$ext["menuVisibility"]["m"];
    $ext["showInLibrary"] = (bool)$ext["menuVisibility"]["library"];
    $found=true;
    break;
  }
}
unset($ext);
if (!$found) {
  $data["extensions"][]=[
    "id"=>$id,
    "name"=>$name,
    "entry"=>$entry,
    "path"=>$entry,
    "enabled"=>$enabled,
    "state"=>($enabled?"active":"inactive"),
    "pinned"=>false,
    "version"=>$ver,
    "versionSource"=>$verSrc,
    "menuVisibility"=>["m"=>false,"library"=>false],
    "showInMMenu"=>false,
    "showInLibrary"=>false,
    "settingsCardOnly"=>false
  ];
}
$data["generated_at"]=date("c");
file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
' "$REGISTRY_PATH" "$ext_id" "$ext_name" "$ext_entry" "$enabled" "$ext_version" "$version_source"

  chown "$SECURITY_USER:$SECURITY_GROUP" "$REGISTRY_PATH" 2>/dev/null || true
  chmod 0664 "$REGISTRY_PATH" 2>/dev/null || true
}

detect_extension_version() {
  local source="$1"

  if [[ -f "$source/version.txt" ]]; then
    local v
    v="$(tr -d '\r' < "$source/version.txt" | head -n 1 | xargs)"
    if [[ -n "$v" ]]; then
      echo "$v|version.txt"
      return 0
    fi
  fi

  local v_manifest
  v_manifest="$(json_get "$source/manifest.json" "version")"
  if [[ -n "$v_manifest" ]]; then
    echo "$v_manifest|manifest.json"
    return 0
  fi

  if [[ -f "$source/info.json" ]]; then
    local v_info
    v_info="$(json_get "$source/info.json" "version")"
    if [[ -n "$v_info" ]]; then
      echo "$v_info|info.json"
      return 0
    fi
  fi

  echo "unknown|unresolved"
}

set_extension_permissions() {
  local target="$1"

  chown -R "$SECURITY_USER:$SECURITY_GROUP" "$target"
  find "$target" -type d -exec chmod 755 {} \;
  find "$target" -type f -exec chmod 644 {} \;
  find "$target/scripts" -type f -name '*.sh' -exec chmod 755 {} \; 2>/dev/null || true

  for p in "$target/cache" "$target/data" "$target/cache/images"; do
    if [[ -d "$p" ]]; then
      chmod 2775 "$p"
      find "$p" -type f -exec chmod 664 {} \; 2>/dev/null || true
    fi
  done

  if command -v setfacl >/dev/null 2>&1; then
    setfacl -R -m "u:${WEB_USER}:rwX" "$target" 2>/dev/null || true
    [[ -d "$target/cache" ]] && setfacl -R -d -m "u:${WEB_USER}:rwX" "$target/cache" 2>/dev/null || true
    [[ -d "$target/data" ]] && setfacl -R -d -m "u:${WEB_USER}:rwX" "$target/data" 2>/dev/null || true
  fi
}

toggle_extension_state() {
  local manifest="$1" target="$2" wanted="$3"
  local service_name
  service_name="$(json_get "$manifest" "ext_mgr.service.name")"

  php -r '
$f=$argv[1]; $wanted=$argv[2];
$j=json_decode(file_get_contents($f), true);
if(!is_array($j)){fwrite(STDERR,"invalid manifest\n"); exit(1);}
if(!isset($j["ext_mgr"])||!is_array($j["ext_mgr"])){$j["ext_mgr"]=[];}
$j["ext_mgr"]["enabled"] = ($wanted === "enable");
$j["ext_mgr"]["state"] = ($wanted === "enable") ? "active" : "inactive";
file_put_contents($f, json_encode($j, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
' "$manifest" "$wanted"

  local enabled_flag="false"
  [[ "$wanted" == "enable" ]] && enabled_flag="true"
  local ext_id ext_name canonical_entry ext_version version_source
  ext_id="$(json_get "$manifest" "id")"
  ext_name="$(json_get "$manifest" "name")"
  ext_version="$(json_get "$manifest" "version")"
  version_source="installed-manifest"
  canonical_entry="/${ext_id}.php"
  ensure_registry
  update_registry_entry "$ext_id" "$ext_name" "$canonical_entry" "$enabled_flag" "$ext_version" "$version_source"

  if [[ -n "$service_name" ]]; then
    if [[ "$wanted" == "enable" ]]; then
      systemctl enable --now "$service_name" >/dev/null 2>&1 || true
    else
      systemctl disable --now "$service_name" >/dev/null 2>&1 || true
    fi
  fi

  log "Extension $ext_id state set to $wanted"
}

run_import() {
  local source="$1"

  [[ -n "$source" ]] || { err "Usage: $0 <extension-source-dir>"; exit 1; }
  [[ -d "$source" ]] || { err "Source dir not found: $source"; exit 1; }
  [[ -f "$source/manifest.json" ]] || { err "manifest.json missing in: $source"; exit 1; }

  local ext_id ext_name main_entry
  ext_id="$(json_get "$source/manifest.json" "id")"
  ext_name="$(json_get "$source/manifest.json" "name")"
  main_entry="$(json_get "$source/manifest.json" "main")"

  [[ -n "$ext_id" ]] || { err "manifest id missing"; exit 1; }
  [[ -n "$main_entry" ]] || { err "manifest main missing"; exit 1; }

  local target="$INSTALLED_ROOT/$ext_id"
  log "Importing extension '$ext_name' ($ext_id) to $target"

  setup_security_principal
  grant_database_access
  install_watchdog_service

  mkdir -p "$INSTALLED_ROOT"
  rm -rf "$target"
  mkdir -p "$target"

  cp -a "$source/." "$target/"

  if [[ ! -f "$target/$main_entry" ]]; then
    err "Manifest main entry file missing after import: $target/$main_entry"
    exit 1
  fi

  set_extension_permissions "$target"

  local version_info ext_version version_source
  version_info="$(detect_extension_version "$source")"
  ext_version="${version_info%%|*}"
  version_source="${version_info##*|}"

  local canonical_main="${ext_id}.php"
  ln -sfn "$target/$main_entry" "/var/www/$canonical_main"
  chown -h "$SECURITY_USER:$SECURITY_GROUP" "/var/www/$canonical_main" 2>/dev/null || true

  # Keep legacy/main entry symlink when manifest main differs from canonical id.php.
  if [[ "$main_entry" != "$canonical_main" ]]; then
    ln -sfn "$target/$main_entry" "/var/www/$main_entry"
    chown -h "$SECURITY_USER:$SECURITY_GROUP" "/var/www/$main_entry" 2>/dev/null || true
  fi

  update_registry_entry "$ext_id" "$ext_name" "/$canonical_main" "true" "$ext_version" "$version_source"

  log "Import completed. Canonical entry point: /$canonical_main | version=$ext_version ($version_source)"
}

run_mode_toggle() {
  local wanted="$1" source="$2"
  [[ -n "$source" ]] || { err "Usage: $0 --$wanted <extension-source-dir>"; exit 1; }
  [[ -f "$source/manifest.json" ]] || { err "manifest.json missing in: $source"; exit 1; }

  local ext_id target manifest
  ext_id="$(json_get "$source/manifest.json" "id")"
  target="$INSTALLED_ROOT/$ext_id"
  manifest="$target/manifest.json"
  [[ -f "$manifest" ]] || { err "Installed manifest missing: $manifest"; exit 1; }

  toggle_extension_state "$manifest" "$target" "$wanted"
}

main() {
  require_root
  require_command php

  case "$MODE" in
    import)
      run_import "$SOURCE_DIR"
      ;;
    enable)
      run_mode_toggle "enable" "$SOURCE_DIR"
      ;;
    disable)
      run_mode_toggle "disable" "$SOURCE_DIR"
      ;;
    help)
      cat <<'TXT'
ext-mgr-import-wizard

Usage:
  ext-mgr-import-wizard.sh <extension-source-dir>
  ext-mgr-import-wizard.sh --enable <extension-source-dir>
  ext-mgr-import-wizard.sh --disable <extension-source-dir>

Behavior:
  - Imports extension into /var/www/extensions/installed/<id>
  - Creates canonical symlink /var/www/<id>.php
  - Updates /var/www/extensions/registry.json with version transparency
  - Applies ext-mgr security principal and runtime permission model
TXT
      ;;
    *)
      err "Unsupported mode: $MODE"
      exit 1
      ;;
  esac
}

main
