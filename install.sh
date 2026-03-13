#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$SCRIPT_DIR"

SRC_PAGE=""
SRC_API=""
SRC_META=""
SRC_REGISTRY=""
SRC_RELEASE=""
SRC_VERSION=""
SRC_INTEGRITY=""
SRC_JS=""
SRC_MODAL_FIX_JS=""
SRC_HOVER_MENU_JS=""
SRC_CSS=""
SRC_REGISTRY_SYNC_SCRIPT=""
SRC_IMPORT_WIZARD_SCRIPT=""
SRC_GUIDANCE_MD=""
SRC_REQUIREMENTS_MD=""
SRC_FAQ_MD=""

TARGET_EXT_DIR="/var/www/extensions"
TARGET_SYS_DIR="$TARGET_EXT_DIR/sys"
TARGET_ASSETS_DIR="$TARGET_SYS_DIR/assets"
TARGET_JS_DIR="$TARGET_ASSETS_DIR/js"
TARGET_CSS_DIR="$TARGET_ASSETS_DIR/css"
TARGET_CONTENT_DIR="$TARGET_SYS_DIR/content"
TARGET_PAGE="$TARGET_SYS_DIR/ext-mgr.php"
TARGET_API="$TARGET_SYS_DIR/ext-mgr-api.php"
TARGET_META="$TARGET_SYS_DIR/ext-mgr.meta.json"
TARGET_REGISTRY="$TARGET_SYS_DIR/registry.json"
TARGET_RELEASE="$TARGET_SYS_DIR/ext-mgr.release.json"
TARGET_VERSION="$TARGET_SYS_DIR/ext-mgr.version"
TARGET_INTEGRITY="$TARGET_SYS_DIR/ext-mgr.integrity.json"
TARGET_JS="$TARGET_JS_DIR/ext-mgr.js"
TARGET_MODAL_FIX_JS="$TARGET_JS_DIR/ext-mgr-modal-fix.js"
TARGET_CSS="$TARGET_CSS_DIR/ext-mgr.css"
TARGET_HOVER_MENU_JS="$TARGET_JS_DIR/ext-mgr-hover-menu.js"
TARGET_SCRIPT_DIR="$TARGET_SYS_DIR/scripts"
TARGET_REGISTRY_SYNC_SCRIPT="$TARGET_SCRIPT_DIR/ext-mgr-registry-sync.sh"
TARGET_IMPORT_WIZARD_SCRIPT="$TARGET_SCRIPT_DIR/ext-mgr-import-wizard.sh"
TARGET_GUIDANCE_MD="$TARGET_CONTENT_DIR/guidance.md"
TARGET_REQUIREMENTS_MD="$TARGET_CONTENT_DIR/developer-requirements.md"
TARGET_FAQ_MD="$TARGET_CONTENT_DIR/faq.md"
TARGET_CACHE_DIR="$TARGET_EXT_DIR/cache"
TARGET_BACKUP_DIR="$TARGET_SYS_DIR/backup"
TARGET_INSTALLED_ROOT="$TARGET_EXT_DIR/installed"
TARGET_RUNTIME_ROOT="$TARGET_SYS_DIR/.ext-mgr"
TARGET_RUNTIME_CACHE="$TARGET_RUNTIME_ROOT/cache"
TARGET_RUNTIME_DATA="$TARGET_RUNTIME_ROOT/data"
TARGET_RUNTIME_LOGS="$TARGET_RUNTIME_ROOT/logs"

SYMLINK_HELPER="/usr/local/sbin/ext-mgr-repair-symlink"
SYMLINK_SUDOERS="/etc/sudoers.d/ext-mgr"
SECURITY_GROUP="moode-extmgr"
SECURITY_USER="moode-extmgrusr"
WEB_USER="www-data"

HEADER_FILE="/var/www/header.php"
FOOTER_MIN_FILE="/var/www/footer.min.php"
FOOTER_FILE="/var/www/footer.php"
INDEX_TEMPLATE_FILE="/var/www/templates/indextpl.min.html"
RB_FILE="/var/www/extensions/installed/radio-browser/radio-browser.php"
RB_JS_FILE="/var/www/extensions/installed/radio-browser/assets/radio-browser-modal-fix.js"

ACTION="install"
REPAIR_FROM_MAIN=0
SKIP_MODULE1=0
REPAIR_TMP_DIR=""
ORIG_ARGC="$#"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --skip-module1)
            SKIP_MODULE1=1
            shift
            ;;
        --with-radio-browser-integration)
            SKIP_MODULE1=0
            shift
            ;;
        --action)
            ACTION="${2:-install}"
            shift 2
            ;;
        --install)
            ACTION="install"
            shift
            ;;
        --repair)
            ACTION="repair"
            shift
            ;;
        --repair-from-main)
            ACTION="repair"
            REPAIR_FROM_MAIN=1
            shift
            ;;
        --uninstall)
            ACTION="uninstall"
            shift
            ;;
        --restore-oobe)
            ACTION="restore-oobe"
            shift
            ;;
        --help|-h)
            ACTION="help"
            shift
            ;;
        *)
            echo "ERROR: unknown option: $1" >&2
            ACTION="help"
            shift
            ;;
    esac
done

if [[ "${EUID}" -eq 0 ]]; then
    SUDO=""
else
    SUDO="sudo"
fi

