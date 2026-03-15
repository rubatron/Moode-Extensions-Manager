#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════
#  install.sh — moode-ydl YouTube Audioplayback
#  Runs as: moode-extmgrusr (unprivileged)
#
#  ext-mgr already handles (as root, before this script runs):
#    - apt-get install (from manifest.json ext_mgr.install.packages)
#    - systemd service unit install + enable
#    - /etc/systemd/system/ symlinks
#
#  This script only does what moode-extmgrusr can do:
#    - pip install yt-dlp into sandbox packages/pylib/
#    - create yt-dlp wrapper script in sandbox packages/bin/
#    - create runtime directories
#
#  /usr/local/bin/yt-dlp symlink is NOT created here — moode-extmgrusr
#  has no write permission there. api.php calls yt-dlp via full sandbox path.
# ═══════════════════════════════════════════════════════════════════
set -euo pipefail

EXT_ID="${EXT_MGR_EXTENSION_ID:-moode-ydl}"
ROOT="${EXT_MGR_EXTENSION_ROOT:-/var/www/extensions/installed/$EXT_ID}"
LOG_DIR="$ROOT/logs"
LOG_FILE="$LOG_DIR/install.log"
YTDLP_BIN="$ROOT/packages/bin/yt-dlp"
YTDLP_PYLIB="$ROOT/packages/pylib"

case "$ROOT" in
  /var/www/extensions/installed/*) ;;
  *)
    echo "[$EXT_ID] ABORT: unsafe ROOT='$ROOT'" >&2
    exit 1 ;;
esac

mkdir -p "$LOG_DIR" "$ROOT/cache" "$ROOT/data" "$ROOT/packages/bin" "$YTDLP_PYLIB"
log() { echo "[$(date +'%Y-%m-%d %H:%M:%S')] [$EXT_ID] $*" | tee -a "$LOG_FILE"; }

log "=== install started === ROOT=$ROOT"
log "running as: $(id -un) ($(id -u))"

# ── Install yt-dlp into sandbox (pip --target) ────────────────────
# python3-pip is declared in manifest.json ext_mgr.install.packages
# and installed by ext-mgr as root before this script runs.
log "pip: installing yt-dlp → $YTDLP_PYLIB"

pip3 install \
    --target="$YTDLP_PYLIB" \
    --upgrade \
    --no-cache-dir \
    yt-dlp \
    >> "$LOG_FILE" 2>&1 \
    || { log "ERROR: pip install yt-dlp failed — check $LOG_FILE"; exit 1; }

log "yt-dlp installed to $YTDLP_PYLIB"

# ── Create yt-dlp wrapper script ──────────────────────────────────
# api.php calls this wrapper via full path — no PATH dependency needed.
# The wrapper sets PYTHONPATH to our pylib and invokes the yt_dlp module.
cat > "$YTDLP_BIN" << 'WRAPEOF'
#!/usr/bin/env bash
# moode-ydl yt-dlp wrapper — sandboxed, called by api.php via full path
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PYTHONPATH="$ROOT/packages/pylib${PYTHONPATH:+:$PYTHONPATH}" \
  exec python3 -m yt_dlp "$@"
WRAPEOF
chmod +x "$YTDLP_BIN"
log "yt-dlp wrapper ready: $YTDLP_BIN"

# ── Verify ────────────────────────────────────────────────────────
if PYTHONPATH="$YTDLP_PYLIB" python3 -c "import yt_dlp; print(yt_dlp.version.__version__)" \
    >> "$LOG_FILE" 2>&1; then
    ver=$(PYTHONPATH="$YTDLP_PYLIB" python3 -c "import yt_dlp; print(yt_dlp.version.__version__)" 2>/dev/null)
    log "yt-dlp $ver verified OK"
else
    log "WARN: yt-dlp import verify failed — extension may not work"
fi

log "=== install completed ==="
echo "[$EXT_ID] install completed"
