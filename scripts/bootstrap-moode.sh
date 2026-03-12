#!/usr/bin/env bash
set -euo pipefail

# Bootstrap ext-mgr on moOde without git clone.
# Downloads repository archive via wget/curl and runs install.sh from extracted tree.

REPO_OWNER="rubatron"
REPO_NAME="Moode-Extensions-Manager"
REF="${EXT_MGR_REF:-main}"
TMP_ROOT="/tmp/ext-mgr-bootstrap-$$"
ARCHIVE_PATH="$TMP_ROOT/ext-mgr.tar.gz"

cleanup() {
  rm -rf "$TMP_ROOT"
}
trap cleanup EXIT

download_archive() {
  local url="$1"
  if command -v wget >/dev/null 2>&1; then
    wget -qO "$ARCHIVE_PATH" "$url"
    return 0
  fi

  if command -v curl >/dev/null 2>&1; then
    curl -fsSL "$url" -o "$ARCHIVE_PATH"
    return 0
  fi

  echo "ERROR: Neither wget nor curl is available." >&2
  return 1
}

main() {
  mkdir -p "$TMP_ROOT"

  local archive_url
  archive_url="https://codeload.github.com/${REPO_OWNER}/${REPO_NAME}/tar.gz/refs/heads/${REF}"

  echo "[1/4] Downloading ${REPO_NAME} (${REF}) archive..."
  download_archive "$archive_url"

  echo "[2/4] Extracting archive..."
  tar -xzf "$ARCHIVE_PATH" -C "$TMP_ROOT"

  local extracted_dir
  extracted_dir="$(find "$TMP_ROOT" -mindepth 1 -maxdepth 1 -type d | head -n 1)"
  if [[ -z "$extracted_dir" || ! -f "$extracted_dir/install.sh" ]]; then
    echo "ERROR: Could not locate install.sh in extracted archive." >&2
    exit 1
  fi

  echo "[3/4] Running installer from $extracted_dir"
  if [[ "${EUID}" -eq 0 ]]; then
    "$extracted_dir/install.sh" "$@"
  else
    sudo "$extracted_dir/install.sh" "$@"
  fi

  echo "[4/4] Done. Open: http://<pi-ip>/ext-mgr.php"
}

main "$@"