set_source_root() {
    local root="$1"
    SRC_PAGE="$root/ext-mgr.php"
    SRC_API="$root/ext-mgr-api.php"
    SRC_META="$root/ext-mgr.meta.json"
    SRC_REGISTRY="$root/registry.json"
    SRC_RELEASE="$root/ext-mgr.release.json"
    SRC_VERSION="$root/ext-mgr.version"
    SRC_INTEGRITY="$root/ext-mgr.integrity.json"
    SRC_JS="$root/assets/js/ext-mgr.js"
    SRC_MODAL_FIX_JS="$root/assets/js/ext-mgr-modal-fix.js"
    SRC_HOVER_MENU_JS="$root/assets/js/ext-mgr-hover-menu.js"
    SRC_CSS="$root/assets/css/ext-mgr.css"
    SRC_REGISTRY_SYNC_SCRIPT="$root/scripts/ext-mgr-registry-sync.sh"
    SRC_IMPORT_WIZARD_SCRIPT="$root/scripts/ext-mgr-import-wizard.sh"
    SRC_GUIDANCE_MD="$root/content/guidance.md"
    SRC_REQUIREMENTS_MD="$root/content/developer-requirements.md"
    SRC_FAQ_MD="$root/content/faq.md"
}

set_source_root "$PROJECT_ROOT"

detect_primary_user() {
    if [[ -n "${SUDO_USER:-}" && "${SUDO_USER}" != "root" ]]; then
        echo "$SUDO_USER"
        return 0
    fi
    if id -u pi >/dev/null 2>&1; then
        echo "pi"
        return 0
    fi
    awk -F: '$3 >= 1000 && $1 != "nobody" { print $1; exit }' /etc/passwd
}

sync_security_user_groups() {
    local source_user="$1"
    if [[ -z "$source_user" ]] || ! id -u "$source_user" >/dev/null 2>&1; then
        return 0
    fi

    local group_name
    for group_name in $(id -nG "$source_user"); do
        if getent group "$group_name" >/dev/null 2>&1; then
            $SUDO usermod -aG "$group_name" "$SECURITY_USER" || true
        fi
    done
}

ensure_extmgr_structure_permissions() {
    local dirs=(
        "$TARGET_EXT_DIR"
        "$TARGET_SYS_DIR"
        "$TARGET_ASSETS_DIR"
        "$TARGET_JS_DIR"
        "$TARGET_CSS_DIR"
        "$TARGET_CONTENT_DIR"
        "$TARGET_SCRIPT_DIR"
        "$TARGET_CACHE_DIR"
        "$TARGET_BACKUP_DIR"
        "$TARGET_INSTALLED_ROOT"
        "$TARGET_RUNTIME_ROOT"
        "$TARGET_RUNTIME_CACHE"
        "$TARGET_RUNTIME_DATA"
        "$TARGET_RUNTIME_LOGS"
    )

    local d
    for d in "${dirs[@]}"; do
        $SUDO mkdir -p "$d"
        $SUDO chown root:"$SECURITY_GROUP" "$d"
        $SUDO chmod 2775 "$d"
    done

    if [[ -f "$TARGET_REGISTRY" ]]; then
        $SUDO chown "$SECURITY_USER":"$SECURITY_GROUP" "$TARGET_REGISTRY"
        $SUDO chmod 0664 "$TARGET_REGISTRY"
    fi

    local runtime_files=(
        "$TARGET_META"
        "$TARGET_REGISTRY"
        "$TARGET_RELEASE"
        "$TARGET_VERSION"
        "$TARGET_INTEGRITY"
    )

    local f
    for f in "${runtime_files[@]}"; do
        if [[ -f "$f" ]]; then
            $SUDO chown "$SECURITY_USER":"$SECURITY_GROUP" "$f"
            $SUDO chmod 0664 "$f"
        fi
    done

    if command -v setfacl >/dev/null 2>&1; then
        for d in "${dirs[@]}"; do
            $SUDO setfacl -m "u:${WEB_USER}:rwX" "$d" 2>/dev/null || true
            $SUDO setfacl -m "u:${SECURITY_USER}:rwX" "$d" 2>/dev/null || true
            $SUDO setfacl -d -m "u:${WEB_USER}:rwX" "$d" 2>/dev/null || true
            $SUDO setfacl -d -m "u:${SECURITY_USER}:rwX" "$d" 2>/dev/null || true
        done

        if [[ -f "$TARGET_REGISTRY" ]]; then
            $SUDO setfacl -m "u:${WEB_USER}:rw" "$TARGET_REGISTRY" 2>/dev/null || true
            $SUDO setfacl -m "u:${SECURITY_USER}:rw" "$TARGET_REGISTRY" 2>/dev/null || true
        fi

        for f in "${runtime_files[@]}"; do
            if [[ -f "$f" ]]; then
                $SUDO setfacl -m "u:${WEB_USER}:rw" "$f" 2>/dev/null || true
                $SUDO setfacl -m "u:${SECURITY_USER}:rw" "$f" 2>/dev/null || true
            fi
        done
    fi
}

require_file() {
    local path="$1"
    if [[ ! -f "$path" ]]; then
        echo "ERROR: required file not found: $path" >&2
        exit 1
    fi
}

print_usage() {
    cat <<EOF
Usage: ./install.sh [options]

Options:
  (no args)             Interactive menu (default install / advanced)
  --install              Install/upgrade ext-mgr (default)
  --repair               Repair local installation using workspace files
  --repair-from-main     Repair installation using files fetched from main branch
  --uninstall            Remove ext-mgr files/symlinks and helpers
  --restore-oobe         Restore moOde OOBE web files using backup script
  --with-radio-browser-integration
                         Enable radio-browser compatibility patching (default)
  --skip-module1         Skip radio-browser specific module patching
  --help, -h             Show this help
EOF
}

