#!/usr/bin/env bash
set -euo pipefail

EXT_ID='${EXT_ID:-template-extension}'
while true; do
  echo "[$(date +'%Y-%m-%d %H:%M:%S')] [$EXT_ID] service heartbeat"
  sleep 60
done
