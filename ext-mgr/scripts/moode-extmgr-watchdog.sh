#!/usr/bin/env bash
set -euo pipefail

# ═══════════════════════════════════════════════════════════════════════════════
# Source centralized configuration (provides EXTMGR_* variables)
# ═══════════════════════════════════════════════════════════════════════════════
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -f "$SCRIPT_DIR/ext-mgr-config.sh" ]]; then
    source "$SCRIPT_DIR/ext-mgr-config.sh"
fi

RUNTIME_ROOT="${EXTMGR_RUNTIME_ROOT:-/var/www/extensions/sys/.ext-mgr}"
LOG_DIR="$RUNTIME_ROOT/logs"
WATCHDOG_STATE="$RUNTIME_ROOT/watchdog-state.json"
SERVICE_STATE="$RUNTIME_ROOT/service-state.json"
LOG_FILE="$LOG_DIR/moode-extmgr-watchdog.log"
SERVICE_NAME="moode-extmgr.service"
INSTALLED_ROOT="${EXTMGR_INSTALLED_ROOT:-/var/www/extensions/installed}"
SYS_LOG_ROOT="${EXTMGR_LOGS_ROOT:-/var/www/extensions/sys/logs}"
EXT_LOG_ROOT="${EXTMGR_EXTENSION_LOGS_ROOT:-$SYS_LOG_ROOT/extensionslogs}"
MGR_LOG_DIR="${EXTMGR_MGR_LOGS_ROOT:-$SYS_LOG_ROOT/ext-mgr logs}"
CHECK_SECONDS="${EXT_MGR_WATCHDOG_CHECK_SECONDS:-45}"
MAX_AGE_SECONDS="${EXT_MGR_WATCHDOG_MAX_AGE_SECONDS:-120}"

mkdir -p "$RUNTIME_ROOT" "$LOG_DIR" "$EXT_LOG_ROOT" "$MGR_LOG_DIR"

declare -A EXT_STATE_CACHE
declare -A EXT_INIT_CACHE
MGR_STATE_CACHE=""

log() {
  printf '[%s] %s\n' "$(date +'%Y-%m-%d %H:%M:%S')" "$*" >> "$LOG_FILE"
}

manager_log() {
  local kind="$1"
  shift
  local file="$MGR_LOG_DIR/${kind}.log"
  printf '[%s] %s\n' "$(date +'%Y-%m-%d %H:%M:%S')" "$*" >> "$file"
}

extension_log() {
  local ext_id="$1"
  local kind="$2"
  shift 2
  local msg="$*"

  [[ -n "$ext_id" ]] || return 0
  local global_dir="$EXT_LOG_ROOT/$ext_id"
  local local_dir="$INSTALLED_ROOT/$ext_id/logs"
  mkdir -p "$global_dir" "$local_dir"

  printf '[%s] %s\n' "$(date +'%Y-%m-%d %H:%M:%S')" "$msg" >> "$global_dir/${kind}.log"
  printf '[%s] %s\n' "$(date +'%Y-%m-%d %H:%M:%S')" "$msg" >> "$local_dir/${kind}.log"
}

init_extension_logs() {
  local ext_id="$1"
  [[ -n "$ext_id" ]] || return 0
  if [[ -n "${EXT_INIT_CACHE[$ext_id]:-}" ]]; then
    return 0
  fi
  extension_log "$ext_id" install "watchdog initialized extension logging"
  EXT_INIT_CACHE[$ext_id]=1
}

manifest_value() {
  local file="$1"
  local expr="$2"
  php -r '$j=@json_decode(@file_get_contents($argv[1]), true); if(is_array($j)){ $v='"$expr"'; if(is_scalar($v)) echo (string)$v; }' "$file" 2>/dev/null || true
}

monitor_extension_services() {
  [[ -d "$INSTALLED_ROOT" ]] || return 0

  local ext_dir manifest ext_id service_name state prev
  for ext_dir in "$INSTALLED_ROOT"/*; do
    [[ -d "$ext_dir" ]] || continue
    manifest="$ext_dir/manifest.json"
    [[ -f "$manifest" ]] || continue

    ext_id="$(manifest_value "$manifest" '$j["id"] ?? ""')"
    [[ -n "$ext_id" ]] || continue
    init_extension_logs "$ext_id"

    service_name="$(manifest_value "$manifest" '$j["ext_mgr"]["service"]["name"] ?? ""')"
    [[ -n "$service_name" ]] || continue

    state="$(systemctl is-active "$service_name" 2>/dev/null || echo "unknown")"
    prev="${EXT_STATE_CACHE[$ext_id]:-}"
    if [[ "$state" != "$prev" ]]; then
      if [[ "$state" == "active" ]]; then
        extension_log "$ext_id" system "service ${service_name} is active"
      else
        extension_log "$ext_id" error "service ${service_name} state=${state}"
      fi
      EXT_STATE_CACHE[$ext_id]="$state"
    fi
  done
}

write_state() {
  local status="$1"
  local detail="$2"
  php -r '$path=$argv[1]; $status=$argv[2]; $detail=$argv[3]; $data=["status"=>$status,"detail"=>$detail,"updatedAt"=>date("c")]; file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);' "$WATCHDOG_STATE" "$status" "$detail"
}

service_state_age_seconds() {
  if [[ ! -f "$SERVICE_STATE" ]]; then
    echo "999999"
    return 0
  fi

  php -r '$state=$argv[1]; $data=@json_decode(@file_get_contents($state), true); if(!is_array($data) || !isset($data["updatedAt"])) { echo "999999"; exit(0);} $ts=strtotime((string)$data["updatedAt"]); if($ts===false){ echo "999999"; exit(0);} $age=time()-$ts; if($age<0){$age=0;} echo (string)$age;' "$SERVICE_STATE" 2>/dev/null || echo "999999"
}

restart_extmgr_service() {
  log "watchdog restarting $SERVICE_NAME"
  manager_log error "watchdog restarting $SERVICE_NAME"
  write_state "restarting" "service-restart"
  systemctl restart "$SERVICE_NAME" >/dev/null 2>&1 || true
}

log "moode-extmgr watchdog starting"
manager_log install "moode-extmgr watchdog started"

while true; do
  current_state="$(systemctl is-active "$SERVICE_NAME" 2>/dev/null || echo "unknown")"
  if [[ "$current_state" != "$MGR_STATE_CACHE" ]]; then
    if [[ "$current_state" == "active" ]]; then
      manager_log system "$SERVICE_NAME active"
    else
      manager_log error "$SERVICE_NAME state=$current_state"
    fi
    MGR_STATE_CACHE="$current_state"
  fi

  if [[ "$current_state" != "active" ]]; then
    log "$SERVICE_NAME inactive"
    restart_extmgr_service
    write_state "degraded" "service-inactive"
    monitor_extension_services
    sleep "$CHECK_SECONDS"
    continue
  fi

  age="$(service_state_age_seconds)"
  if [[ "$age" =~ ^[0-9]+$ ]] && (( age > MAX_AGE_SECONDS )); then
    log "service heartbeat stale (${age}s > ${MAX_AGE_SECONDS}s)"
    manager_log error "service heartbeat stale (${age}s > ${MAX_AGE_SECONDS}s)"
    restart_extmgr_service
    write_state "degraded" "heartbeat-stale"
  else
    write_state "online" "service-ok"
    manager_log system "service heartbeat ok (${age}s)"
  fi

  monitor_extension_services

  sleep "$CHECK_SECONDS"
done