confirm_destructive_action() {
    local action_label="$1"
    local answer=""

    if [[ ! -t 0 ]]; then
        echo "WARN: non-interactive shell detected, skipping confirmation for ${action_label}." >&2
        return 0
    fi

    echo
    echo "WARNING: You are about to run '${action_label}'."
    printf "Type YES to continue: "
    read -r answer
    if [[ "$answer" != "YES" ]]; then
        echo "INFO: cancelled ${action_label}."
        exit 0
    fi
}

show_interactive_menu() {
    local choice adv
    echo
    echo "ext-mgr installer"
    echo "1) Default install (recommended)"
    echo "2) Advanced"
    echo "0) Exit"
    printf "Select [1]: "
    read -r choice
    choice="${choice:-1}"

    case "$choice" in
        1)
            ACTION="install"
            ;;
        2)
            echo
            echo "Advanced actions"
            echo "1) Install/upgrade"
            echo "2) Uninstall"
            echo "3) Repair (workspace files)"
            echo "4) Repair (fetch from main)"
            echo "5) Restore moOde OOBE"
            echo "0) Exit"
            printf "Select [1]: "
            read -r adv
            adv="${adv:-1}"
            case "$adv" in
                1)
                    ACTION="install"
                    ;;
                2)
                    ACTION="uninstall"
                    ;;
                3)
                    ACTION="repair"
                    REPAIR_FROM_MAIN=0
                    ;;
                4)
                    ACTION="repair"
                    REPAIR_FROM_MAIN=1
                    ;;
                5)
                    ACTION="restore-oobe"
                    ;;
                0)
                    ACTION="help"
                    ;;
                *)
                    echo "WARN: invalid selection, using default install" >&2
                    ACTION="install"
                    ;;
            esac
            ;;
        0)
            ACTION="help"
            ;;
        *)
            echo "WARN: invalid selection, using default install" >&2
            ACTION="install"
            ;;
    esac
}

run_restore_oobe() {
    local restore_script=""
    local tmp_script=""

    if [[ -f "$PROJECT_ROOT/scripts/moode-oobe-restore.sh" ]]; then
        restore_script="$PROJECT_ROOT/scripts/moode-oobe-restore.sh"
    elif [[ -f "/home/pi/ext-mgr-tools/moode-oobe-restore.sh" ]]; then
        restore_script="/home/pi/ext-mgr-tools/moode-oobe-restore.sh"
    else
        tmp_script="$(mktemp /tmp/moode-oobe-restore.XXXXXX.sh)"
        if command -v curl >/dev/null 2>&1; then
            curl -fsSL "https://raw.githubusercontent.com/rubatron/Moode-Extensions-Manager/main/scripts/moode-oobe-restore.sh" -o "$tmp_script"
        elif command -v wget >/dev/null 2>&1; then
            wget -q -O "$tmp_script" "https://raw.githubusercontent.com/rubatron/Moode-Extensions-Manager/main/scripts/moode-oobe-restore.sh"
        else
            echo "ERROR: curl or wget is required to fetch moode-oobe-restore.sh" >&2
            return 1
        fi
        chmod +x "$tmp_script"
        restore_script="$tmp_script"
    fi

    echo "INFO: restoring moOde OOBE web files using: $restore_script"
    $SUDO bash "$restore_script"

    if [[ -n "$tmp_script" && -f "$tmp_script" ]]; then
        rm -f "$tmp_script"
    fi

    echo "INFO: OOBE restore completed."
}

graceful_finalize_services() {
    local ready=0
    local i code

    echo "[11/11] Graceful reload and health wait..."
    if command -v systemctl >/dev/null 2>&1; then
        for svc in nginx apache2 php8.3-fpm php8.2-fpm php8.1-fpm php-fpm; do
            if systemctl list-unit-files | grep -q "^${svc}\\.service"; then
                $SUDO systemctl reload "$svc" 2>/dev/null || true
            fi
        done
    fi

    if command -v curl >/dev/null 2>&1; then
        for i in $(seq 1 20); do
            code="$(curl -s -o /dev/null -w "%{http_code}" http://localhost/index.php || true)"
            if [[ "$code" == "200" || "$code" == "302" ]]; then
                ready=1
                echo "INFO: Web UI health check passed (HTTP $code)."
                break
            fi
            sleep 1
        done
    fi

    if [[ "$ready" -eq 0 ]]; then
        echo "WARN: health check did not confirm ready state within timeout."
    fi
}

read_version_value() {
    local path="$1"
    if [[ ! -f "$path" ]]; then
        echo ""
        return 0
    fi
    tr -d '\r' < "$path" | head -n 1 | xargs
}

version_compare() {
    local a="$1" b="$2"
    if [[ "$a" == "$b" ]]; then
        echo 0
        return 0
    fi
    if command -v sort >/dev/null 2>&1; then
        local first
        first="$(printf '%s\n%s\n' "$a" "$b" | sort -V | head -n 1)"
        if [[ "$first" == "$a" ]]; then
            echo -1
        else
            echo 1
        fi
        return 0
    fi
    echo 0
}

