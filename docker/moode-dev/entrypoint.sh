#!/usr/bin/env bash
set -euo pipefail

SRC_ROOT="${EXTMGR_SRC:-/workspace/ext-mgr}"

mkdir -p /var/www/inc
mkdir -p /var/www/templates
mkdir -p /var/www/extensions/sys/assets/js
mkdir -p /var/www/extensions/sys/assets/css
mkdir -p /var/www/extensions/sys/scripts
mkdir -p /var/www/extensions/installed/radio-browser/assets

cat > /var/www/inc/common.php <<'PHP'
<?php
function sqlConnect() { return null; }
function phpSession($mode) { if (session_status() === PHP_SESSION_NONE) { @session_start(); } return true; }
function storeBackLink($section, $tpl) { return true; }
PHP

cat > /var/www/inc/session.php <<'PHP'
<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
PHP

cat > /var/www/inc/sql.php <<'PHP'
<?php
function cfgdb_connect() { return null; }
PHP

cat > /var/www/header.php <<'PHP'
<?php
if (!isset($section)) { $section = 'index'; }
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>moode-dev</title>
  <style>
    body { margin:0; font-family: Arial, sans-serif; background:#111; color:#ddd; }
    #top { padding:10px; background:#1a1a1a; border-bottom:1px solid #333; }
    #config-tabs a.btn { color:#ddd; margin-right:8px; text-decoration:none; }
    #container { padding:12px; }
    .btn { padding:6px 10px; border:1px solid #555; border-radius:4px; display:inline-block; }
    .btn-large { padding:10px 14px; }
    .config-title { margin:0 0 8px; }
  </style>
</head>
<body>
<div id="top">
  <div id="config-tabs">
    <a id="per-config-btn" class="btn" href="per-config.php"><span>Peripherals</span><i class="fa-solid fa-sharp fa-display"></i></a>
  </div>
</div>
PHP

cat > /var/www/footer.min.php <<'PHP'
<div id="configure-modal" class="modal hide">
  <div class="modal-body"><ul>
    <li><a href="cdsp-config.php" class="btn btn-large"><i class="fa-solid fa-sharp fa-square-sliders-vertical"></i><br>CamillaDSP</a></li>
  </ul></div>
</div>
</body>
</html>
PHP

cp /var/www/footer.min.php /var/www/footer.php

cat > /var/www/templates/indextpl.min.html <<'HTML'
<div id="library-dropdown">
<button aria-label="Radio" class="btn radio-view-btn" href="#library-panel"><span>Radio</span></button> <button aria-label="Folder" class="btn folder-view-btn" href="#library-panel">Folder</button>
</div>
HTML

cat > /var/www/extensions/installed/radio-browser/radio-browser.php <<'PHP'
<?php
$section = 'radio-browser';
$extAssetsPath = '/extensions/installed/radio-browser/assets';
include('/var/www/header.php');
echo '<script src="' . $extAssetsPath . '/radio-browser.js" defer></script>' . "\n";
echo '<div id="container"><div class="container"><h1 class="config-title">Radio Browser</h1><a href="#configure-modal" class="btn">Configure</a></div></div>';
include('/var/www/footer.min.php');
PHP

cat > /var/www/extensions/installed/radio-browser/assets/radio-browser.js <<'JS'
console.log('radio-browser stub loaded');
JS

link_or_copy() {
  local src="$1"
  local dest="$2"
  local mode="$3"
  mkdir -p "$(dirname "$dest")"
  if [[ -e "$dest" || -L "$dest" ]]; then
    rm -f "$dest"
  fi
  if [[ -f "$src" ]]; then
    ln -s "$src" "$dest" || install -m "$mode" "$src" "$dest"
  fi
}

link_or_copy "$SRC_ROOT/ext-mgr.php" /var/www/extensions/sys/ext-mgr.php 0644
link_or_copy "$SRC_ROOT/ext-mgr-api.php" /var/www/extensions/sys/ext-mgr-api.php 0644
link_or_copy "$SRC_ROOT/ext-mgr.meta.json" /var/www/extensions/sys/ext-mgr.meta.json 0644
link_or_copy "$SRC_ROOT/ext-mgr.release.json" /var/www/extensions/sys/ext-mgr.release.json 0644
link_or_copy "$SRC_ROOT/ext-mgr.version" /var/www/extensions/sys/ext-mgr.version 0644
link_or_copy "$SRC_ROOT/ext-mgr.integrity.json" /var/www/extensions/sys/ext-mgr.integrity.json 0644
link_or_copy "$SRC_ROOT/assets/js/ext-mgr.js" /var/www/extensions/sys/assets/js/ext-mgr.js 0644
link_or_copy "$SRC_ROOT/assets/js/ext-mgr-hover-menu.js" /var/www/extensions/sys/assets/js/ext-mgr-hover-menu.js 0644
link_or_copy "$SRC_ROOT/assets/css/ext-mgr.css" /var/www/extensions/sys/assets/css/ext-mgr.css 0644

if [[ -f "$SRC_ROOT/registry.json" ]]; then
  install -m 0644 "$SRC_ROOT/registry.json" /var/www/extensions/sys/registry.json
else
  cat > /var/www/extensions/sys/registry.json <<'JSON'
{
  "generatedAt": "dev",
  "extensions": []
}
JSON
fi

ln -sfn /var/www/extensions/sys/ext-mgr.php /var/www/ext-mgr.php
ln -sfn /var/www/extensions/sys/ext-mgr-api.php /var/www/ext-mgr-api.php
ln -sfn /var/www/extensions/installed/radio-browser/radio-browser.php /var/www/radio-browser.php

exec "$@"
