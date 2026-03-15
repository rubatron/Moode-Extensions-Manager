#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════
#  build-zip.sh — ext-mgr extension package builder
#
#  Builds a clean, import-ready .zip from the extension root.
#  Strips dev-only files, runs path audit before packing.
#
#  Usage:
#    ./scripts/build-zip.sh                # <ext-id>-<version>.zip alongside root
#    ./scripts/build-zip.sh -o /tmp        # custom output dir
#    ./scripts/build-zip.sh --dry-run      # list what would be included
# ═══════════════════════════════════════════════════════════════════
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# ── Parse args ────────────────────────────────────────────────────
OUTPUT_DIR="$(cd "$ROOT/.." && pwd)"
DRY_RUN=false

while [[ $# -gt 0 ]]; do
  case "$1" in
    --output|-o) OUTPUT_DIR="$2"; shift 2 ;;
    --dry-run)   DRY_RUN=true; shift ;;
    -h|--help)   echo "Usage: build-zip.sh [-o dir] [--dry-run]"; exit 0 ;;
    *)           echo "Unknown arg: $1"; exit 1 ;;
  esac
done

# ── Delegate everything to Python ─────────────────────────────────
exec python3 - "$ROOT" "$OUTPUT_DIR" "$DRY_RUN" << 'PYEOF'
import sys, os, json, zipfile, shutil
from pathlib import Path

root       = Path(sys.argv[1]).resolve()
output_dir = Path(sys.argv[2]).resolve()
dry_run    = sys.argv[3] == "True"

# ── Read manifest ──────────────────────────────────────────────────
manifest_path = root / "manifest.json"
try:
    m = json.loads(manifest_path.read_text())
    ext_id      = m.get("id", root.name)
    ext_version = m.get("version", "0.0.0")
except Exception:
    ext_id      = root.name
    ext_version = "0.0.0"

zip_name = f"{ext_id}-{ext_version}.zip"
zip_path = output_dir / zip_name

# ── Dev-only exclusions ────────────────────────────────────────────
EXCLUDE_NAMES    = {".vscode", "__pycache__", ".git", ".DS_Store", "Thumbs.db"}
EXCLUDE_SUFFIXES = {".pyc", ".pyo", ".code-workspace"}
EXCLUDE_REL      = {
    "logs", "cache",
    "data/install-footprint.json",
    "scripts/build-zip.sh",
    ".gitkeep", ".gitignore",
}

def is_excluded(p: Path) -> bool:
    rel = p.relative_to(root)
    for part in rel.parts:
        if part in EXCLUDE_NAMES: return True
    if p.suffix in EXCLUDE_SUFFIXES: return True
    if str(rel) in EXCLUDE_REL or rel.name in EXCLUDE_REL: return True
    return False

included = sorted(
    [p for p in root.rglob("*") if p.is_file() and not is_excluded(p)],
    key=lambda p: str(p.relative_to(root))
)

# ── Pre-flight scan ────────────────────────────────────────────────
helper = root / "backend" / "ext_helper.py"
if helper.exists():
    import subprocess, importlib.util
    spec = importlib.util.spec_from_file_location("ext_helper", helper)
    mod  = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)

    print("── Scanning extension...")
    scan = mod.ExtensionScanner().scan(str(root))

    violations = scan.get("violations", [])
    warnings   = scan.get("warnings", [])

    if violations:
        print(f"\n✗  {len(violations)} path violation(s) — fix before building:")
        for v in violations:
            print(f"   {v['path']}")
            print(f"   → {v.get('strategy','')[:72]}")
        print("\nBuild aborted.")
        sys.exit(1)

    print(f"   Path audit: clean  ({len(warnings)} warning(s))")
    for w in warnings:
        print(f"   ⚠  {w['path']} → bundle in: {w.get('packages','-')}")
else:
    print("── ext_helper.py not found — skipping scan")

# ── Dry run ────────────────────────────────────────────────────────
if dry_run:
    print(f"\n── Dry run — {len(included)} files in {zip_name}:")
    for p in included:
        print(f"   {p.relative_to(root)}")
    print(f"\n   Output: {zip_path}")
    sys.exit(0)

# ── Build zip ──────────────────────────────────────────────────────
output_dir.mkdir(parents=True, exist_ok=True)
print(f"── Building {zip_name}...")

with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
    for p in included:
        arcname = p.relative_to(root.parent)   # include folder name in zip
        zf.write(p, arcname)

size_kb = zip_path.stat().st_size // 1024
print(f"\n✓  {zip_path}")
print(f"   {len(included)} files · {size_kb}KB · ready for ext-mgr import")
PYEOF