print_version_warning() {
    local source_ver target_ver cmp
    source_ver="$(read_version_value "$SRC_VERSION")"
    target_ver="$(read_version_value "$TARGET_VERSION")"

    if [[ -z "$source_ver" ]]; then
        echo "WARN: source version file missing/empty: $SRC_VERSION" >&2
        return 0
    fi

    if [[ -z "$target_ver" ]]; then
        echo "INFO: fresh install target (no existing ext-mgr.version found)."
        return 0
    fi

    cmp="$(version_compare "$source_ver" "$target_ver")"
    if [[ "$cmp" == "1" ]]; then
        echo "INFO: upgrade detected target=$target_ver -> source=$source_ver"
    elif [[ "$cmp" == "-1" ]]; then
        echo "WARN: downgrade detected target=$target_ver -> source=$source_ver"
    else
        echo "INFO: reinstalling same version: $source_ver"
    fi
}

fetch_from_main_branch() {
    local base_url="https://raw.githubusercontent.com/rubatron/Moode-Extensions-Manager/main"
    local tmp_dir
    tmp_dir="$(mktemp -d)"
    REPAIR_TMP_DIR="$tmp_dir"

    local required=(
        "ext-mgr.php"
        "ext-mgr-api.php"
        "ext-mgr.meta.json"
        "registry.json"
        "ext-mgr.release.json"
        "ext-mgr.version"
        "ext-mgr.integrity.json"
        "assets/js/ext-mgr.js"
        "assets/js/ext-mgr-modal-fix.js"
        "assets/js/ext-mgr-hover-menu.js"
        "assets/css/ext-mgr.css"
        "content/guidance.md"
        "content/developer-requirements.md"
        "content/faq.md"
        "scripts/ext-mgr-import-wizard.sh"
        "scripts/ext-mgr-registry-sync.sh"
    )

    echo "INFO: fetching repair payload from main branch..."

    local rel target_dir target_file url
    for rel in "${required[@]}"; do
        target_dir="$tmp_dir/$(dirname "$rel")"
        target_file="$tmp_dir/$rel"
        mkdir -p "$target_dir"
        url="$base_url/$rel"

        if command -v curl >/dev/null 2>&1; then
            if ! curl -fsSL "$url" -o "$target_file"; then
                echo "ERROR: failed to fetch $url" >&2
                rm -rf "$tmp_dir"
                return 1
            fi
        elif command -v wget >/dev/null 2>&1; then
            if ! wget -q -O "$target_file" "$url"; then
                echo "ERROR: failed to fetch $url" >&2
                rm -rf "$tmp_dir"
                return 1
            fi
        else
            echo "ERROR: curl or wget is required for --repair-from-main" >&2
            rm -rf "$tmp_dir"
            return 1
        fi
    done

    PROJECT_ROOT="$tmp_dir"
    set_source_root "$PROJECT_ROOT"
    echo "INFO: repair source switched to main branch payload: $PROJECT_ROOT"
    return 0
}

cleanup_tmp_dir() {
    if [[ -n "$REPAIR_TMP_DIR" && -d "$REPAIR_TMP_DIR" ]]; then
        rm -rf "$REPAIR_TMP_DIR"
    fi
}

trap cleanup_tmp_dir EXIT

run_uninstall() {
    local stamp
    stamp="$(date +%Y%m%d-%H%M%S)"
    local uninstall_backup_dir="$TARGET_BACKUP_DIR/uninstall-$stamp"
    $SUDO mkdir -p "$uninstall_backup_dir"

    echo "[uninstall] Backing up core files where present..."
    for f in "$TARGET_PAGE" "$TARGET_API" "$TARGET_META" "$TARGET_RELEASE" "$TARGET_VERSION" "$TARGET_INTEGRITY" "$TARGET_JS" "$TARGET_MODAL_FIX_JS" "$TARGET_CSS" "$TARGET_HOVER_MENU_JS" "$TARGET_REGISTRY" "$TARGET_REGISTRY_SYNC_SCRIPT" "$TARGET_IMPORT_WIZARD_SCRIPT"; do
        if [[ -f "$f" ]]; then
            rel="${f#/var/www/extensions/sys/}"
            if [[ "$rel" == "$f" ]]; then
                rel="$(basename "$f")"
            fi
            $SUDO mkdir -p "$uninstall_backup_dir/$(dirname "$rel")"
            $SUDO cp -a "$f" "$uninstall_backup_dir/$rel"
        fi
    done

    echo "[uninstall] Removing ext-mgr files/symlinks/helpers..."
    $SUDO rm -f "$TARGET_PAGE" "$TARGET_API" "$TARGET_META" "$TARGET_RELEASE" "$TARGET_VERSION" "$TARGET_INTEGRITY" "$TARGET_JS" "$TARGET_MODAL_FIX_JS" "$TARGET_CSS" "$TARGET_HOVER_MENU_JS" "$TARGET_REGISTRY_SYNC_SCRIPT" "$TARGET_IMPORT_WIZARD_SCRIPT"
    $SUDO rm -f /var/www/extensions/ext-mgr.php /var/www/extensions/ext-mgr-api.php /var/www/extensions/ext-mgr.meta.json /var/www/extensions/ext-mgr.release.json /var/www/extensions/ext-mgr.version /var/www/extensions/ext-mgr.integrity.json /var/www/extensions/registry.json /var/www/extensions/ext-mgr-hover-menu.js
    $SUDO rm -f /var/www/extensions/assets/js/ext-mgr.js /var/www/extensions/assets/css/ext-mgr.css
    $SUDO rm -f /var/www/ext-mgr.php /var/www/ext-mgr-api.php /var/www/extensions-manager.php
    $SUDO rm -f "$SYMLINK_HELPER" "$SYMLINK_SUDOERS"

    echo "[uninstall] Completed. Registry kept at $TARGET_REGISTRY (if present)."
}

