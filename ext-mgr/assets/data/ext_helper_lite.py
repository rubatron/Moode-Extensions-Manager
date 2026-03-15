#!/usr/bin/env python3
"""ext-mgr developer helper: validate extension before import.

Lightweight scanner for extension developers to validate their extensions
before importing via the ext-mgr Import Wizard.

Usage:
    python3 ext_helper.py scan /path/to/extension
    python3 ext_helper.py patterns
    python3 ext_helper.py policy
"""

from __future__ import annotations
import argparse
import json
import re
import sys
from pathlib import Path
from typing import Any

# Path security policy - validated during import
PATH_POLICY = [
    {"prefix": "/var/www/extensions/installed/", "severity": "ok", "label": "managed root"},
    {"prefix": "/var/www/extensions/sys/", "severity": "ok", "label": "shared sys root"},
    {"prefix": "/etc/systemd/system/", "severity": "ok", "label": "systemd units"},
    {"prefix": "/var/www/", "severity": "violation", "label": "moOde web root"},
    {"prefix": "/etc/", "severity": "warning", "label": "system config"},
    {"prefix": "/boot/", "severity": "warning", "label": "pi boot partition"},
    {"prefix": "/usr/local/bin/", "severity": "info", "label": "local binary"},
    {"prefix": "/opt/", "severity": "info", "label": "optional package"},
]

# Code patterns detected during import
CODE_PATTERNS = [
    {
        "id": "hardcoded_header_suppress",
        "label": "Hardcoded header suppression",
        "severity": "upgradeable",
        "files": ["template.php"],
        "pattern": r"#config-tabs\s*\{\s*display\s*:\s*none\s*!important\s*\}",
        "fix": "Auto-upgraded to dynamic header visibility",
    },
    {
        "id": "hardcoded_extension_path",
        "label": "Hardcoded extension path",
        "severity": "warning",
        "files": ["*.php", "*.sh"],
        "pattern": r"/var/www/extensions/installed/[a-z0-9_-]+",
        "fix": "Use $assetBase or EXTMGR_INSTALLED_ROOT variable",
    },
    {
        "id": "unsafe_shell_exec",
        "label": "Unsafe shell execution",
        "severity": "warning",
        "files": ["*.php"],
        "pattern": r"shell_exec\s*\(\s*\$[a-zA-Z_]+\s*\)",
        "fix": "Sanitize input with escapeshellarg()",
    },
    {
        "id": "direct_apt_install",
        "label": "Direct apt install",
        "severity": "info",
        "files": ["install.sh"],
        "pattern": r"apt(-get)?\s+install\s+-y",
        "fix": "Declare in manifest.json ext_mgr.install.packages",
    },
    {
        "id": "rm_rf_dangerous",
        "label": "Dangerous rm -rf",
        "severity": "warning",
        "files": ["*.sh"],
        "pattern": r"rm\s+-rf\s+\$",
        "fix": "Add variable checks before rm -rf",
    },
]


def _read_json(path: Path, fallback: Any) -> Any:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        return fallback


def _load_custom_patterns(root: Path) -> list:
    custom_file = root / "ext-mgr-patterns.json"
    if not custom_file.exists():
        return []
    try:
        data = json.loads(custom_file.read_text(encoding="utf-8"))
        return data.get("patterns", []) if isinstance(data, dict) else []
    except Exception:
        return []


def _classify_path(path: str) -> dict:
    for row in PATH_POLICY:
        if path.startswith(row["prefix"]):
            return {"severity": row["severity"], "label": row["label"]}
    return {"severity": "info", "label": "unclassified"}


def _scan_paths(text: str) -> list:
    audit = []
    seen = set()
    for match in re.finditer(r"/(?:var|etc|boot|usr|opt)/[^\s'\"]+", text):
        p = match.group(0)
        if p in seen:
            continue
        seen.add(p)
        cls = _classify_path(p)
        audit.append({"path": p, **cls})
    return audit


def _scan_code_patterns(root: Path, custom: list) -> dict:
    import fnmatch
    all_patterns = CODE_PATTERNS + custom
    findings = []
    
    for file_path in list(root.rglob("*.php")) + list(root.rglob("*.sh")) + list(root.rglob("*.js")):
        if not file_path.is_file():
            continue
        try:
            content = file_path.read_text(encoding="utf-8", errors="ignore")
        except Exception:
            continue
        
        rel_path = str(file_path.relative_to(root)).replace("\\", "/")
        
        for pdef in all_patterns:
            file_globs = pdef.get("files", ["*"])
            if not any(fnmatch.fnmatch(file_path.name, g) for g in file_globs):
                continue
            
            regex = pdef.get("pattern", "")
            if not regex:
                continue
            
            try:
                if re.search(regex, content, re.IGNORECASE | re.MULTILINE):
                    findings.append({
                        "id": pdef.get("id", "unknown"),
                        "label": pdef.get("label", "Unknown"),
                        "severity": pdef.get("severity", "info"),
                        "file": rel_path,
                        "fix": pdef.get("fix", ""),
                    })
            except re.error:
                continue
    
    return {
        "findings": findings,
        "by_severity": {
            "violation": [f for f in findings if f["severity"] == "violation"],
            "warning": [f for f in findings if f["severity"] == "warning"],
            "info": [f for f in findings if f["severity"] == "info"],
            "upgradeable": [f for f in findings if f["severity"] == "upgradeable"],
        },
    }


def cmd_scan(args) -> int:
    root = Path(args.root).resolve()
    manifest = _read_json(root / "manifest.json", {})
    ext_id = str(manifest.get("id") or root.name)
    
    install_sh = root / "scripts" / "install.sh"
    install_text = install_sh.read_text(encoding="utf-8", errors="ignore") if install_sh.exists() else ""
    
    path_audit = _scan_paths(install_text)
    custom_patterns = _load_custom_patterns(root)
    code_patterns = _scan_code_patterns(root, custom_patterns)
    
    payload = {
        "ext_id": ext_id,
        "path_audit": path_audit,
        "violations": [r for r in path_audit if r["severity"] == "violation"],
        "warnings": [r for r in path_audit if r["severity"] == "warning"],
        "code_patterns": code_patterns,
    }
    
    print(json.dumps(payload, indent=2, ensure_ascii=True))
    return 0


def cmd_patterns(_) -> int:
    print(json.dumps(CODE_PATTERNS, indent=2, ensure_ascii=True))
    return 0


def cmd_policy(_) -> int:
    print(json.dumps(PATH_POLICY, indent=2, ensure_ascii=True))
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(prog="ext_helper.py", description="ext-mgr developer helper")
    sub = parser.add_subparsers(dest="cmd", required=True)

    p_scan = sub.add_parser("scan", help="Scan extension for issues")
    p_scan.add_argument("root", help="Extension root directory")
    p_scan.set_defaults(func=cmd_scan)

    p_patterns = sub.add_parser("patterns", help="Show built-in code patterns")
    p_patterns.set_defaults(func=cmd_patterns)

    p_policy = sub.add_parser("policy", help="Show path security policy")
    p_policy.set_defaults(func=cmd_policy)

    args = parser.parse_args()
    return int(args.func(args))


if __name__ == "__main__":
    sys.exit(main())
