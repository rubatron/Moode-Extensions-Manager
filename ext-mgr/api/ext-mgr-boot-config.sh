#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════════
# ext-mgr-boot-config.sh - Boot Configuration Manager for Extension Manager
# ═══════════════════════════════════════════════════════════════════════════════
#
# Manages /boot/firmware/config.txt (Trixie/Bookworm) or /boot/config.txt (Legacy)
# through a modular include-based system. Each extension gets its own fragment file.
#
# Usage:
#   ext-mgr-boot-config.sh init                    # One-time setup (add include to config.txt)
#   ext-mgr-boot-config.sh add <ext-id> <lines>    # Add/update extension fragment
#   ext-mgr-boot-config.sh remove <ext-id>         # Remove extension fragment
#   ext-mgr-boot-config.sh list                    # List all managed fragments
#   ext-mgr-boot-config.sh check <ext-id> <lines>  # Check for conflicts (dry-run)
#   ext-mgr-boot-config.sh status                  # Show current boot config status
#   ext-mgr-boot-config.sh regenerate              # Rebuild boot.conf from fragments
#
# Fragment storage:
#   /boot/firmware/ext-mgr/fragments/<ext-id>.conf  (Trixie/Bookworm)
#   /boot/ext-mgr/fragments/<ext-id>.conf           (Legacy)
#
# ═══════════════════════════════════════════════════════════════════════════════

set -euo pipefail

# ─────────────────────────────────────────────────────────────────────────────
# Configuration
# ─────────────────────────────────────────────────────────────────────────────

EXTMGR_BOOT_MARKER="# [ext-mgr] Managed boot configuration - DO NOT EDIT BELOW THIS LINE"
EXTMGR_BOOT_INCLUDE="include ext-mgr/boot.conf"
EXTMGR_BOOT_DIR_NAME="ext-mgr"
EXTMGR_FRAGMENTS_DIR="fragments"
EXTMGR_BOOT_CONF="boot.conf"

# Detect boot config location (Trixie/Bookworm vs Legacy)
detect_boot_root() {
  if [[ -d "/boot/firmware" ]]; then
    echo "/boot/firmware"
  elif [[ -d "/boot" ]]; then
    echo "/boot"
  else
    echo ""
  fi
}

BOOT_ROOT=""
CONFIG_TXT=""
EXTMGR_DIR=""
FRAGMENTS_DIR=""
BOOT_CONF=""

init_paths() {
  BOOT_ROOT=$(detect_boot_root)
  if [[ -z "$BOOT_ROOT" ]]; then
    err "Cannot detect boot directory (/boot/firmware or /boot)"
    return 1
  fi
  CONFIG_TXT="$BOOT_ROOT/config.txt"
  EXTMGR_DIR="$BOOT_ROOT/$EXTMGR_BOOT_DIR_NAME"
  FRAGMENTS_DIR="$EXTMGR_DIR/$EXTMGR_FRAGMENTS_DIR"
  BOOT_CONF="$EXTMGR_DIR/$EXTMGR_BOOT_CONF"
}

# ─────────────────────────────────────────────────────────────────────────────
# Logging
# ─────────────────────────────────────────────────────────────────────────────

LOG_DIR="${EXTMGR_LOG_DIR:-/var/www/extensions/sys/logs}"
LOG_FILE="$LOG_DIR/boot-config.log"

log() {
  mkdir -p "$LOG_DIR" 2>/dev/null || true
  printf '[%s] %s\n' "$(date +'%Y-%m-%d %H:%M:%S')" "$*" | tee -a "$LOG_FILE" 2>/dev/null || true
}

info() {
  log "INFO: $*"
  echo "$*"
}

warn() {
  log "WARN: $*"
  echo "WARNING: $*" >&2
}

err() {
  log "ERROR: $*"
  echo "ERROR: $*" >&2
}

# ─────────────────────────────────────────────────────────────────────────────
# Helper functions
# ─────────────────────────────────────────────────────────────────────────────

require_root() {
  if [[ $EUID -ne 0 ]]; then
    err "This command requires root privileges"
    exit 1
  fi
}

# Check if the include line already exists in config.txt
is_include_present() {
  grep -qF "$EXTMGR_BOOT_INCLUDE" "$CONFIG_TXT" 2>/dev/null
}

# Check if marker exists in config.txt
is_marker_present() {
  grep -qF "$EXTMGR_BOOT_MARKER" "$CONFIG_TXT" 2>/dev/null
}

