#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════════
# ext-mgr-config.sh - Centralized configuration for ext-mgr shell scripts
#
# This library provides consistent access to ext-mgr paths and settings.
# Source this file at the top of your scripts:
#   source "$(dirname "$0")/ext-mgr-config.sh"
#
# Matches PHP getDefaultSystemVariables() and JS Config module structure.
# ═══════════════════════════════════════════════════════════════════════════════

# ───────────────────────────────────────────────────────────────────────────────
# PATHS - File system paths (matching PHP paths array)
# ───────────────────────────────────────────────────────────────────────────────
EXTMGR_EXTENSIONS_ROOT="${EXTMGR_EXTENSIONS_ROOT:-/var/www/extensions}"
EXTMGR_INSTALLED_ROOT="${EXTMGR_INSTALLED_ROOT:-$EXTMGR_EXTENSIONS_ROOT/installed}"
EXTMGR_CACHE_ROOT="${EXTMGR_CACHE_ROOT:-$EXTMGR_EXTENSIONS_ROOT/cache}"
EXTMGR_BACKUP_ROOT="${EXTMGR_BACKUP_ROOT:-$EXTMGR_EXTENSIONS_ROOT/sys/backup}"
EXTMGR_REGISTRY_PATH="${EXTMGR_REGISTRY_PATH:-$EXTMGR_EXTENSIONS_ROOT/sys/registry.json}"
EXTMGR_LOGS_ROOT="${EXTMGR_LOGS_ROOT:-$EXTMGR_EXTENSIONS_ROOT/sys/logs}"
EXTMGR_EXTENSION_LOGS_ROOT="${EXTMGR_EXTENSION_LOGS_ROOT:-$EXTMGR_LOGS_ROOT/extensionslogs}"
EXTMGR_MGR_LOGS_ROOT="${EXTMGR_MGR_LOGS_ROOT:-$EXTMGR_LOGS_ROOT/ext-mgr logs}"
EXTMGR_RUNTIME_ROOT="${EXTMGR_RUNTIME_ROOT:-$EXTMGR_EXTENSIONS_ROOT/sys/.ext-mgr}"
EXTMGR_VARIABLES_PATH="${EXTMGR_VARIABLES_PATH:-$EXTMGR_RUNTIME_ROOT/variables.json}"
EXTMGR_MOODE_ROOT="${EXTMGR_MOODE_ROOT:-/var/www}"
EXTMGR_MOODE_INCLUDE="${EXTMGR_MOODE_INCLUDE:-/var/www/inc}"
EXTMGR_SQLITE_DB="${EXTMGR_SQLITE_DB:-/var/local/www/db/moode-sqlite3.db}"

# ───────────────────────────────────────────────────────────────────────────────
# SECURITY - User and group settings (matching PHP security array)
# ───────────────────────────────────────────────────────────────────────────────
EXTMGR_USER="${EXTMGR_USER:-moode-extmgrusr}"
EXTMGR_GROUP="${EXTMGR_GROUP:-moode-extmgr}"
EXTMGR_WEB_USER="${EXTMGR_WEB_USER:-www-data}"
EXTMGR_WEB_GROUP="${EXTMGR_WEB_GROUP:-www-data}"

# ───────────────────────────────────────────────────────────────────────────────
# URIS - Web paths (matching PHP uris array)
# ───────────────────────────────────────────────────────────────────────────────
EXTMGR_API_ENDPOINT="${EXTMGR_API_ENDPOINT:-/ext-mgr-api.php}"
EXTMGR_API_ENDPOINT_ALT="${EXTMGR_API_ENDPOINT_ALT:-/extensions/sys/ext-mgr-api.php}"

# ───────────────────────────────────────────────────────────────────────────────
# CANONICAL LINK PATTERN - For creating symlinks in /var/www
# ───────────────────────────────────────────────────────────────────────────────
EXTMGR_CANONICAL_LINK_PATTERN="${EXTMGR_CANONICAL_LINK_PATTERN:-/var/www/%s.php}"

# ───────────────────────────────────────────────────────────────────────────────
# HELPER FUNCTIONS
# ───────────────────────────────────────────────────────────────────────────────