patch_index_template_menu() {
    if [[ ! -f "$INDEX_TEMPLATE_FILE" ]]; then
        echo "WARN: index template not found, skipping module 3 menu patch" >&2
        return 0
    fi

    $SUDO python3 - <<'PY'
from pathlib import Path

p = Path('/var/www/templates/indextpl.min.html')
s = p.read_text(encoding='utf-8', errors='ignore')

s = s.replace("window.location.href='/extensions-manager.php';", "window.location.href='/ext-mgr.php';")
s = s.replace('/extensions-manager.php', '/ext-mgr.php')

ext_btn = '<button aria-label="Extensions" class="btn extensions-manager-btn menu-separator" href="#notarget" onclick="window.location.href=\'/ext-mgr.php\';"><i class="fa-solid fa-sharp fa-puzzle-piece"></i> Extensions</button>'

if 'extensions-manager-btn' not in s:
    marker = '</span></button> <button aria-label="Folder" class="btn folder-view-btn" href="#library-panel">'
    insert = '</span></button> <span class="extmgr-hover-menu" style="position:relative;display:block;width:100%;">' + ext_btn.replace('class="btn extensions-manager-btn menu-separator"', 'class="btn extensions-manager-btn menu-separator" style="width:100%;"') + '<div id="extmgr-hover-panel" style="display:none;position:static;min-width:0;z-index:auto;background:transparent;border:none;box-shadow:none;padding:0 0 4px 0;border-radius:0;"><div id="extmgr-hover-list"></div></div></span> <button aria-label="Folder" class="btn folder-view-btn" href="#library-panel">'
    if marker not in s:
        raise SystemExit('ERROR: unable to find library menu marker in index template')
    s = s.replace(marker, insert, 1)
elif 'extmgr-hover-menu' not in s:
    s = s.replace(ext_btn, '<span class="extmgr-hover-menu" style="position:relative;display:block;width:100%;">' + ext_btn.replace('class="btn extensions-manager-btn menu-separator"', 'class="btn extensions-manager-btn menu-separator" style="width:100%;"') + '<div id="extmgr-hover-panel" style="display:none;position:static;min-width:0;z-index:auto;background:transparent;border:none;box-shadow:none;padding:0 0 4px 0;border-radius:0;"><div id="extmgr-hover-list"></div></div></span>', 1)

# Keep Extensions as second item in Library list (after first primary item).
ext_start = s.find('<span class="extmgr-hover-menu"')
if ext_start != -1:
    ext_end = s.find('</span>', ext_start)
    first_button_start = s.find('<button aria-label=')
    if ext_end != -1 and first_button_start != -1:
        ext_end += len('</span>')
        first_button_end = s.find('</button>', first_button_start)
        if first_button_end != -1:
            first_button_end += len('</button>')
            ext_block = s[ext_start:ext_end]
            s = s[:ext_start] + s[ext_end:]
            first_button_start = s.find('<button aria-label=')
            first_button_end = s.find('</button>', first_button_start)
            if first_button_end != -1:
                first_button_end += len('</button>')
                s = s[:first_button_end] + ' ' + ext_block + s[first_button_end:]

script_tag = '<script src="/extensions/sys/assets/js/ext-mgr-hover-menu.js" defer></script>'
if script_tag not in s:
    anchor = '</span> <button aria-label="Folder" class="btn folder-view-btn" href="#library-panel">'
    if anchor in s:
        s = s.replace(anchor, '</span> ' + script_tag + ' <button aria-label="Folder" class="btn folder-view-btn" href="#library-panel">', 1)

p.write_text(s, encoding='utf-8')
print('patched index template')
PY
}