# Normalize a config line (trim whitespace, lowercase for comparison)
normalize_line() {
  echo "$1" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' -e 's/[[:space:]]*=[[:space:]]*/=/'
}

# Extract the key from a config line (e.g., "dtoverlay=hifiberry-dac" -> "dtoverlay=hifiberry-dac")
# For dtparam lines, extract the full line since multiple dtparams are valid
# For dtoverlay lines, extract the overlay name for conflict detection
get_config_key() {
  local line="$1"
  local normalized
  normalized=$(normalize_line "$line")

  # Skip comments and empty lines
  [[ "$normalized" =~ ^# ]] && return
  [[ -z "$normalized" ]] && return

  # For dtoverlay, the full overlay definition is the key (allows same overlay with different params)
  if [[ "$normalized" =~ ^dtoverlay= ]]; then
    echo "$normalized"
  # For dtparam, extract param name for dedup (e.g., dtparam=spi=on -> dtparam=spi)
  elif [[ "$normalized" =~ ^dtparam=([^=]+) ]]; then
    echo "dtparam=${BASH_REMATCH[1]}"
  # For gpio, the full line is unique
  elif [[ "$normalized" =~ ^gpio= ]]; then
    echo "$normalized"
  # For other settings, use the full line
  else
    echo "$normalized"
  fi
}

# ─────────────────────────────────────────────────────────────────────────────
# Core functions
# ─────────────────────────────────────────────────────────────────────────────

# Initialize ext-mgr boot config (one-time setup)
cmd_init() {
  require_root
  init_paths || exit 1

  info "Boot config initialization"
  info "  Boot root: $BOOT_ROOT"
  info "  Config file: $CONFIG_TXT"

  if [[ ! -f "$CONFIG_TXT" ]]; then
    err "config.txt not found at $CONFIG_TXT"
    exit 1
  fi

  # Create directories
  mkdir -p "$FRAGMENTS_DIR"
  chmod 755 "$EXTMGR_DIR" "$FRAGMENTS_DIR"

  # Create empty boot.conf if it doesn't exist
  if [[ ! -f "$BOOT_CONF" ]]; then
    cat > "$BOOT_CONF" << 'EOF'
# ═══════════════════════════════════════════════════════════════════════════════
# Extension Manager - Managed Boot Configuration
# ═══════════════════════════════════════════════════════════════════════════════
# This file is auto-generated from extension fragments.
# DO NOT EDIT MANUALLY - changes will be overwritten.
#
# To add boot config for an extension, use:
#   ext-mgr-boot-config.sh add <ext-id> "dtparam=spi=on" "dtoverlay=..."
#
# Fragments are stored in: fragments/<ext-id>.conf
# ═══════════════════════════════════════════════════════════════════════════════

EOF
    chmod 644 "$BOOT_CONF"
    info "Created $BOOT_CONF"
  fi

  # Check if include already exists
  if is_include_present; then
    info "Include already present in config.txt - no changes needed"
    return 0
  fi

  # Backup config.txt
  local backup="$CONFIG_TXT.extmgr-backup.$(date +%Y%m%d-%H%M%S)"
  cp "$CONFIG_TXT" "$backup"
  info "Backed up config.txt to $backup"

  # Add marker and include at the end
  {
    echo ""
    echo "$EXTMGR_BOOT_MARKER"
    echo "$EXTMGR_BOOT_INCLUDE"
  } >> "$CONFIG_TXT"

  info "Added include directive to config.txt"
  info "Initialization complete"
  log "init completed - include added to $CONFIG_TXT"
}

# Add or update an extension's boot config fragment
cmd_add() {
  local ext_id="$1"
  shift
  local lines=("$@")

  require_root
  init_paths || exit 1

  if [[ -z "$ext_id" ]]; then
    err "Usage: ext-mgr-boot-config.sh add <ext-id> <line1> [line2] ..."
    exit 1
  fi

  if [[ ${#lines[@]} -eq 0 ]]; then
    err "No config lines provided"
    exit 1
  fi

  # Ensure directories exist
  mkdir -p "$FRAGMENTS_DIR"

  local fragment_file="$FRAGMENTS_DIR/$ext_id.conf"
  local timestamp
  timestamp=$(date +'%Y-%m-%d %H:%M:%S')

  # Check for conflicts with other extensions
  local conflicts=()
  for line in "${lines[@]}"; do
    local key
    key=$(get_config_key "$line")
    [[ -z "$key" ]] && continue

    # Check all other fragments
    for other_fragment in "$FRAGMENTS_DIR"/*.conf; do
      [[ -f "$other_fragment" ]] || continue
      [[ "$other_fragment" == "$fragment_file" ]] && continue

      local other_id
      other_id=$(basename "$other_fragment" .conf)

      while IFS= read -r other_line; do
        local other_key
        other_key=$(get_config_key "$other_line")
        [[ -z "$other_key" ]] && continue

        if [[ "$key" == "$other_key" ]]; then
          conflicts+=("$ext_id: '$line' conflicts with $other_id: '$other_line'")
        fi
      done < "$other_fragment"
    done
  done

  if [[ ${#conflicts[@]} -gt 0 ]]; then
    warn "Potential conflicts detected:"
    for conflict in "${conflicts[@]}"; do
      warn "  - $conflict"
    done
    warn "Proceeding anyway - later entry takes precedence"
  fi

  # Write fragment file
  {
    echo "# Extension: $ext_id"
    echo "# Generated: $timestamp"
    echo "# ─────────────────────────────────────────────────────────────────"
    for line in "${lines[@]}"; do
      # Skip empty lines but preserve comments
      [[ -z "$(normalize_line "$line")" ]] && continue
      echo "$line"
    done
  } > "$fragment_file"

  chmod 644 "$fragment_file"
  info "Created fragment: $fragment_file"
  log "add $ext_id - created fragment with ${#lines[@]} lines"

  # Regenerate boot.conf
  cmd_regenerate
}

# Remove an extension's boot config fragment
cmd_remove() {
  local ext_id="$1"

  require_root
  init_paths || exit 1

  if [[ -z "$ext_id" ]]; then
    err "Usage: ext-mgr-boot-config.sh remove <ext-id>"
    exit 1
  fi

  local fragment_file="$FRAGMENTS_DIR/$ext_id.conf"

  if [[ ! -f "$fragment_file" ]]; then
    info "No fragment found for $ext_id"
    return 0
  fi

  rm -f "$fragment_file"
  info "Removed fragment: $fragment_file"
  log "remove $ext_id - deleted fragment"

  # Regenerate boot.conf
  cmd_regenerate
}

# List all managed fragments
cmd_list() {
  init_paths || exit 1

  echo "Boot config fragments:"
  echo "  Location: $FRAGMENTS_DIR"
  echo ""

  if [[ ! -d "$FRAGMENTS_DIR" ]]; then
    echo "  (no fragments directory)"
    return 0
  fi

  local count=0
  for fragment in "$FRAGMENTS_DIR"/*.conf; do
    [[ -f "$fragment" ]] || continue
    local ext_id
    ext_id=$(basename "$fragment" .conf)
    local line_count
    line_count=$(grep -cv '^#\|^$' "$fragment" 2>/dev/null || echo "0")
    echo "  - $ext_id ($line_count config lines)"
    count=$((count + 1))
  done

  if [[ $count -eq 0 ]]; then
    echo "  (no fragments)"
  fi

  echo ""
  echo "Total: $count extension(s) with boot config"
}

# Check for conflicts without making changes (dry-run)
cmd_check() {
  local ext_id="$1"
  shift
  local lines=("$@")

  init_paths || exit 1

  if [[ -z "$ext_id" || ${#lines[@]} -eq 0 ]]; then
    err "Usage: ext-mgr-boot-config.sh check <ext-id> <line1> [line2] ..."
    exit 1
  fi

  echo "Checking boot config for $ext_id:"
  echo ""

  local conflicts=()
  local duplicates=()

  for line in "${lines[@]}"; do
    local key
    key=$(get_config_key "$line")
    [[ -z "$key" ]] && continue

    echo "  Checking: $line"

    # Check existing fragments
    for fragment in "$FRAGMENTS_DIR"/*.conf; do
      [[ -f "$fragment" ]] || continue

      local other_id
      other_id=$(basename "$fragment" .conf)

      # Skip self
      [[ "$other_id" == "$ext_id" ]] && continue

      while IFS= read -r other_line; do
        local other_key
        other_key=$(get_config_key "$other_line")
        [[ -z "$other_key" ]] && continue

        if [[ "$key" == "$other_key" ]]; then
          if [[ "$(normalize_line "$line")" == "$(normalize_line "$other_line")" ]]; then
            duplicates+=("    → Duplicate in $other_id (same value, can coexist)")
          else
            conflicts+=("    → CONFLICT with $other_id: '$other_line'")
          fi
        fi
      done < "$fragment"
    done

    # Check main config.txt (above the marker)
    if [[ -f "$CONFIG_TXT" ]]; then
      local before_marker
      before_marker=$(sed -n "/$EXTMGR_BOOT_MARKER/q;p" "$CONFIG_TXT" 2>/dev/null || cat "$CONFIG_TXT")

      while IFS= read -r config_line; do
        local config_key
        config_key=$(get_config_key "$config_line")
        [[ -z "$config_key" ]] && continue

        if [[ "$key" == "$config_key" ]]; then
          conflicts+=("    → Already in config.txt: '$config_line'")
        fi
      done <<< "$before_marker"
    fi
  done

  echo ""

  if [[ ${#duplicates[@]} -gt 0 ]]; then
    echo "Duplicates (info only):"
    for dup in "${duplicates[@]}"; do
      echo "$dup"
    done
    echo ""
  fi

  if [[ ${#conflicts[@]} -gt 0 ]]; then
    echo "Conflicts found:"
    for conflict in "${conflicts[@]}"; do
      echo "$conflict"
    done
    return 1
  else
    echo "No conflicts detected - safe to add"
    return 0
  fi
}

# Show current boot config status
cmd_status() {
  init_paths || exit 1

  echo "Extension Manager Boot Config Status"
  echo "═══════════════════════════════════════════════════════════════════"
  echo ""
  echo "Boot root:     $BOOT_ROOT"
  echo "Config file:   $CONFIG_TXT"
  echo "Managed dir:   $EXTMGR_DIR"
  echo "Boot conf:     $BOOT_CONF"
  echo ""

  # Check if initialized
  if is_include_present; then
    echo "Status:        ✓ Initialized (include present in config.txt)"
  else
    echo "Status:        ✗ Not initialized (run 'init' command)"
  fi
  echo ""

  # Count fragments
  local fragment_count=0
  local total_lines=0
  if [[ -d "$FRAGMENTS_DIR" ]]; then
    for fragment in "$FRAGMENTS_DIR"/*.conf; do
      [[ -f "$fragment" ]] || continue
      fragment_count=$((fragment_count + 1))
      local lines
      lines=$(grep -cv '^#\|^$' "$fragment" 2>/dev/null || echo "0")
      total_lines=$((total_lines + lines))
    done
  fi

  echo "Extensions:    $fragment_count"
  echo "Config lines:  $total_lines"
  echo ""

  # Show fragments
  if [[ $fragment_count -gt 0 ]]; then
    echo "Fragments:"
    for fragment in "$FRAGMENTS_DIR"/*.conf; do
      [[ -f "$fragment" ]] || continue
      local ext_id
      ext_id=$(basename "$fragment" .conf)
      local lines
      lines=$(grep -cv '^#\|^$' "$fragment" 2>/dev/null || echo "0")
      echo "  - $ext_id: $lines lines"

      # Show actual config lines (non-comment)
      while IFS= read -r line; do
        [[ "$line" =~ ^# ]] && continue
        [[ -z "$line" ]] && continue
        echo "      $line"
      done < "$fragment"
    done
  fi
}

# Regenerate boot.conf from all fragments
cmd_regenerate() {
  init_paths || exit 1

  if [[ ! -d "$EXTMGR_DIR" ]]; then
    info "Ext-mgr boot directory not found - nothing to regenerate"
    return 0
  fi

  local timestamp
  timestamp=$(date +'%Y-%m-%d %H:%M:%S')

  # Collect all unique config lines with source tracking
  declare -A seen_keys
  declare -a all_lines
  declare -a sources

  # Process fragments in sorted order for deterministic output
  for fragment in $(ls "$FRAGMENTS_DIR"/*.conf 2>/dev/null | sort); do
    [[ -f "$fragment" ]] || continue

    local ext_id
    ext_id=$(basename "$fragment" .conf)

    while IFS= read -r line; do
      # Skip comments and empty lines from fragments
      [[ "$line" =~ ^# ]] && continue
      [[ -z "$(normalize_line "$line")" ]] && continue

      local key
      key=$(get_config_key "$line")

      # Track duplicates - last one wins
      if [[ -n "$key" && -n "${seen_keys[$key]:-}" ]]; then
        # Find and remove previous occurrence
        local prev_src="${seen_keys[$key]}"
        warn "Duplicate key '$key' - $ext_id overrides $prev_src"
      fi

      if [[ -n "$key" ]]; then
        seen_keys[$key]="$ext_id"
      fi

      all_lines+=("$line")
      sources+=("$ext_id")
    done < "$fragment"
  done

  # Write boot.conf
  {
    echo "# ═══════════════════════════════════════════════════════════════════════════════"
    echo "# Extension Manager - Managed Boot Configuration"
    echo "# ═══════════════════════════════════════════════════════════════════════════════"
    echo "# AUTO-GENERATED - DO NOT EDIT MANUALLY"
    echo "# Generated: $timestamp"
    echo "# Source: Combined from ${#sources[@]} extension fragments"
    echo "# ═══════════════════════════════════════════════════════════════════════════════"
    echo ""

    if [[ ${#all_lines[@]} -eq 0 ]]; then
      echo "# No extension boot configurations active"
    else
      local current_ext=""
      for i in "${!all_lines[@]}"; do
        local line="${all_lines[$i]}"
        local src="${sources[$i]}"

        # Add section header when extension changes
        if [[ "$src" != "$current_ext" ]]; then
          [[ -n "$current_ext" ]] && echo ""
          echo "# ─── $src ───"
          current_ext="$src"
        fi

        echo "$line"
      done
    fi

    echo ""
    echo "# ═══════════════════════════════════════════════════════════════════════════════"
    echo "# End of managed boot configuration"
    echo "# ═══════════════════════════════════════════════════════════════════════════════"
  } > "$BOOT_CONF"

  chmod 644 "$BOOT_CONF"
  info "Regenerated $BOOT_CONF (${#all_lines[@]} config lines from $(ls "$FRAGMENTS_DIR"/*.conf 2>/dev/null | wc -l) fragments)"
  log "regenerate - wrote ${#all_lines[@]} lines to boot.conf"
}

# ─────────────────────────────────────────────────────────────────────────────
# Main entry point
# ─────────────────────────────────────────────────────────────────────────────

main() {
  local cmd="${1:-help}"
  shift || true

  case "$cmd" in
    init)
      cmd_init
      ;;
    add)
      cmd_add "$@"
      ;;
    remove)
      cmd_remove "$@"
      ;;
    list)
      cmd_list
      ;;
    check)
      cmd_check "$@"
      ;;
    status)
      cmd_status
      ;;
    regenerate)
      cmd_regenerate
      ;;
    help|--help|-h)
      cat << 'EOF'
ext-mgr-boot-config.sh - Boot Configuration Manager for Extension Manager

Usage:
  ext-mgr-boot-config.sh <command> [arguments]

Commands:
  init                          One-time setup - adds include to config.txt
  add <ext-id> <line> [...]     Add boot config for an extension
  remove <ext-id>               Remove boot config for an extension
  list                          List all managed extension fragments
  check <ext-id> <line> [...]   Check for conflicts (dry-run, no changes)
  status                        Show current boot config status
  regenerate                    Rebuild boot.conf from all fragments
  help                          Show this help message

Examples:
  # Initialize (one-time)
  sudo ext-mgr-boot-config.sh init

  # Add boot config for pirate-audio extension
  sudo ext-mgr-boot-config.sh add pirate-audio \
    "dtparam=spi=on" \
    "dtparam=i2c_arm=on" \
    "dtoverlay=hifiberry-dac" \
    "gpio=25=op,dh"

  # Check for conflicts before adding
  ext-mgr-boot-config.sh check my-extension "dtoverlay=hifiberry-dac"

  # Remove extension boot config
  sudo ext-mgr-boot-config.sh remove pirate-audio

  # View current status
  ext-mgr-boot-config.sh status

Boot Config Location:
  Trixie/Bookworm:  /boot/firmware/config.txt
  Legacy:           /boot/config.txt

Fragment Storage:
  /boot/[firmware/]ext-mgr/fragments/<ext-id>.conf

EOF
      ;;
    *)
      err "Unknown command: $cmd"
      echo "Use 'ext-mgr-boot-config.sh help' for usage information"
      exit 1
      ;;
  esac
}

main "$@"
