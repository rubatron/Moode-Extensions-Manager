#!/usr/bin/env python3
"""ext-mgr helper: scan/policy/rewrite/register utilities.

This is a minimal, production-safe baseline aligned with the sandbox roadmap.
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
from pathlib import Path
from typing import Any

PATH_POLICY = [
    {"prefix": "/var/www/extensions/installed/", "severity": "ok", "label": "managed root", "strategy": "direct write", "target": ""},
    {"prefix": "/var/www/extensions/sys/", "severity": "ok", "label": "shared sys root", "strategy": "direct write", "target": ""},
    {"prefix": "/etc/systemd/system/", "severity": "ok", "label": "systemd units", "strategy": "symlink", "target": "packages/services/"},
    {"prefix": "/var/www/", "severity": "violation", "label": "moOde web root", "strategy": "relocate + symlink", "target": "packages/webroot/"},
    {"prefix": "/etc/udev/rules.d/", "severity": "warning", "label": "udev rules", "strategy": "relocate + symlink", "target": "packages/config/udev/"},
    {"prefix": "/etc/modules-load.d/", "severity": "warning", "label": "kernel modules", "strategy": "relocate + symlink", "target": "packages/config/modules/"},
    {"prefix": "/etc/modprobe.d/", "severity": "warning", "label": "modprobe config", "strategy": "relocate + symlink", "target": "packages/config/modprobe/"},
    {"prefix": "/etc/lirc/", "severity": "warning", "label": "lirc config", "strategy": "relocate + symlink", "target": "packages/config/lirc/"},
    {"prefix": "/etc/asound", "severity": "warning", "label": "alsa config", "strategy": "relocate + symlink", "target": "packages/config/alsa/"},
    {"prefix": "/etc/", "severity": "warning", "label": "system config", "strategy": "review + relocate", "target": "packages/config/"},
    {"prefix": "/boot/config.txt", "severity": "warning", "label": "pi boot config", "strategy": "append-only guarded block", "target": ""},
    {"prefix": "/boot/", "severity": "warning", "label": "pi boot partition", "strategy": "append-only guarded block", "target": ""},
    {"prefix": "/usr/local/bin/", "severity": "info", "label": "local binary", "strategy": "relocate + symlink", "target": "packages/bin/"},
    {"prefix": "/usr/local/lib/", "severity": "info", "label": "local library", "strategy": "relocate + symlink", "target": "packages/lib/"},
    {"prefix": "/var/lib/", "severity": "info", "label": "runtime data", "strategy": "prefer sandbox data", "target": "data/"},
    {"prefix": "/opt/", "severity": "info", "label": "optional package", "strategy": "relocate + symlink", "target": "packages/opt/"},
]


def _read_json(path: Path, fallback: Any) -> Any:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        return fallback


def _detect_type(root: Path) -> str:
    install_sh = root / "scripts" / "install.sh"
    if install_sh.exists():
        text = install_sh.read_text(encoding="utf-8", errors="ignore")
        if re.search(r"udev|gpio|lirc|dtoverlay|i2c", text, re.IGNORECASE):
            return "hardware"
        if re.search(r"librespot|shairport|raspotify|spotifyd|tidal", text, re.IGNORECASE):
            return "streaming_service"
    if (root / "template.php").exists() and (root / "backend").is_dir():
        return "functionality"
    if (root / "template.php").exists():
        return "page"
    if (root / "assets" / "css").is_dir() and not install_sh.exists():
        return "theme"
    return "other"


def _extract_packages(install_text: str) -> tuple[list[str], list[str]]:
    apt = set()
    pip = set()
    for line in install_text.splitlines():
        if re.search(r"apt(-get)?\s+install", line):
            for token in re.findall(r"[A-Za-z0-9.+_-]+", line):
                if token.startswith("-"):
                    continue
                if token in {"apt", "apt-get", "install", "sudo", "&&", "|", "true"}:
                    continue
                if "/" in token:
                    continue
                apt.add(token)
        if re.search(r"\bpip(3)?\s+install", line):
            for token in re.findall(r"[A-Za-z0-9.+_-]+", line):
                if token.startswith("-"):
                    continue
                if token in {"pip", "pip3", "install", "sudo", "&&", "|", "true"}:
                    continue
                pip.add(token)
    return sorted(apt), sorted(pip)


def _service_units(root: Path) -> list[str]:
    units: list[str] = []
    for p in (root / "scripts").glob("*.service") if (root / "scripts").is_dir() else []:
        units.append(str(p.relative_to(root)).replace("\\", "/"))
    for p in (root / "packages" / "services").glob("*.service") if (root / "packages" / "services").is_dir() else []:
        units.append(str(p.relative_to(root)).replace("\\", "/"))
    return sorted(set(units))


def _classify_path(path: str) -> dict[str, str]:
    for row in PATH_POLICY:
        if path.startswith(row["prefix"]):
            return {
                "severity": row["severity"],
                "label": row["label"],
                "strategy": row["strategy"],
                "target": row["target"],
            }
    return {
        "severity": "info",
        "label": "unclassified",
        "strategy": "manual review",
        "target": "",
    }


def _scan_paths(install_text: str) -> list[dict[str, str]]:
    audit: list[dict[str, str]] = []
    for match in re.finditer(r"/(?:var|etc|boot|usr|opt|home|tmp|run)/[^\s'\"]+", install_text):
        p = match.group(0)
        cls = _classify_path(p)
        audit.append({"path": p, **cls})
    unique = []
    seen = set()
    for row in audit:
        key = (row["path"], row["severity"], row["label"])
        if key in seen:
            continue
        seen.add(key)
        unique.append(row)
    return unique


def cmd_scan(args: argparse.Namespace) -> int:
    root = Path(args.root).resolve()
    manifest = _read_json(root / "manifest.json", {})
    ext_id = str(manifest.get("id") or root.name)
    install_sh = root / "scripts" / "install.sh"
    install_text = install_sh.read_text(encoding="utf-8", errors="ignore") if install_sh.exists() else ""

    apt, pip = _extract_packages(install_text)
    path_audit = _scan_paths(install_text)
    violations = [r for r in path_audit if r["severity"] == "violation"]
    warnings = [r for r in path_audit if r["severity"] == "warning"]

    payload = {
        "ext_id": ext_id,
        "detected_type": _detect_type(root),
        "path_audit": path_audit,
        "violations": violations,
        "warnings": warnings,
        "apt_packages": apt,
        "pip_packages": pip,
        "service_units": _service_units(root),
        "package_artifacts": [],
        "icon_candidates": [
            str(p.relative_to(root)).replace("\\", "/")
            for p in (root / "assets" / "images").glob("icon.*")
        ] if (root / "assets" / "images").is_dir() else [],
        "rewrite_candidates": [
            p for p in ["manifest.json", "info.json", "template.php", "scripts/install.sh"]
            if (root / p).exists()
        ],
    }
    print(json.dumps(payload, ensure_ascii=True))
    return 0


def cmd_policy(_: argparse.Namespace) -> int:
    print(json.dumps(PATH_POLICY, ensure_ascii=True, indent=2))
    return 0


def cmd_rewrite(args: argparse.Namespace) -> int:
    root = Path(args.root).resolve()
    old = args.old_id
    new = args.new_id
    targets = [
        root / "manifest.json",
        root / "info.json",
        root / "template.php",
    ]
    if (root / "scripts").is_dir():
        targets.extend((root / "scripts").glob("*.sh"))
        targets.extend((root / "scripts").glob("*.service"))
    for file_path in targets:
        if not file_path.exists() or not file_path.is_file():
            continue
        try:
            text = file_path.read_text(encoding="utf-8", errors="ignore")
            file_path.write_text(text.replace(old, new), encoding="utf-8")
        except Exception:
            pass
    print(json.dumps({"ok": True, "old": old, "new": new}, ensure_ascii=True))
    return 0


def cmd_register(args: argparse.Namespace) -> int:
    path = Path(args.registry)
    data = _read_json(path, {})
    if not isinstance(data, dict):
        data = {}
    owners = data.setdefault("owners", {})
    ext = args.ext_id
    pkg = args.package
    items = owners.setdefault(pkg, [])
    if ext in items:
        print(json.dumps({"ok": True, "exists": True}, ensure_ascii=True))
        return 2
    items.append(ext)
    path.write_text(json.dumps(data, indent=2, ensure_ascii=True), encoding="utf-8")
    print(json.dumps({"ok": True, "exists": False}, ensure_ascii=True))
    return 0


def cmd_unregister(args: argparse.Namespace) -> int:
    path = Path(args.registry)
    data = _read_json(path, {})
    if not isinstance(data, dict):
        data = {}
    owners = data.get("owners", {}) if isinstance(data.get("owners"), dict) else {}
    ext = args.ext_id
    orphaned = []
    for pkg in list(owners.keys()):
        refs = owners.get(pkg)
        if not isinstance(refs, list):
            continue
        owners[pkg] = [r for r in refs if r != ext]
        if not owners[pkg]:
            orphaned.append(pkg)
            del owners[pkg]
    data["owners"] = owners
    path.write_text(json.dumps(data, indent=2, ensure_ascii=True), encoding="utf-8")
    print(json.dumps({"ok": True, "orphaned": orphaned}, ensure_ascii=True))
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(prog="ext_helper.py")
    sub = parser.add_subparsers(dest="cmd", required=True)

    p_scan = sub.add_parser("scan")
    p_scan.add_argument("root")
    p_scan.set_defaults(func=cmd_scan)

    p_policy = sub.add_parser("policy")
    p_policy.set_defaults(func=cmd_policy)

    p_rewrite = sub.add_parser("rewrite")
    p_rewrite.add_argument("root")
    p_rewrite.add_argument("old_id")
    p_rewrite.add_argument("new_id")
    p_rewrite.set_defaults(func=cmd_rewrite)

    p_reg = sub.add_parser("register")
    p_reg.add_argument("registry")
    p_reg.add_argument("ext_id")
    p_reg.add_argument("package")
    p_reg.set_defaults(func=cmd_register)

    p_unreg = sub.add_parser("unregister")
    p_unreg.add_argument("registry")
    p_unreg.add_argument("ext_id")
    p_unreg.set_defaults(func=cmd_unregister)

    args = parser.parse_args()
    return int(args.func(args))


if __name__ == "__main__":
    sys.exit(main())