patch_header_and_footer_menu() {
    if [[ -f "$HEADER_FILE" ]]; then
        $SUDO python3 - <<'PY'
from pathlib import Path

p = Path('/var/www/header.php')
s = p.read_text(encoding='utf-8', errors='ignore')
script_tag = '<script src="/extensions/sys/assets/js/ext-mgr-hover-menu.js" defer></script>'

s = s.replace('id="ext-mgr-btn" class="btn" href="ext-mgr.php"', 'id="ext-mgr-btn" class="btn" href="/ext-mgr.php"')

btn = '<a id="ext-mgr-btn" class="btn" href="/ext-mgr.php"><span>Extensions</span><i class="fa-solid fa-sharp fa-puzzle-piece"></i></a>'
if 'id="ext-mgr-btn"' not in s:
    marker = '<a id="per-config-btn" class="btn" href="per-config.php"><span>Peripherals</span><i class="fa-solid fa-sharp fa-display"></i></a>'
    if marker in s:
        s = s.replace(marker, marker + '\n\t\t\t\t\t' + btn, 1)

if script_tag not in s:
    nav_anchor = '</div><!--main-menu-->'
    if nav_anchor in s:
        s = s.replace(nav_anchor, script_tag + '\n' + nav_anchor, 1)
    elif '</head>' in s:
        s = s.replace('</head>', script_tag + '\n</head>', 1)
    elif '</body>' in s:
        s = s.replace('</body>', script_tag + '\n</body>', 1)
    else:
        s += '\n' + script_tag + '\n'

p.write_text(s, encoding='utf-8')
print('patched header')
PY
    else
        echo "WARN: $HEADER_FILE not found, skipping top tabs extension button patch" >&2
    fi

    $SUDO python3 - <<'PY'
from pathlib import Path

tile = '<li><a href="/ext-mgr.php" class="btn btn-large"><i class="fa-solid fa-sharp fa-puzzle-piece"></i><br>Extensions</a></li>'
script_tag = '<script src="/extensions/sys/assets/js/ext-mgr-hover-menu.js" defer></script>'

for p in [Path('/var/www/footer.min.php'), Path('/var/www/footer.php')]:
    if not p.exists():
        continue

    s = p.read_text(encoding='utf-8', errors='ignore')
    s = s.replace('href="ext-mgr.php" class="btn btn-large"', 'href="/ext-mgr.php" class="btn btn-large"')

    if 'href="/ext-mgr.php" class="btn btn-large"' in s:
        if script_tag not in s:
            if '</body>' in s:
                s = s.replace('</body>', script_tag + '\n</body>', 1)
            elif '</html>' in s:
                s = s.replace('</html>', script_tag + '\n</html>', 1)
            else:
                s += '\n' + script_tag + '\n'
        p.write_text(s, encoding='utf-8')
        continue

    marker_clock = '<?php if ($section == \'index\') { ?> <li class="context-menu"'
    marker_camilla = '<li><a href="cdsp-config.php" class="btn btn-large"><i class="fa-solid fa-sharp fa-square-sliders-vertical"></i><br>CamillaDSP</a></li>'
    marker_close = '</ul></div></div><div class="modal-footer">'

    if marker_clock in s:
        s = s.replace(marker_clock, tile + ' ' + marker_clock, 1)
    elif marker_camilla in s:
        s = s.replace(marker_camilla, marker_camilla + ' ' + tile, 1)
    elif marker_close in s:
        s = s.replace(marker_close, tile + marker_close, 1)

    if script_tag not in s:
        if '</body>' in s:
            s = s.replace('</body>', script_tag + '\n</body>', 1)
        elif '</html>' in s:
            s = s.replace('</html>', script_tag + '\n</html>', 1)
        else:
            s += '\n' + script_tag + '\n'

    p.write_text(s, encoding='utf-8')

print('patched footer')
PY
}

if [[ "$ORIG_ARGC" -eq 0 && -t 0 ]]; then
    show_interactive_menu
fi

case "$ACTION" in
    help)
        print_usage
        exit 0
        ;;
    uninstall)
        confirm_destructive_action "uninstall"
        run_uninstall
        exit 0
        ;;
    restore-oobe)
        confirm_destructive_action "restore-oobe"
        run_restore_oobe
        exit 0
        ;;
    repair)
        if [[ "$REPAIR_FROM_MAIN" -eq 1 ]]; then
            fetch_from_main_branch
        fi
        echo "INFO: running repair mode (non-destructive where possible)."
        ;;
    install)
        ;;
    *)
        echo "ERROR: unsupported action: $ACTION" >&2
        print_usage
        exit 1
        ;;
esac

require_file "$SRC_PAGE"
require_file "$SRC_API"
require_file "$SRC_META"
require_file "$SRC_REGISTRY"
require_file "$SRC_RELEASE"
require_file "$SRC_VERSION"
require_file "$SRC_INTEGRITY"
require_file "$SRC_JS"
require_file "$SRC_MODAL_FIX_JS"
require_file "$SRC_HOVER_MENU_JS"
require_file "$SRC_CSS"
require_file "$SRC_REGISTRY_SYNC_SCRIPT"
require_file "$SRC_IMPORT_WIZARD_SCRIPT"
require_file "$SRC_GUIDANCE_MD"
require_file "$SRC_REQUIREMENTS_MD"
require_file "$SRC_FAQ_MD"

print_version_warning

MODULE1_REASON=""
if [[ "$SKIP_MODULE1" -eq 0 ]]; then
    if [[ ! -f "$HEADER_FILE" ]]; then
        MODULE1_REASON="header.php not found at $HEADER_FILE"
        SKIP_MODULE1=1
    elif [[ ! -f "$RB_FILE" ]]; then
        MODULE1_REASON="radio-browser.php not found at $RB_FILE"
        SKIP_MODULE1=1
    fi

    if [[ "$SKIP_MODULE1" -eq 1 ]]; then
        echo "WARN: Module 1 integration auto-skipped: $MODULE1_REASON" >&2
    fi
fi

STAMP="$(date +%Y%m%d-%H%M%S)"

echo "[0/10] Ensuring ext-mgr security principals..."
if ! getent group "$SECURITY_GROUP" >/dev/null 2>&1; then
    $SUDO groupadd --system "$SECURITY_GROUP"
fi

if ! id -u "$SECURITY_USER" >/dev/null 2>&1; then
    $SUDO useradd --system --no-create-home --shell /usr/sbin/nologin --gid "$SECURITY_GROUP" "$SECURITY_USER"
fi

$SUDO usermod -aG "$SECURITY_GROUP" "$WEB_USER" || true
$SUDO usermod -aG "$WEB_USER" "$SECURITY_USER" || true

PRIMARY_USER="$(detect_primary_user || true)"
sync_security_user_groups "$PRIMARY_USER"

echo "[1/10] Preparing target directories..."
$SUDO mkdir -p "$TARGET_EXT_DIR" "$TARGET_SYS_DIR" "$TARGET_ASSETS_DIR" "$TARGET_JS_DIR" "$TARGET_CSS_DIR" "$TARGET_CONTENT_DIR" "$TARGET_SCRIPT_DIR" "$TARGET_CACHE_DIR" "$TARGET_BACKUP_DIR" "$TARGET_INSTALLED_ROOT" "$TARGET_RUNTIME_CACHE" "$TARGET_RUNTIME_DATA" "$TARGET_RUNTIME_LOGS"