# Load variables from variables.json if it exists
# Usage: extmgr_load_variables
extmgr_load_variables() {
    local vars_file="$EXTMGR_VARIABLES_PATH"
    if [[ -f "$vars_file" ]] && command -v jq &>/dev/null; then
        local value

        # Paths
        value=$(jq -r '.paths.extensionsRoot // empty' "$vars_file" 2>/dev/null)
        [[ -n "$value" ]] && EXTMGR_EXTENSIONS_ROOT="$value"

        value=$(jq -r '.paths.installedRoot // empty' "$vars_file" 2>/dev/null)
        [[ -n "$value" ]] && EXTMGR_INSTALLED_ROOT="$value"

        value=$(jq -r '.paths.cacheRoot // empty' "$vars_file" 2>/dev/null)
        [[ -n "$value" ]] && EXTMGR_CACHE_ROOT="$value"

        value=$(jq -r '.paths.backupRoot // empty' "$vars_file" 2>/dev/null)
        [[ -n "$value" ]] && EXTMGR_BACKUP_ROOT="$value"

        value=$(jq -r '.paths.logsRoot // empty' "$vars_file" 2>/dev/null)
        [[ -n "$value" ]] && EXTMGR_LOGS_ROOT="$value"

        # Security
        value=$(jq -r '.security.user // empty' "$vars_file" 2>/dev/null)
        [[ -n "$value" ]] && EXTMGR_USER="$value"

        value=$(jq -r '.security.group // empty' "$vars_file" 2>/dev/null)
        [[ -n "$value" ]] && EXTMGR_GROUP="$value"

        value=$(jq -r '.security.webUser // empty' "$vars_file" 2>/dev/null)
        [[ -n "$value" ]] && EXTMGR_WEB_USER="$value"

        value=$(jq -r '.security.webGroup // empty' "$vars_file" 2>/dev/null)
        [[ -n "$value" ]] && EXTMGR_WEB_GROUP="$value"

        return 0
    fi
    return 1
}

# Get a variable value by key path
# Usage: extmgr_get_var "paths.extensionsRoot" "/var/www/extensions"
extmgr_get_var() {
    local key_path="$1"
    local default="${2:-}"
    local vars_file="$EXTMGR_VARIABLES_PATH"

    if [[ -f "$vars_file" ]] && command -v jq &>/dev/null; then
        local jq_path=".${key_path//./][.}"
        jq_path="${jq_path//]\[/.}"
        local value
        value=$(jq -r "$jq_path // empty" "$vars_file" 2>/dev/null)
        if [[ -n "$value" ]]; then
            echo "$value"
            return 0
        fi
    fi

    echo "$default"
}

# Get extension-specific variable
# Usage: extmgr_get_ext_var "my-extension" "settings.enabled" "true"
extmgr_get_ext_var() {
    local ext_id="$1"
    local key_path="$2"
    local default="${3:-}"
    local vars_file="$EXTMGR_INSTALLED_ROOT/$ext_id/variables.json"

    if [[ -f "$vars_file" ]] && command -v jq &>/dev/null; then
        local jq_path=".${key_path//./][.}"
        jq_path="${jq_path//]\[/.}"
        local value
        value=$(jq -r "$jq_path // empty" "$vars_file" 2>/dev/null)
        if [[ -n "$value" ]]; then
            echo "$value"
            return 0
        fi
    fi

    echo "$default"
}

# Set extension-specific variable
# Usage: extmgr_set_ext_var "my-extension" "settings.enabled" "false"
extmgr_set_ext_var() {
    local ext_id="$1"
    local key_path="$2"
    local value="$3"
    local vars_file="$EXTMGR_INSTALLED_ROOT/$ext_id/variables.json"

    if ! command -v jq &>/dev/null; then
        echo "Error: jq is required for extmgr_set_ext_var" >&2
        return 1
    fi

    local dir
    dir=$(dirname "$vars_file")
    mkdir -p "$dir"

    if [[ ! -f "$vars_file" ]]; then
        echo '{}' > "$vars_file"
    fi

    local jq_path=".${key_path//./][.}"
    jq_path="${jq_path//]\[/.}"

    local tmp_file
    tmp_file=$(mktemp)
    if jq "$jq_path = \$val" --arg val "$value" "$vars_file" > "$tmp_file"; then
        mv "$tmp_file" "$vars_file"
        chown "${EXTMGR_WEB_USER}:${EXTMGR_GROUP}" "$vars_file" 2>/dev/null || true
        chmod 664 "$vars_file" 2>/dev/null || true
        return 0
    else
        rm -f "$tmp_file"
        return 1
    fi
}

