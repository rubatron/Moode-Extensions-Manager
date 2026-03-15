#!/usr/bin/env bash
set -euo pipefail

# Restore a moOde web snapshot produced by moode-oobe-backup.sh.
# Usage:
#   sudo bash scripts/moode-oobe-restore.sh [archive_path]
# If archive_path is omitted, restores the newest backup in /home/pi/moode-oobe-backups.

DEFAULT_DIR="/home/pi/moode-oobe-backups"
ARCHIVE="${1:-}"

if [[ -z "${ARCHIVE}" ]]; then
  ARCHIVE="$(ls -1t "${DEFAULT_DIR}"/moode-oobe-web-*.tgz 2>/dev/null | head -n 1 || true)"
fi

if [[ -z "${ARCHIVE}" || ! -f "${ARCHIVE}" ]]; then
  echo "Backup archive not found. Provide a path or place backups in ${DEFAULT_DIR}." >&2
  exit 1
fi

tar -xzf "${ARCHIVE}" -C /

if command -v systemctl >/dev/null 2>&1; then
  sudo systemctl reload nginx 2>/dev/null || true
  sudo systemctl reload apache2 2>/dev/null || true
  sudo systemctl reload php8.3-fpm 2>/dev/null || true
  sudo systemctl reload php8.2-fpm 2>/dev/null || true
  sudo systemctl reload php8.1-fpm 2>/dev/null || true
  sudo systemctl reload php-fpm 2>/dev/null || true
fi

echo "Restore completed from: ${ARCHIVE}"
