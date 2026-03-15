#!/usr/bin/env python3
"""ext-mgr helper: scan/policy/rewrite/register utilities.

This is a minimal, production-safe baseline aligned with the sandbox roadmap.
Includes code pattern detection and auto-upgrade capabilities.
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
from datetime import datetime
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

# Code patterns to detect and optionally auto-fix during import
CODE_PATTERNS = [
    {
        "id": "hardcoded_header_suppress",
        "label": "Hardcoded header suppression",
        "description": "Old-style hardcoded #config-tabs hiding",
        "severity": "upgradeable",
        "files": ["template.php"],
        "pattern": r"#config-tabs\s*\{\s*display\s*:\s*none\s*!important\s*\}",
        "autofix": True,
        "fix_description": "Upgrade to dynamic header visibility from registry",
    },
    {
        "id": "hardcoded_extension_path",
        "label": "Hardcoded extension path",
        "description": "Direct path to /var/www/extensions instead of using variables",
        "severity": "warning",
        "files": ["*.php", "*.sh"],
        "pattern": r"/var/www/extensions/installed/[a-z0-9_-]+",
        "autofix": False,
        "fix_description": "Use $assetBase or EXTMGR_INSTALLED_ROOT variable",
    },
    {
        "id": "deprecated_moode_includes",
        "label": "Deprecated moOde includes",
        "description": "Using old moOde include paths",
        "severity": "warning",
        "files": ["*.php"],
        "pattern": r"require(_once)?\s*\(?['\"]\/var\/www\/(playerlib|inc\/playerlib)",
        "autofix": False,
        "fix_description": "Use /var/www/inc/common.php instead",
    },
    {
        "id": "unsafe_shell_exec",
        "label": "Unsafe shell execution",
        "description": "Direct shell_exec without input sanitization",
        "severity": "warning",
        "files": ["*.php"],
        "pattern": r"shell_exec\s*\(\s*\$[a-zA-Z_]+\s*\)",
        "autofix": False,
        "fix_description": "Sanitize input with escapeshellarg() or escapeshellcmd()",
    },
    {
        "id": "missing_csrf_check",
        "label": "Form without CSRF protection",
        "description": "POST handling without session/CSRF validation",
        "severity": "info",
        "files": ["*.php"],
        "pattern": r"\$_POST\s*\[",
        "autofix": False,
        "fix_description": "Consider adding session validation for form submissions",
    },
    {
        "id": "absolute_symlink",
        "label": "Absolute symlink creation",
        "description": "Creating symlinks with absolute paths outside managed root",
        "severity": "warning",
        "files": ["*.sh"],
        "pattern": r"ln\s+-s[f]?\s+(/var/www/|/etc/|/usr/)",
        "autofix": False,
        "fix_description": "Use packages/ directory and let ext-mgr handle symlink creation",
    },
    {
        "id": "direct_apt_install",
        "label": "Direct apt install in script",
        "description": "Running apt install directly instead of declaring dependencies",
        "severity": "info",
        "files": ["install.sh"],
        "pattern": r"apt(-get)?\s+install\s+-y",
        "autofix": False,
        "fix_description": "Declare packages in manifest.json ext_mgr.install.packages",
    },
    {
        "id": "rm_rf_dangerous",
        "label": "Dangerous rm -rf command",
        "description": "rm -rf with variable that could be empty",
        "severity": "warning",
        "files": ["*.sh"],
        "pattern": r"rm\s+-rf\s+(\"\$|\$\{?[A-Z_]+\}?/)",
        "autofix": False,
        "fix_description": "Add checks: [[ -n \"$VAR\" ]] && [[ -d \"$VAR\" ]] before rm -rf",
    },
    {
        "id": "uses_moode_header",
        "label": "Uses moOde header.php",
        "description": "Extension integrates with moOde header shell",
        "severity": "ok",
        "files": ["*.php"],
        "pattern": r"include\s+['\"]?/var/www/header\.php['\"]?",
        "autofix": False,
        "fix_description": "moOde header integration detected - good",
    },
    {
        "id": "uses_moode_footer",
        "label": "Uses moOde footer",
        "description": "Extension integrates with moOde footer",
        "severity": "ok",
        "files": ["*.php"],
        "pattern": r"include\s+['\"]?/var/www/footer(\.min)?\.php['\"]?",
        "autofix": False,
        "fix_description": "moOde footer integration detected - good",
    },
    {
        "id": "hardcoded_navbar_suppress",
        "label": "Hardcoded navbar suppression",
        "description": "CSS hiding navbar-settings or moode-settings-nav",
        "severity": "upgradeable",
        "files": ["*.php"],
        "pattern": r"(#navbar-settings|\.navbar-settings|\.moode-settings-nav|#header\s+\.nav)[^}]*display\s*:\s*none",
        "autofix": True,
        "fix_description": "Upgrade to dynamic header visibility via ext-mgr registry",
    },
    {
        "id": "has_dynamic_header_control",
        "label": "Has dynamic header control",
        "description": "Already uses ext-mgr registry for header visibility",
        "severity": "ok",
        "files": ["*.php"],
        "pattern": r"\$extMgrHideHeader|\bheaderVisible\b|registry\.json",
        "autofix": False,
        "fix_description": "Extension already uses dynamic header control - good",
    },
    {
        "id": "uses_moode_common",
        "label": "Uses moOde common.php",
        "description": "Extension uses moOde common includes",
        "severity": "ok",
        "files": ["*.php"],
        "pattern": r"require(_once)?\s*['\"]?/var/www/inc/common\.php['\"]?",
        "autofix": False,
        "fix_description": "moOde common include detected - good",
    },
    {
        "id": "uses_moode_session",
        "label": "Uses moOde session.php",
        "description": "Extension integrates with moOde session handling",
        "severity": "ok",
        "files": ["*.php"],
        "pattern": r"require(_once)?\s*['\"]?/var/www/inc/session\.php['\"]?",
        "autofix": False,
        "fix_description": "moOde session integration detected - good",
    },
]


def _read_json(path: Path, fallback: Any) -> Any:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        return fallback


def _load_custom_patterns(root: Path) -> list[dict]:
    """Load custom code patterns from ext-mgr-patterns.json if present."""
    custom_file = root / "ext-mgr-patterns.json"
    if not custom_file.exists():
        return []
    try:
        data = json.loads(custom_file.read_text(encoding="utf-8"))
        return data.get("patterns", []) if isinstance(data, dict) else []
    except Exception:
        return []


def _file_matches_glob(filename: str, patterns: list[str]) -> bool:
    """Check if filename matches any of the glob patterns."""
    import fnmatch
    for pattern in patterns:
        if fnmatch.fnmatch(filename, pattern):
            return True
    return False


def _scan_code_patterns(root: Path, custom_patterns: list[dict] | None = None) -> dict:
    """Scan extension files for known code patterns."""
    all_patterns = CODE_PATTERNS + (custom_patterns or [])
    findings: list[dict] = []
    upgradeable: list[dict] = []

    # Collect all files to scan
    files_to_scan: list[Path] = []
    for pattern in ["*.php", "*.sh", "*.py", "*.js"]:
        files_to_scan.extend(root.rglob(pattern))

    for file_path in files_to_scan:
        if not file_path.is_file():
            continue

        try:
            content = file_path.read_text(encoding="utf-8", errors="ignore")
        except Exception:
            continue

        rel_path = str(file_path.relative_to(root)).replace("\\", "/")
        filename = file_path.name

        for pattern_def in all_patterns:
            # Check if this file type should be scanned for this pattern
            file_patterns = pattern_def.get("files", ["*"])
            if not _file_matches_glob(filename, file_patterns):
                continue

            regex = pattern_def.get("pattern", "")
            if not regex:
                continue

            try:
                matches = list(re.finditer(regex, content, re.IGNORECASE | re.MULTILINE))
            except re.error:
                continue

            if not matches:
                continue

            finding = {
                "id": pattern_def.get("id", "unknown"),
                "label": pattern_def.get("label", "Unknown pattern"),
                "description": pattern_def.get("description", ""),
                "severity": pattern_def.get("severity", "info"),
                "file": rel_path,
                "line_numbers": [],
                "autofix": pattern_def.get("autofix", False),
                "fix_description": pattern_def.get("fix_description", ""),
            }

            # Find line numbers for each match
            for match in matches:
                line_num = content[:match.start()].count("\n") + 1
                if line_num not in finding["line_numbers"]:
                    finding["line_numbers"].append(line_num)

            findings.append(finding)

            if pattern_def.get("severity") == "upgradeable":
                upgradeable.append(finding)

    return {
        "patterns_checked": len(all_patterns),
        "findings": findings,
        "upgradeable": upgradeable,
        "by_severity": {
            "violation": [f for f in findings if f["severity"] == "violation"],
            "warning": [f for f in findings if f["severity"] == "warning"],
            "info": [f for f in findings if f["severity"] == "info"],
            "upgradeable": upgradeable,
        },
    }


class ModificationLog:
    """Log modifications made during import wizard processing."""

    def __init__(self, root: Path):
        self.root = root
        self.entries: list[dict] = []
        self.log_file = root / "ext-mgr-modifications.log"

    def log(self, action: str, file: str, description: str, before: str = "", after: str = ""):
        entry = {
            "timestamp": datetime.now().isoformat(),
            "action": action,
            "file": file,
            "description": description,
        }
        if before:
            entry["before"] = before[:500] + ("..." if len(before) > 500 else "")
        if after:
            entry["after"] = after[:500] + ("..." if len(after) > 500 else "")
        self.entries.append(entry)

    def save(self):
        """Save modification log to file."""
        if not self.entries:
            return

        lines = ["# ext-mgr Modification Log", f"# Generated: {datetime.now().isoformat()}", ""]
        for entry in self.entries:
            lines.append(f"## [{entry['timestamp']}] {entry['action']}")
            lines.append(f"File: {entry['file']}")
            lines.append(f"Description: {entry['description']}")
            if entry.get("before"):
                lines.append(f"Before: {entry['before']}")
            if entry.get("after"):
                lines.append(f"After: {entry['after']}")
            lines.append("")

        try:
            self.log_file.write_text("\n".join(lines), encoding="utf-8")
        except Exception:
            pass

    def to_json(self) -> list[dict]:
        return self.entries


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

    # Scan for code patterns
    custom_patterns = _load_custom_patterns(root)
    code_patterns = _scan_code_patterns(root, custom_patterns)

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
        "code_patterns": code_patterns,
    }
    print(json.dumps(payload, ensure_ascii=True))
    return 0


def cmd_patterns(_: argparse.Namespace) -> int:
    """Output the built-in code patterns as JSON."""
    print(json.dumps(CODE_PATTERNS, ensure_ascii=True, indent=2))
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

    p_scan = sub.add_parser("scan", help="Scan extension for paths and code patterns")
    p_scan.add_argument("root")
    p_scan.set_defaults(func=cmd_scan)

    p_patterns = sub.add_parser("patterns", help="Output built-in code patterns as JSON")
    p_patterns.set_defaults(func=cmd_patterns)

    p_policy = sub.add_parser("policy", help="Output path policy as JSON")
    p_policy.set_defaults(func=cmd_policy)

    p_rewrite = sub.add_parser("rewrite", help="Rewrite extension ID in files")
    p_rewrite.add_argument("root")
    p_rewrite.add_argument("old_id")
    p_rewrite.add_argument("new_id")
    p_rewrite.set_defaults(func=cmd_rewrite)

    p_reg = sub.add_parser("register", help="Register package ownership")
    p_reg.add_argument("registry")
    p_reg.add_argument("ext_id")
    p_reg.add_argument("package")
    p_reg.set_defaults(func=cmd_register)

    p_unreg = sub.add_parser("unregister", help="Unregister extension from packages")
    p_unreg.add_argument("registry")
    p_unreg.add_argument("ext_id")
    p_unreg.set_defaults(func=cmd_unregister)

    args = parser.parse_args()
    return int(args.func(args))


if __name__ == "__main__":
    sys.exit(main())
