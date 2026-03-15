#!/usr/bin/env python3
"""
ext_helper.py — ext-mgr intelligent helper utilities
Callable from the import wizard (PHP via subprocess) or standalone CLI.

Commands:
  scan        <extension_root>               analyse extension, output JSON
  footprint   <extension_root>               show install footprint JSON
  register    <register.json> <ext_id> <pkg> register pkg ownership (exit 0=new, 2=exists)
  unregister  <register.json> <ext_id>       remove ext from all pkg ownerships
  rewrite     <root> <old_id> <new_id>       rename extension ID throughout
  save-icon   <root> <src_file>              copy .ico/.png/.svg to assets/images/
"""

import sys, os, re, json, shutil
from pathlib import Path
from dataclasses import dataclass, field
from typing import Optional


# ─────────────────────────────────────────────────────────────────
# Path policy table
#
# Every path category that can appear in an extension's install.sh
# is classified here with:
#   severity  : ok | info | warning | violation
#   strategy  : what the extension SHOULD do instead
#   packages  : where to bundle the artifact in the extension zip
# ─────────────────────────────────────────────────────────────────

PATH_POLICY = [
    # ── Always OK ─────────────────────────────────────────────────
    {
        "prefix":    "/var/www/extensions/installed/",
        "severity":  "ok",
        "label":     "managed root",
        "strategy":  "Direct writes allowed.",
        "packages":  None,
    },
    {
        "prefix":    "/var/www/extensions/sys/",
        "severity":  "ok",
        "label":     "shared sys root",
        "strategy":  "Direct writes allowed.",
        "packages":  None,
    },
    {
        "prefix":    "/etc/systemd/system",
        "severity":  "ok",
        "label":     "systemd units",
        "strategy":  "Symlinks only — never copy. Unit source stays in packages/services/.",
        "packages":  "packages/services/",
    },

    # ── Tool invocations — not writes ──────────────────────────────
    {
        "prefix":    "/usr/bin/",
        "severity":  "ok",
        "label":     "system tool",
        "strategy":  "Tool invocation — not a write target.",
        "packages":  None,
        "exact_pattern": r'^/usr/bin/[a-z0-9\-]+$',
    },
    {
        "prefix":    "/usr/sbin/",
        "severity":  "ok",
        "label":     "system tool",
        "strategy":  "Tool invocation — not a write target.",
        "packages":  None,
        "exact_pattern": r'^/usr/sbin/[a-z0-9\-]+$',
    },

    # ── /var/www/ outside managed — violation ─────────────────────
    {
        "prefix":    "/var/www/",
        "severity":  "violation",
        "label":     "moOde web root",
        "strategy":  "Must redirect to managed root. Move to packages/webroot/ and "
                     "install via symlink from managed root.",
        "packages":  "packages/webroot/",
    },

    # ── /etc/ config files ─────────────────────────────────────────
    {
        "prefix":    "/etc/udev/rules.d/",
        "severity":  "warning",
        "label":     "udev rules",
        "strategy":  "Bundle in packages/config/udev/. Install via symlink: "
                     "ln -sf $ROOT/packages/config/udev/<file> /etc/udev/rules.d/<file>. "
                     "Track symlink in footprint. Trigger: udevadm control --reload-rules.",
        "packages":  "packages/config/udev/",
    },
    {
        "prefix":    "/etc/modules-load.d/",
        "severity":  "warning",
        "label":     "kernel module config",
        "strategy":  "Bundle in packages/config/modules/. Install via symlink. "
                     "Track in footprint.",
        "packages":  "packages/config/modules/",
    },
    {
        "prefix":    "/etc/modprobe.d/",
        "severity":  "warning",
        "label":     "modprobe config",
        "strategy":  "Bundle in packages/config/modprobe/. Install via symlink.",
        "packages":  "packages/config/modprobe/",
    },
    {
        "prefix":    "/etc/lirc/",
        "severity":  "warning",
        "label":     "LIRC config",
        "strategy":  "Bundle in packages/config/lirc/. Install via symlink. "
                     "Config is user-editable so copy (not symlink) may be preferred.",
        "packages":  "packages/config/lirc/",
    },
    {
        "prefix":    "/etc/asound",
        "severity":  "warning",
        "label":     "ALSA config",
        "strategy":  "Bundle in packages/config/alsa/. Coordinate with moOde's "
                     "own ALSA management to avoid conflicts.",
        "packages":  "packages/config/alsa/",
    },
    {
        "prefix":    "/etc/",
        "severity":  "warning",
        "label":     "system config",
        "strategy":  "Bundle config in packages/config/<subdir>/. Install via symlink "
                     "where possible. Track all writes in footprint for clean uninstall.",
        "packages":  "packages/config/",
    },

    # ── /boot/ — special: append-only ─────────────────────────────
    {
        "prefix":    "/boot/config.txt",
        "severity":  "warning",
        "label":     "Pi boot config",
        "strategy":  "Never overwrite. Use append-only pattern with guard comments:\n"
                     "  # BEGIN <ext-id>\n"
                     "  dtoverlay=...\n"
                     "  # END <ext-id>\n"
                     "Track added lines in footprint. Remove by stripping BEGIN/END block.",
        "packages":  None,
    },
    {
        "prefix":    "/boot/",
        "severity":  "warning",
        "label":     "Pi boot partition",
        "strategy":  "Treat as append-only. Never overwrite existing entries. "
                     "Track all additions in footprint.",
        "packages":  None,
    },

    # ── /usr/local/ — binaries & libs ─────────────────────────────
    {
        "prefix":    "/usr/local/bin/",
        "severity":  "info",
        "label":     "local binary",
        "strategy":  "Bundle binary in packages/bin/. Install via symlink: "
                     "ln -sf $ROOT/packages/bin/<name> /usr/local/bin/<name>. "
                     "Track symlink in footprint.",
        "packages":  "packages/bin/",
    },
    {
        "prefix":    "/usr/local/lib/",
        "severity":  "info",
        "label":     "local library",
        "strategy":  "Bundle in packages/lib/. Install via symlink or copy. "
                     "Python packages: use pip install --target=$ROOT/packages/pylib/ "
                     "and add $ROOT/packages/pylib/ to PYTHONPATH in the service unit.",
        "packages":  "packages/lib/",
    },
    {
        "prefix":    "/usr/local/",
        "severity":  "info",
        "label":     "local install prefix",
        "strategy":  "Bundle artifacts in packages/. Use symlinks where possible.",
        "packages":  "packages/",
    },

    # ── /var/lib/ — runtime data ───────────────────────────────────
    {
        "prefix":    "/var/lib/",
        "severity":  "info",
        "label":     "runtime data",
        "strategy":  "Use $ROOT/data/ instead. If the software requires a fixed path, "
                     "create a symlink: ln -sf $ROOT/data/<name> /var/lib/<name>. "
                     "Track in footprint.",
        "packages":  None,
    },

    # ── /opt/ — optional packages ──────────────────────────────────
    {
        "prefix":    "/opt/",
        "severity":  "info",
        "label":     "optional package",
        "strategy":  "Bundle in packages/opt/<name>/. Install via symlink from /opt/ "
                     "or add to PATH in the service unit.",
        "packages":  "packages/opt/",
    },

    # ── /tmp/ /run/ — transient ────────────────────────────────────
    {
        "prefix":    "/tmp/",
        "severity":  "info",
        "label":     "temp dir",
        "strategy":  "Transient — acceptable. Use $ROOT/cache/ for persistent temp data.",
        "packages":  None,
    },
    {
        "prefix":    "/run/",
        "severity":  "info",
        "label":     "runtime dir",
        "strategy":  "Transient — acceptable for PID files and sockets.",
        "packages":  None,
    },

    # ── /home/ — user dirs ─────────────────────────────────────────
    {
        "prefix":    "/home/",
        "severity":  "warning",
        "label":     "user home dir",
        "strategy":  "Avoid writing to user home dirs. Use $ROOT/data/ for "
                     "persistent config, $ROOT/cache/ for runtime state.",
        "packages":  None,
    },
]

