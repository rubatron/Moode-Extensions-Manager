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
STATE_FILE="$RUNTIME_ROOT/service-state.json"
LOG_FILE="$LOG_DIR/moode-extmgr-service.log"
REGISTRY_PATH="${EXTMGR_REGISTRY_PATH:-/var/www/extensions/sys/registry.json}"
SYS_LOG_ROOT="${EXTMGR_LOGS_ROOT:-/var/www/extensions/sys/logs}"
MGR_LOG_DIR="${EXTMGR_MGR_LOGS_ROOT:-$SYS_LOG_ROOT/ext-mgr logs}"
POLL_SECONDS="${EXT_MGR_POLL_SECONDS:-30}"

mkdir -p "$RUNTIME_ROOT" "$LOG_DIR" "$MGR_LOG_DIR"

log() {
  printf '[%s] %s\n' "$(date +'%Y-%m-%d %H:%M:%S')" "$*" >> "$LOG_FILE"
}

manager_log() {
  local kind="$1"
  shift
  local file="$MGR_LOG_DIR/${kind}.log"
  printf '[%s] %s\n' "$(date +'%Y-%m-%d %H:%M:%S')" "$*" >> "$file"
}

write_state() {
  local status="$1"
  local detail="$2"
  php -r '$path=$argv[1]; $status=$argv[2]; $detail=$argv[3]; $data=["status"=>$status,"detail"=>$detail,"updatedAt"=>date("c")]; file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);' "$STATE_FILE" "$status" "$detail"
}

log "moode-extmgr service starting"
manager_log install "moode-extmgr service started"

while true; do
  if [[ -f "$REGISTRY_PATH" ]]; then
    write_state "online" "registry-present"
    manager_log system "registry present; heartbeat online"
  else
    write_state "degraded" "registry-missing"
    log "registry missing: $REGISTRY_PATH"
    manager_log error "registry missing: $REGISTRY_PATH"
  fi
  sleep "$POLL_SECONDS"
done
