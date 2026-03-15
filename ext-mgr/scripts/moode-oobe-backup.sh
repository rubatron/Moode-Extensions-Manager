#!/usr/bin/env bash
set -euo pipefail

# Create a restore-friendly snapshot of core moOde web files.
# Usage:
#   sudo bash scripts/moode-oobe-backup.sh [output_dir]

OUT_DIR="${1:-/home/pi/moode-oobe-backups}"
STAMP="$(date +%Y%m%d-%H%M%S)"
ARCHIVE="${OUT_DIR}/moode-oobe-web-${STAMP}.tgz"

FILES=(
  /var/www/header.php
  /var/www/footer.min.php
  /var/www/footer.php
  /var/www/templates/indextpl.min.html
  /var/www/index.php
)

mkdir -p "${OUT_DIR}"

existing=()
for f in "${FILES[@]}"; do
  if [[ -f "${f}" ]]; then
    existing+=("${f}")
  fi
done
review d
if [[ ${#existing[@]} -eq 0 ]]; then
  echo "No target files found. Nothing to back up." >&2
  exit 1
fi

tar --xattrs --acls -czf "${ARCHIVE}" "${existing[@]}"

echo "Backup created: ${ARCHIVE}"