# Severity ordering for sorting results
SEVERITY_ORDER = {"violation": 0, "warning": 1, "info": 2, "ok": 3}


def classify_path(path: str, ext_id: str) -> dict:
    """
    Classify a single path against the policy table.
    Returns a policy dict with severity, label, strategy, packages.
    """
    managed = f"/var/www/extensions/installed/{ext_id}/"

    # Check managed root first (most specific /var/www/ match)
    if path.startswith(managed) or path.startswith("/var/www/extensions/sys/"):
        return {**PATH_POLICY[0], "path": path, "matched_prefix": managed}

    for policy in PATH_POLICY:
        # Skip generic /var/www/ entry if path is already under extensions/
        if policy["prefix"] == "/var/www/" and path.startswith("/var/www/extensions/"):
            continue
        if "exact_pattern" in policy:
            if re.match(policy["exact_pattern"], path):
                return {**policy, "path": path}
            continue
        if path.startswith(policy["prefix"]):
            result = {**policy, "path": path}
            return result

    return {
        "prefix": path,
        "severity": "info",
        "label": "unknown path",
        "strategy": "Review manually.",
        "packages": None,
        "path": path,
    }


# ─────────────────────────────────────────────────────────────────
# Scanner
# ─────────────────────────────────────────────────────────────────

