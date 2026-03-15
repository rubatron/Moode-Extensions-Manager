#!/usr/bin/env bash
# Kept for ext-mgr compatibility — service runs via moode-ydl.service directly
EXT_ID='moode-ydl'
while true; do
  echo "[$(date +'%Y-%m-%d %H:%M:%S')] [$EXT_ID] heartbeat"
  sleep 60
done
