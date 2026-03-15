#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════
#  uninstall.sh — moode-ydl YouTube Audioplayback
#  Runs as: moode-extmgrusr (unprivileged)
#
#  ext-mgr already handles (as root):
#    - apt package removal
#    - systemd service disable + unit removal
#    - install-metadata.json cleanup
#
#  This script only cleans up what install.sh created in the sandbox.
# ═══════════════════════════════════════════════════════════════════
set -euo pipefail

EXT_ID="${EXT_MGR_EXTENSION_ID:-moode-ydl}"
ROOT="${EXT_MGR_EXTENSION_ROOT:-/var/www/extensions/installed/$EXT_ID}"
LOG_FILE="$ROOT/logs/install.log"

log() { echo "[$(date +'%Y-%m-%d %H:%M:%S')] [$EXT_ID] $*" | tee -a "$LOG_FILE" 2>/dev/null || true; }
log "=== uninstall started ==="

# Remove pip-installed yt-dlp from sandbox pylib
if [[ -d "$ROOT/packages/pylib" ]]; then
    rm -rf "$ROOT/packages/pylib"
    log "removed: packages/pylib (yt-dlp)"
fi

# Remove wrapper script
if [[ -f "$ROOT/packages/bin/yt-dlp" ]]; then
    rm -f "$ROOT/packages/bin/yt-dlp"
    log "removed: packages/bin/yt-dlp"
fi

# Remove generated runtime dirs (keep MPD playlist files — user data)
rm -rf "$ROOT/cache" "$ROOT/data" "$ROOT/logs"

# Note: /var/lib/mpd/playlists/youtube.m3u and ytpllist.txt are NOT removed
# They are MPD runtime files — user may want to keep their saved playlists.
log "=== uninstall completed ==="
echo "[$EXT_ID] uninstall completed"