class ExtensionScanner:

    _TYPE_SIGS = {
        "hardware": [
            r'\budev\b', r'\bgpio\b', r'\blirc\b', r'\bdtoverlay\b',
            r'\bwiringpi\b', r'\bi2c\b', r'\bspi\b', r'luma\.oled',
            r'/dev/tty', r'/dev/input', r'\bmodprobe\b', r'\braspi-config\b',
        ],
        "streaming_service": [
            r'\blibrespot\b', r'\bshairport\b', r'\braspotify\b',
            r'\bspotifyd\b', r'\btidal\b', r'\bairplay\b',
            r'\bsqueezelite\b', r'\bplexamp\b',
        ],
    }

    _APT_NOISE = {
        'y','yes','q','get','install','remove','purge','update','upgrade',
        'recommends','only','noninteractive','auto','allow','unauthenticated',
        'fix','broken','no','apt','failed','for','pkg',
    }

    def scan(self, root: str) -> dict:
        root     = Path(root)
        manifest = self._json(root / "manifest.json")
        info     = self._json(root / "info.json")
        ext_mgr  = manifest.get("ext_mgr", {})
        ext_id   = manifest.get("id", "")

        path_audit = self._scan_all_paths(root, ext_id)

        return {
            "ext_id":       ext_id,
            "name":         info.get("name", manifest.get("name", "")),
            "version":      info.get("version", manifest.get("version", "0.1.0")),
            "author":       info.get("author", ""),
            "description":  info.get("description", ""),
            "repository":   info.get("repository", ""),
            "icon_class":   info.get("iconClass",
                              ext_mgr.get("iconClass", "fa-solid fa-puzzle-piece")),
            "custom_icon":  self._find_custom_icon(root),
            "type":         self._detect_type(root),
            "has_template": (root / "template.php").exists(),
            "has_backend":  (root / "backend" / "api.php").exists(),
            "has_service":  self._glob_exists(root / "scripts", "*.service"),
            "has_helper_units": self._glob_exists(root / "packages" / "services", "*.service"),
            "apt_packages": (
                ext_mgr.get("install", {}).get("packages")
                or self._parse_apt_packages(root)
            ),
            "pip_packages": self._parse_pip_packages(root),
            "path_audit":   path_audit,
            "violations":   [p for p in path_audit if p["severity"] == "violation"],
            "warnings":     [p for p in path_audit if p["severity"] == "warning"],
            "service_units": self._list_units(root),
        }

    # ── private helpers ──────────────────────────────────────────

    def _json(self, p: Path) -> dict:
        try: return json.loads(p.read_text()) if p.exists() else {}
        except Exception: return {}

    def _glob_exists(self, d: Path, pattern: str) -> bool:
        return d.exists() and bool(list(d.glob(pattern)))

    def _find_custom_icon(self, root: Path) -> str:
        for name in ["icon.ico", "icon.png", "icon.svg"]:
            if (root / "assets" / "images" / name).exists():
                return f"assets/images/{name}"
        return ""

    def _strip_for_analysis(self, text: str) -> str:
        """Strip code blocks that contain false-positive path references."""
        # Strip heredoc blocks (contain Python/bash inline code with paths as strings)
        text = re.sub(r'<<\s*[\'"]?\w+[\'"]?.*?^\w+$', '', text,
                      flags=re.DOTALL | re.MULTILINE)
        # Strip case...esac (path patterns used for matching, not writing)
        text = re.sub(r'\bcase\b.*?\besac\b', '', text, flags=re.DOTALL)
        # Strip echo/log lines that are NOT file redirections (>> or >)
        # Keep: echo 'x' >> /boot/config.txt  (write target — important!)
        # Strip: echo "installed at $ROOT"    (descriptive string)
        text = re.sub(r'^\s*(?:echo|log(?:ger)?)\s+(?!.*>>?\s*/).*$', '', text, flags=re.MULTILINE)
        # Strip comment lines
        text = re.sub(r'^\s*#.*$', '', text, flags=re.MULTILINE)
        return text

    def _read_install_sh(self, root: Path) -> str:
        p = root / "scripts" / "install.sh"
        return self._strip_for_analysis(p.read_text()) if p.exists() else ""

    def _detect_type(self, root: Path) -> str:
        scripts = root / "scripts"
        has_sh  = scripts.exists() and bool(list(scripts.glob("*.sh")))
        has_tpl = (root / "template.php").exists()
        if not has_sh and not has_tpl and (root / "assets" / "css").exists():
            return "theme"
        content = self._read_install_sh(root)
        for etype, patterns in self._TYPE_SIGS.items():
            if any(re.search(p, content, re.I) for p in patterns):
                return etype
        return "functionality" if has_tpl else "other"

    def _parse_apt_packages(self, root: Path) -> list:
        content = self._read_install_sh(root)
        if not content: return []
        found = []
        noise = self._APT_NOISE | {'apt', 'failed', 'for', 'pkg'}
        for line in content.splitlines():
            stripped = line.strip()
            if not re.match(r'(?:DEBIAN_FRONTEND=\S+\s+)?apt(?:-get)?\s+install\b', stripped):
                continue
            m = re.search(r'apt(?:-get)?\s+install\s+(.+)', stripped, re.I)
            if not m: continue
            for token in m.group(1).split():
                token = token.strip("'\"${}\\")
                if (re.match(r'^[a-z0-9][a-z0-9\-\.+]{1,}$', token)
                        and not token.startswith('-')
                        and token not in noise):
                    found.append(token)
        return list(dict.fromkeys(found))

    def _parse_pip_packages(self, root: Path) -> list:
        """Detect pip install calls in install.sh."""
        content = self._read_install_sh(root)
        if not content: return []
        found = []
        for line in content.splitlines():
            stripped = line.strip()
            if not re.match(r'pip3?\s+install\b', stripped):
                continue
            m = re.search(r'pip3?\s+install\s+(.+)', stripped, re.I)
            if not m: continue
            for token in m.group(1).split():
                token = token.strip("'\"${}\\")
                if (re.match(r'^[a-zA-Z0-9][a-zA-Z0-9\-\._]{1,}$', token)
                        and not token.startswith('-')):
                    found.append(token)
        return list(dict.fromkeys(found))

    def _scan_all_paths(self, root: Path, ext_id: str) -> list:
        """
        Scan install.sh for ALL external path references.
        Classify each against the policy table.
        Returns sorted list: violations first, then warnings, then info, then ok.
        """
        content = self._read_install_sh(root)
        if not content: return []

        found_paths = set()

        # Match any absolute path starting with common system prefixes
        for m in re.finditer(
            r'(?<!["\w$])(/'
            r'(?:var/www|var/lib|etc|usr|lib|opt|home|tmp|run|boot|srv|mnt)'
            r'/[^\s\'"\\);`\]\|&{]+)',
            content
        ):
            p = m.group(1).rstrip("'\"\\;),|&")
            # Skip bare variable expressions like $ROOT, ${EXT_ID}
            if '$' in p: continue
            # Skip very short paths (likely false positives)
            if len(p) < 5: continue
            found_paths.add(p)

        # Classify each found path
        results = []
        seen_prefixes = set()
        for path in sorted(found_paths):
            classified = classify_path(path, ext_id)
            # Deduplicate by (prefix, severity) to avoid noise
            key = (classified["prefix"], classified["severity"])
            if key in seen_prefixes and classified["severity"] == "ok":
                continue
            seen_prefixes.add(key)
            results.append(classified)

        # Sort: violations → warnings → info → ok
        results.sort(key=lambda x: SEVERITY_ORDER.get(x["severity"], 9))
        return results

    def _list_units(self, root: Path) -> list:
        units = []
        for d in [root / "scripts", root / "packages" / "services"]:
            if d.exists():
                units += [str(p.relative_to(root)) for p in sorted(d.glob("*.service"))]
        return units


