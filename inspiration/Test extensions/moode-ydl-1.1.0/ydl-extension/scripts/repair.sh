#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════
#  repair.sh — moode-ydl YouTube Audioplayback
#  Runs as: moode-extmgrusr (unprivileged)
#  Re-creates sandbox files if missing. Does not touch system paths.
# ═══════════════════════════════════════════════════════════════════
set -euo pipefail

EXT_ID="${EXT_MGR_EXTENSION_ID:-moode-ydl}"
ROOT="${EXT_MGR_EXTENSION_ROOT:-/var/www/extensions/installed/$EXT_ID}"
LOG_DIR="$ROOT/logs"

case "$ROOT" in
  /var/www/extensions/installed/*) ;;
  *) echo "[$EXT_ID] ABORT: unsafe ROOT" >&2; exit 1 ;;
esac

mkdir -p "$LOG_DIR" "$ROOT/cache" "$ROOT/data" "$ROOT/packages/bin"
log() { echo "[$(date +'%Y-%m-%d %H:%M:%S')] [$EXT_ID] $*" | tee -a "$LOG_DIR/install.log"; }
log "=== repair started ==="

YTDLP_BIN="$ROOT/packages/bin/yt-dlp"
YTDLP_PYLIB="$ROOT/packages/pylib"

# Re-create wrapper if missing
if [[ ! -x "$YTDLP_BIN" ]]; then
    log "wrapper missing — re-creating"
    mkdir -p "$ROOT/packages/bin"
    cat > "$YTDLP_BIN" << 'WRAPEOF'
#!/usr/bin/env bash
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PYTHONPATH="$ROOT/packages/pylib${PYTHONPATH:+:$PYTHONPATH}" \
  exec python3 -m yt_dlp "$@"
WRAPEOF
    chmod +x "$YTDLP_BIN"
    log "wrapper re-created: $YTDLP_BIN"
fi

# Re-install yt-dlp if pylib missing
if [[ ! -d "$YTDLP_PYLIB" ]] || ! PYTHONPATH="$YTDLP_PYLIB" python3 -c "import yt_dlp" 2>/dev/null; then
    log "yt-dlp not found in pylib — re-installing"
    mkdir -p "$YTDLP_PYLIB"
    pip3 install --target="$YTDLP_PYLIB" --upgrade --no-cache-dir yt-dlp \
        >> "$LOG_DIR/install.log" 2>&1 \
        && log "yt-dlp re-installed" \
        || log "ERROR: yt-dlp re-install failed"
else
    ver=$(PYTHONPATH="$YTDLP_PYLIB" python3 -c "import yt_dlp; print(yt_dlp.version.__version__)" 2>/dev/null || echo "unknown")
    log "yt-dlp OK: $ver"
fi

log "=== repair completed ==="