# Ensure required directories exist with correct permissions
# Usage: extmgr_ensure_dirs
extmgr_ensure_dirs() {
    local dirs=(
        "$EXTMGR_EXTENSIONS_ROOT"
        "$EXTMGR_INSTALLED_ROOT"
        "$EXTMGR_CACHE_ROOT"
        "$EXTMGR_BACKUP_ROOT"
        "$EXTMGR_LOGS_ROOT"
        "$EXTMGR_EXTENSION_LOGS_ROOT"
        "$EXTMGR_MGR_LOGS_ROOT"
        "$EXTMGR_RUNTIME_ROOT"
    )

    for dir in "${dirs[@]}"; do
        if [[ ! -d "$dir" ]]; then
            mkdir -p "$dir"
            chown "${EXTMGR_WEB_USER}:${EXTMGR_GROUP}" "$dir" 2>/dev/null || true
            chmod 775 "$dir" 2>/dev/null || true
        fi
    done
}

# Log to ext-mgr manager logs
# Usage: extmgr_log "INFO" "Something happened"
extmgr_log() {
    local level="${1:-INFO}"
    local message="${2:-}"
    local log_file="$EXTMGR_MGR_LOGS_ROOT/ext-mgr.log"

    mkdir -p "$EXTMGR_MGR_LOGS_ROOT" 2>/dev/null || true
    printf '[%s] [%s] %s\n' "$(date +'%Y-%m-%d %H:%M:%S')" "$level" "$message" >> "$log_file"
}

# Log to extension-specific logs
# Usage: extmgr_ext_log "my-extension" "INFO" "Something happened"
extmgr_ext_log() {
    local ext_id="$1"
    local level="${2:-INFO}"
    local message="${3:-}"

    [[ -z "$ext_id" ]] && return 1

    local global_log="$EXTMGR_EXTENSION_LOGS_ROOT/$ext_id/extension.log"
    local local_log="$EXTMGR_INSTALLED_ROOT/$ext_id/logs/extension.log"

    mkdir -p "$(dirname "$global_log")" 2>/dev/null || true
    mkdir -p "$(dirname "$local_log")" 2>/dev/null || true

    local entry
    entry=$(printf '[%s] [%s] %s\n' "$(date +'%Y-%m-%d %H:%M:%S')" "$level" "$message")
    echo "$entry" >> "$global_log"
    echo "$entry" >> "$local_log"
}

# Print all configuration values (for debugging)
# Usage: extmgr_debug_config
extmgr_debug_config() {
    echo "=== ext-mgr Configuration ==="
    echo ""
    echo "Paths:"
    echo "  EXTMGR_EXTENSIONS_ROOT=$EXTMGR_EXTENSIONS_ROOT"
    echo "  EXTMGR_INSTALLED_ROOT=$EXTMGR_INSTALLED_ROOT"
    echo "  EXTMGR_CACHE_ROOT=$EXTMGR_CACHE_ROOT"
    echo "  EXTMGR_BACKUP_ROOT=$EXTMGR_BACKUP_ROOT"
    echo "  EXTMGR_REGISTRY_PATH=$EXTMGR_REGISTRY_PATH"
    echo "  EXTMGR_LOGS_ROOT=$EXTMGR_LOGS_ROOT"
    echo "  EXTMGR_RUNTIME_ROOT=$EXTMGR_RUNTIME_ROOT"
    echo "  EXTMGR_VARIABLES_PATH=$EXTMGR_VARIABLES_PATH"
    echo "  EXTMGR_MOODE_ROOT=$EXTMGR_MOODE_ROOT"
    echo "  EXTMGR_SQLITE_DB=$EXTMGR_SQLITE_DB"
    echo ""
    echo "Security:"
    echo "  EXTMGR_USER=$EXTMGR_USER"
    echo "  EXTMGR_GROUP=$EXTMGR_GROUP"
    echo "  EXTMGR_WEB_USER=$EXTMGR_WEB_USER"
    echo "  EXTMGR_WEB_GROUP=$EXTMGR_WEB_GROUP"
    echo ""
    echo "Variables file exists: $([[ -f "$EXTMGR_VARIABLES_PATH" ]] && echo "yes" || echo "no")"
    echo "jq available: $(command -v jq &>/dev/null && echo "yes" || echo "no")"
}

# ───────────────────────────────────────────────────────────────────────────────
# AUTO-LOAD: Try to load custom variables if variables.json exists
# ───────────────────────────────────────────────────────────────────────────────
if [[ -f "$EXTMGR_VARIABLES_PATH" ]]; then
    extmgr_load_variables 2>/dev/null || true
fi