echo "[2/10] Backing up existing ext-mgr files (if present)..."
BACKUP_SNAPSHOT_DIR="$TARGET_BACKUP_DIR/install-$STAMP"
$SUDO mkdir -p "$BACKUP_SNAPSHOT_DIR"
for f in "$TARGET_PAGE" "$TARGET_API" "$TARGET_META" "$TARGET_REGISTRY" "$TARGET_RELEASE" "$TARGET_VERSION" "$TARGET_INTEGRITY" "$TARGET_JS" "$TARGET_MODAL_FIX_JS" "$TARGET_CSS" "$TARGET_HOVER_MENU_JS" "$TARGET_REGISTRY_SYNC_SCRIPT" "$TARGET_IMPORT_WIZARD_SCRIPT" "$TARGET_GUIDANCE_MD" "$TARGET_REQUIREMENTS_MD" "$TARGET_FAQ_MD"; do
    if [[ -f "$f" ]]; then
        rel="${f#/var/www/extensions/sys/}"
        if [[ "$rel" == "$f" ]]; then
            rel="$(basename "$f")"
        fi
        $SUDO mkdir -p "$BACKUP_SNAPSHOT_DIR/$(dirname "$rel")"
        $SUDO cp -a "$f" "$BACKUP_SNAPSHOT_DIR/$rel"
    fi
done

echo "[3/10] Installing ext-mgr page/api/metadata/assets..."
$SUDO install -o www-data -g www-data -m 0644 "$SRC_PAGE" "$TARGET_PAGE"
$SUDO install -o www-data -g www-data -m 0644 "$SRC_API" "$TARGET_API"
$SUDO install -o www-data -g www-data -m 0644 "$SRC_META" "$TARGET_META"
$SUDO install -o www-data -g www-data -m 0644 "$SRC_RELEASE" "$TARGET_RELEASE"
$SUDO install -o www-data -g www-data -m 0644 "$SRC_VERSION" "$TARGET_VERSION"
$SUDO install -o www-data -g www-data -m 0644 "$SRC_INTEGRITY" "$TARGET_INTEGRITY"
$SUDO install -o www-data -g www-data -m 0644 "$SRC_JS" "$TARGET_JS"
$SUDO install -o www-data -g www-data -m 0644 "$SRC_MODAL_FIX_JS" "$TARGET_MODAL_FIX_JS"
$SUDO install -o www-data -g www-data -m 0644 "$SRC_CSS" "$TARGET_CSS"
$SUDO install -o www-data -g www-data -m 0644 "$SRC_HOVER_MENU_JS" "$TARGET_HOVER_MENU_JS"
$SUDO install -o www-data -g www-data -m 0644 "$SRC_GUIDANCE_MD" "$TARGET_GUIDANCE_MD"
$SUDO install -o www-data -g www-data -m 0644 "$SRC_REQUIREMENTS_MD" "$TARGET_REQUIREMENTS_MD"
$SUDO install -o www-data -g www-data -m 0644 "$SRC_FAQ_MD" "$TARGET_FAQ_MD"
$SUDO install -o root -g "$SECURITY_GROUP" -m 0750 "$SRC_REGISTRY_SYNC_SCRIPT" "$TARGET_REGISTRY_SYNC_SCRIPT"
$SUDO install -o root -g "$SECURITY_GROUP" -m 0750 "$SRC_IMPORT_WIZARD_SCRIPT" "$TARGET_IMPORT_WIZARD_SCRIPT"

if [[ -f "$TARGET_REGISTRY" ]]; then
    echo "Existing registry detected, preserving current state at $TARGET_REGISTRY"
else
    $SUDO install -o www-data -g www-data -m 0644 "$SRC_REGISTRY" "$TARGET_REGISTRY"
fi

echo "[4/10] Creating root shortcuts..."
$SUDO ln -sfn /var/www/extensions/sys/ext-mgr.php /var/www/ext-mgr.php
$SUDO ln -sfn /var/www/extensions/sys/ext-mgr-api.php /var/www/ext-mgr-api.php
$SUDO ln -sfn /var/www/ext-mgr.php /var/www/extensions-manager.php

echo "[5/10] Installing privileged symlink repair helper..."
echo "[5.1/10] Applying ext-mgr folder and permission structure..."
ensure_extmgr_structure_permissions

echo "[5.2/10] Reloading web services to apply updated group memberships..."
if command -v systemctl >/dev/null 2>&1; then
    for svc in php8.3-fpm php8.2-fpm php8.1-fpm php-fpm nginx apache2; do
        if systemctl list-unit-files | grep -q "^${svc}\.service"; then
            $SUDO systemctl restart "$svc" || true
        fi
    done
fi

cat <<'SH' | $SUDO tee "$SYMLINK_HELPER" > /dev/null
#!/usr/bin/env bash
set -euo pipefail

EXT_ID="${1:-}"
ENTRY_HINT="${2:-}"

if [[ -z "$EXT_ID" || ! "$EXT_ID" =~ ^[a-zA-Z0-9._-]+$ ]]; then
    echo "invalid-extension-id" >&2
    exit 2
fi

