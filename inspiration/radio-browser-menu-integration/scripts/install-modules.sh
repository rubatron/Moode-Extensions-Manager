#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

MOD1="$SCRIPT_DIR/install-modal-fix.sh"
MOD2="$SCRIPT_DIR/install-module-2-menu-button.sh"
MOD3="$SCRIPT_DIR/install-module-3-extension-manager.sh"

require_file() {
    local f="$1"
    if [[ ! -f "$f" ]]; then
        echo "ERROR: required script not found: $f" >&2
        exit 1
    fi
}

require_file "$MOD1"
require_file "$MOD2"
require_file "$MOD3"

run_step() {
    local id="$1"
    local label="$2"
    local script="$3"

    echo
    echo "=== [$id] $label ==="
    bash "$script"
}

run_selected() {
    local selection="$1"

    case "$selection" in
        1)
            run_step "1" "Module 1 - Modal Fix" "$MOD1"
            ;;
        2)
            run_step "2" "Module 2 - Radio Browser Menu Button" "$MOD2"
            ;;
        3)
            run_step "3" "Module 3 - Extensions Manager" "$MOD3"
            ;;
        12|21)
            run_step "1" "Module 1 - Modal Fix" "$MOD1"
            run_step "2" "Module 2 - Radio Browser Menu Button" "$MOD2"
            ;;
        13|31)
            run_step "1" "Module 1 - Modal Fix" "$MOD1"
            run_step "3" "Module 3 - Extensions Manager" "$MOD3"
            ;;
        23|32)
            run_step "2" "Module 2 - Radio Browser Menu Button" "$MOD2"
            run_step "3" "Module 3 - Extensions Manager" "$MOD3"
            ;;
        123|132|213|231|312|321|a|A|all|ALL)
            run_step "1" "Module 1 - Modal Fix" "$MOD1"
            run_step "2" "Module 2 - Radio Browser Menu Button" "$MOD2"
            run_step "3" "Module 3 - Extensions Manager" "$MOD3"
            ;;
        *)
            echo "ERROR: unknown selection '$selection'" >&2
            echo "Valid: 1,2,3,12,13,23,123,all" >&2
            exit 1
            ;;
    esac
}

print_menu() {
    cat <<'MENU'

Select installation option:
  1   Module 1 only  (Modal fix)
  2   Module 2 only  (Radio Browser menu buttons)
  3   Module 3 only  (Radio Browser menu buttons via Extensions manager)
  12  Module 1 + 2
  13  Module 1 + 3
  23  Module 2 + 3
  123 All modules
  q   Quit
MENU
}

if [[ "${1:-}" != "" ]]; then
    run_selected "$1"
    echo
    echo "OK: Selected installation completed."
    exit 0
fi

while true; do
    print_menu
    read -r -p "Choice: " choice

    case "$choice" in
        q|Q|quit|QUIT|exit|EXIT)
            echo "Cancelled."
            exit 0
            ;;
        1|2|3|12|21|13|31|23|32|123|132|213|231|312|321|a|A|all|ALL)
            run_selected "$choice"
            echo
            echo "OK: Selected installation completed."
            exit 0
            ;;
        *)
            echo "Invalid choice: $choice"
            ;;
    esac
done