# ─────────────────────────────────────────────────────────────────
# Package register
# ─────────────────────────────────────────────────────────────────

class PackageRegister:
    """Manages /var/www/extensions/sys/pkg-register.json."""

    def __init__(self, path: str):
        self.path = Path(path)

    def _load(self) -> dict:
        try: return json.loads(self.path.read_text()) if self.path.exists() else {"packages":{}}
        except Exception: return {"packages":{}}

    def _save(self, data: dict):
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self.path.write_text(json.dumps(data, indent=2))

    def register(self, ext_id: str, package: str) -> dict:
        data = self._load()
        pkgs = data.setdefault("packages", {})
        if package in pkgs:
            if ext_id not in pkgs[package]:
                pkgs[package].append(ext_id)
                self._save(data)
            return {"status": "already_present", "owners": pkgs[package]}
        pkgs[package] = [ext_id]
        self._save(data)
        return {"status": "registered", "owners": [ext_id]}

    def unregister(self, ext_id: str) -> list:
        data    = self._load()
        pkgs    = data.get("packages", {})
        orphans = []
        for pkg, owners in list(pkgs.items()):
            if ext_id in owners: owners.remove(ext_id)
            if not owners:
                del pkgs[pkg]
                orphans.append(pkg)
        self._save(data)
        return orphans


