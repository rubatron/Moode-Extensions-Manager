#!/usr/bin/env bash
set -euo pipefail

REGISTRY_PATH="/var/www/extensions/sys/registry.json"
INSTALLED_ROOT="/var/www/extensions/installed"
PRUNE_MISSING=0

for arg in "$@"; do
  if [[ "$arg" == "--prune-missing" ]]; then
    PRUNE_MISSING=1
  fi
done

mkdir -p "$(dirname "$REGISTRY_PATH")"
if [[ ! -f "$REGISTRY_PATH" ]]; then
  cat > "$REGISTRY_PATH" <<'JSON'
{
  "generated_at": "",
  "extensions": []
}
JSON
fi

php -r '
$path=$argv[1];
$installedRoot=$argv[2];
$prune=($argv[3]==="1");

$data=[];
if (file_exists($path)) {
  $decoded=json_decode(file_get_contents($path), true);
  if (is_array($decoded)) { $data=$decoded; }
}
if (!isset($data["extensions"]) || !is_array($data["extensions"])) {
  $data["extensions"]=[];
}

$next=[];
$total=0; $installed=0; $missing=0; $pruned=0;
foreach ($data["extensions"] as $ext) {
  if (!is_array($ext)) { continue; }
  $total++;
  $id=(string)($ext["id"] ?? "");
  if ($id==="") { continue; }

  $dir=$installedRoot . "/" . $id;
  $link="/var/www/" . $id . ".php";
  $present=is_dir($dir) && (is_link($link) || file_exists($link));

  $ext["installed"]=$present;

  if (!$present) {
    $ext["enabled"]=false;
    $ext["state"]="missing";
    $missing++;
    if ($prune) {
      $pruned++;
      continue;
    }
  } else {
    $ext["enabled"]=isset($ext["enabled"]) ? (bool)$ext["enabled"] : true;
    $ext["state"]=$ext["enabled"] ? "active" : "inactive";
    $installed++;
  }

  if (!isset($ext["menuVisibility"]) || !is_array($ext["menuVisibility"])) {
    $ext["menuVisibility"]=[];
  }
  if (!array_key_exists("m", $ext["menuVisibility"])) {
    $ext["menuVisibility"]["m"]=isset($ext["showInMMenu"]) ? (bool)$ext["showInMMenu"] : true;
  }
  if (!array_key_exists("library", $ext["menuVisibility"])) {
    $ext["menuVisibility"]["library"]=isset($ext["showInLibrary"]) ? (bool)$ext["showInLibrary"] : true;
  }
  $ext["showInMMenu"]=(bool)$ext["menuVisibility"]["m"];
  $ext["showInLibrary"]=(bool)$ext["menuVisibility"]["library"];

  $next[]=$ext;
}

$data["extensions"]=$next;
$data["generated_at"]=date("c");
file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) . PHP_EOL);

echo "ok total={$total} installed={$installed} missing={$missing} pruned={$pruned}\n";
' "$REGISTRY_PATH" "$INSTALLED_ROOT" "$PRUNE_MISSING"

chown moode-extmgrusr:moode-extmgr "$REGISTRY_PATH" 2>/dev/null || true
chmod 0664 "$REGISTRY_PATH" 2>/dev/null || true