is_safe_rel() {
    local p="$1"
    [[ -n "$p" && "$p" != /* && "$p" != *..* ]]
}

INSTALLED_DIR="/var/www/extensions/installed/${EXT_ID}"
if [[ ! -d "$INSTALLED_DIR" ]]; then
    echo "installed-dir-not-found" >&2
    exit 3
fi

MANIFEST_MAIN=""
if [[ -f "$INSTALLED_DIR/manifest.json" ]]; then
    MANIFEST_MAIN="$(php -r '$j=json_decode(@file_get_contents($argv[1]), true); if(is_array($j) && isset($j["main"]) && is_string($j["main"])) echo trim($j["main"]);' "$INSTALLED_DIR/manifest.json" 2>/dev/null || true)"
fi

TARGET=""
if is_safe_rel "$MANIFEST_MAIN" && [[ -f "$INSTALLED_DIR/$MANIFEST_MAIN" ]]; then
    TARGET="$INSTALLED_DIR/$MANIFEST_MAIN"
elif is_safe_rel "$ENTRY_HINT" && [[ -f "$INSTALLED_DIR/$ENTRY_HINT" ]]; then
    TARGET="$INSTALLED_DIR/$ENTRY_HINT"
elif [[ -f "$INSTALLED_DIR/${EXT_ID}.php" ]]; then
    TARGET="$INSTALLED_DIR/${EXT_ID}.php"
elif [[ -f "$INSTALLED_DIR/index.php" ]]; then
    TARGET="$INSTALLED_DIR/index.php"
else
    echo "entry-not-found" >&2
    exit 4
fi

LINK_PATH="/var/www/${EXT_ID}.php"
ln -sfn "$TARGET" "$LINK_PATH"
chown -h www-data:www-data "$LINK_PATH" 2>/dev/null || true

echo "$LINK_PATH|$TARGET"
SH

$SUDO chown root:"$SECURITY_GROUP" "$SYMLINK_HELPER"
$SUDO chmod 0750 "$SYMLINK_HELPER"

cat <<EOF | $SUDO tee "$SYMLINK_SUDOERS" > /dev/null
%$SECURITY_GROUP ALL=(root) NOPASSWD: $SYMLINK_HELPER *
EOF
$SUDO chown root:root "$SYMLINK_SUDOERS"
$SUDO chmod 0440 "$SYMLINK_SUDOERS"

echo "[6/10] Applying Module 1 (radio-browser modal fix)..."
if [[ "$SKIP_MODULE1" -eq 1 ]]; then
    if [[ -n "$MODULE1_REASON" ]]; then
        echo "Skipped Module 1 integration: $MODULE1_REASON"
    else
        echo "Skipped Module 1 integration (explicitly disabled via --skip-module1)."
    fi
else
    $SUDO cp -a "$RB_FILE" "$RB_FILE.bak-module1-$STAMP"

    if ! grep -q "radio-browser-modal-fix.js" "$RB_FILE"; then
        if grep -q 'radio-browser\.js" defer<\/script>' "$RB_FILE"; then
            $SUDO sed -i '/radio-browser\.js" defer<\/script>/a echo '\''<script src="'\'' . $extAssetsPath . '\''/radio-browser-modal-fix.js" defer><\/script>'\'' . "\\n";' "$RB_FILE"
        else
            $SUDO sed -i "/include('\/var\/www\/footer\.min\.php');/i echo '<script src=\"' . \$extAssetsPath . '\/radio-browser-modal-fix.js\" defer><\/script>' . \"\\n\";" "$RB_FILE"
        fi
    fi

    cat <<'JS' | $SUDO tee "$RB_JS_FILE" > /dev/null
(function (window, document) {
    'use strict';

    function initFix($) {
        if (!$ || !$.fn || !$.fn.modal) {
            return;
        }

        $(document).on('click.rbConfigureModalFix', 'a[href="#configure-modal"], a[href*="configure-modal"], [data-target="#configure-modal"]', function (e) {
            var $modal = $('#configure-modal');
            if (!$modal.length) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();
            $modal.removeClass('hide').modal('show');
        });

        if (window.location.hash === '#configure-modal' || window.location.hash.indexOf('configure-modal') !== -1) {
            setTimeout(function () {
                var $modal = $('#configure-modal');
                if ($modal.length) {
                    $modal.removeClass('hide').modal('show');
                }
            }, 0);
        }
    }

    if (window.jQuery) {
        initFix(window.jQuery);
        return;
    }

    document.addEventListener('DOMContentLoaded', function () {
        initFix(window.jQuery || window.$);
    });
})(window, document);
JS

    $SUDO chown www-data:www-data "$RB_JS_FILE"
    $SUDO chmod 0644 "$RB_JS_FILE"

    php -l "$RB_FILE"
fi

echo "[7/10] Applying Module 3 menu integration..."
if [[ -f "$INDEX_TEMPLATE_FILE" ]]; then
    $SUDO cp -a "$INDEX_TEMPLATE_FILE" "$INDEX_TEMPLATE_FILE.bak-extmgr-$STAMP"
fi
patch_index_template_menu
patch_header_and_footer_menu

echo "[8/10] Validating ext-mgr syntax..."
php -l "$TARGET_PAGE"
php -l "$TARGET_API"

echo "[9/10] Validation hints..."
echo "- Verify Library dropdown shows Extensions and canonical routes"
echo "- Verify Configure modal includes Extensions tile"
echo "- Verify /ext-mgr.php loads in moOde shell"

echo "[10/11] Done."
graceful_finalize_services
echo "Installed: $TARGET_PAGE, $TARGET_API, $TARGET_JS, $TARGET_HOVER_MENU_JS, $TARGET_CSS, $TARGET_META"
echo "Root endpoints: /ext-mgr.php, /ext-mgr-api.php, /extensions-manager.php"