# ─────────────────────────────────────────────────────────────────
# Template rewriter
# ─────────────────────────────────────────────────────────────────

class TemplateRewriter:
    _TEXT_SUFFIXES = {
        '.sh','.php','.js','.json','.service',
        '.md','.py','.txt','.html','.css','.conf','.ini',
    }

    def rewrite(self, root: str, old_id: str, new_id: str) -> list:
        root    = Path(root)
        changed = []
        for path in sorted(root.rglob("*")):
            if not path.is_file() or path.suffix not in self._TEXT_SUFFIXES:
                continue
            try: text = path.read_text(encoding="utf-8")
            except (UnicodeDecodeError, PermissionError): continue
            if old_id in text:
                path.write_text(text.replace(old_id, new_id), encoding="utf-8")
                changed.append(str(path.relative_to(root)))
        old_svc = root / "scripts" / f"{old_id}.service"
        new_svc = root / "scripts" / f"{new_id}.service"
        if old_svc.exists() and old_svc != new_svc:
            old_svc.rename(new_svc)
            changed.append(f"scripts/{old_id}.service → {new_id}.service (renamed)")
        return changed


# ─────────────────────────────────────────────────────────────────
# Custom icon handler
# ─────────────────────────────────────────────────────────────────

class IconHandler:
    ALLOWED_EXTS   = {'.ico', '.png', '.svg'}
    MAX_SIZE_BYTES = 512 * 1024

    def save(self, root: str, src_file: str) -> dict:
        root = Path(root)
        src  = Path(src_file)
        if src.suffix.lower() not in self.ALLOWED_EXTS:
            return {"ok": False, "error": f"Unsupported: {src.suffix}. Use .ico .png .svg"}
        if src.stat().st_size > self.MAX_SIZE_BYTES:
            return {"ok": False, "error": "File too large (max 512 KB)"}
        dest_dir = root / "assets" / "images"
        dest_dir.mkdir(parents=True, exist_ok=True)
        dest = dest_dir / f"icon{src.suffix.lower()}"
        shutil.copy2(src, dest)
        return {"ok": True, "path": f"assets/images/icon{src.suffix.lower()}"}


# ─────────────────────────────────────────────────────────────────
# CLI
# ─────────────────────────────────────────────────────────────────

def cmd_scan(args):
    print(json.dumps(ExtensionScanner().scan(args[0] if args else "."), indent=2))

def cmd_footprint(args):
    root = Path(args[0] if args else ".")
    fp   = root / "data" / "install-footprint.json"
    print(fp.read_text() if fp.exists() else
          json.dumps({"error": "no footprint found", "path": str(fp)}))

def cmd_register(args):
    if len(args) < 3: sys.exit("usage: register <register.json> <ext_id> <package>")
    r = PackageRegister(args[0]).register(args[1], args[2])
    print(json.dumps(r))
    sys.exit(0 if r["status"] == "registered" else 2)

def cmd_unregister(args):
    if len(args) < 2: sys.exit("usage: unregister <register.json> <ext_id>")
    print(json.dumps({"orphaned_packages": PackageRegister(args[0]).unregister(args[1])}))

def cmd_rewrite(args):
    if len(args) < 3: sys.exit("usage: rewrite <root> <old_id> <new_id>")
    print(json.dumps({"changed_files": TemplateRewriter().rewrite(*args[:3])}))

def cmd_save_icon(args):
    if len(args) < 2: sys.exit("usage: save-icon <root> <src_file>")
    print(json.dumps(IconHandler().save(args[0], args[1])))

def cmd_policy(args):
    """Print the full path policy table as JSON."""
    print(json.dumps(PATH_POLICY, indent=2))

COMMANDS = {
    "scan":       cmd_scan,
    "footprint":  cmd_footprint,
    "register":   cmd_register,
    "unregister": cmd_unregister,
    "rewrite":    cmd_rewrite,
    "save-icon":  cmd_save_icon,
    "policy":     cmd_policy,
}

if __name__ == "__main__":
    if len(sys.argv) < 2 or sys.argv[1] not in COMMANDS:
        print(__doc__); sys.exit(1)
    COMMANDS[sys.argv[1]](sys.argv[2:])
