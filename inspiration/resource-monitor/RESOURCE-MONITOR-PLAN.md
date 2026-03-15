# moOde Enterprise Resource Monitor

> **Status:** Concept/Inspiration - voor latere uitwerking
> **Versie:** 1.0 (15 maart 2026)
> **Doel:** Volledige indexering + real-time monitoring van alle packages, services en resources op een Moode-systeem.

## Architectuur

Gescheiden in:

- **Debian core** = RaspiOS Lite + stage2 pakketten (nginx, php-fpm, samba, etc.)
- **Moode core** = stage3 pakketten (mpd, camilladsp, shairport-sync, upmpdcli, moode-player, etc.)
- **Moode WWW/CSS** = alles onder /var/www/moode/ van pakket moode-player

### Stack

- Metrics via `systemd-cgtop` + `dpkg -S` + `df/free/top`
- Output: JSON (cache + live) → PHP → JS dashboard
- Beveiliging: sudoers beperking + escapeshellcmd + timeouts
- **Geen Grafana** - lightweight en resource friendly

---

## Scripts

### A. /usr/local/bin/moode-resource-index.sh

```bash
#!/bin/bash
set -euo pipefail
LOG="/var/log/moode/resource-index.log"
exec > >(tee -a "$LOG") 2>&1

MOODE_PKGS="moode-player alsa-cdsp alsacap ashuffle boss2-oled-p3 camilladsp camillagui libnpupnp13 librespot libupnpp16 log2ram mpc mpd mpd2cdspvolume nqptp peppy-alsa peppy-meter peppy-spectrum pleezer python3-camilladsp-plot python3-camilladsp python3-libupnpp python3-mpd shairport-sync-metadata-reader shairport-sync squeezelite trx udisks-glue udisks upmpdcli-qobuz upmpdcli-tidal upmpdcli"

OUTPUT="/tmp/moode-cache/index.json"
dpkg-query -W -f='${Package}|${Version}\n' | sort > /tmp/allpkgs.tmp

jq -n \
  --argjson debian "$(grep -vE "$(echo $MOODE_PKGS | tr ' ' '|')" /tmp/allpkgs.tmp | jq -R -s 'split("\n")[:-1]')" \
  --argjson moode "$(grep -E "$(echo $MOODE_PKGS | tr ' ' '|')" /tmp/allpkgs.tmp | jq -R -s 'split("\n")[:-1]')" \
  --argjson www "$(dpkg -L moode-player 2>/dev/null | grep -E '/www/|/css/|/js/|/inc/' | jq -R -s 'split("\n")[:-1]')" \
  --argjson services "$(systemctl list-unit-files --type=service --state=enabled | tail -n +2 | awk '{print $1"|"$2}' | jq -R -s 'split("\n")[:-1]')" \
  '{timestamp: "'$(date -u +%Y-%m-%dT%H:%M:%SZ)'", version: "10.1.3", debian_core: $debian, moode_core: $moode, moode_www_css: $www, services: $services}' > "$OUTPUT"

echo "[$(date)] Index complete → $OUTPUT" >> "$LOG"
```

### B. /usr/local/bin/moode-resource-metrics.py

```python
#!/usr/bin/env python3
import subprocess, json, os
from datetime import datetime

def run(cmd):
    try: return subprocess.check_output(cmd, shell=True, text=True, timeout=8).strip()
    except: return "0"

data = {"timestamp": datetime.utcnow().isoformat() + "Z", "system": {}, "groups": {}, "services": []}

# System
data["system"] = {
    "cpu_pct": run("top -bn1 | grep '^%Cpu' | awk '{print $2}'"),
    "mem_used_mb": run("free -m | awk 'NR==2{print $3}'"),
    "disk_root_pct": run("df -h / | awk 'NR==2{print $5}'")
}

# Groups + services (cgroup accurate!)
for svc in run("systemctl list-units --type=service --state=running | awk 'NR>1{print $1}'").splitlines()[:60]:
    cpu = run(f"systemd-cgtop -b -n1 --raw | grep -w '{svc}' | awk '{{print $3}}'") or "0"
    mem = run(f"systemd-cgtop -b -n1 --raw | grep -w '{svc}' | awk '{{print $4}}'") or "0"
    pkg = run(f"systemctl show -p MainPID {svc} | cut -d= -f2 | xargs -r ps -o pid= | xargs -r dpkg -S 2>/dev/null | cut -d: -f1") or "unknown"

    group = "debian_core"
    if any(x in pkg for x in ["moode","alsa-cdsp","camilladsp","mpd","shairport","squeezelite","upmpdcli","peppy"]):
        group = "moode_core"
    elif pkg in ["nginx","php-fpm","php8.4-fpm"]:
        group = "moode_web"

    data["groups"].setdefault(group, {"cpu":0,"mem_mb":0,"procs":0})
    data["groups"][group]["cpu"] += float(cpu)
    data["groups"][group]["mem_mb"] += float(mem)
    data["groups"][group]["procs"] += 1

    data["services"].append({"service":svc, "package":pkg, "group":group, "cpu":cpu, "mem_mb":mem})

# Prometheus textfile (optioneel)
with open("/var/lib/prometheus/node-exporter/moode_resources.prom", "w") as f:
    for g, v in data["groups"].items():
        f.write(f'moode_group_cpu{{group="{g}"}} {v["cpu"]}\n')
        f.write(f'moode_group_mem_mb{{group="{g}"}} {v["mem_mb"]}\n')

print(json.dumps(data, indent=2))
```

### C. PHP Backend (/var/www/moode/inc/resource-monitor.php)

```php
<?php
header('Content-Type: application/json');
$cache = '/tmp/moode-cache/';
$action = $_GET['action'] ?? '';

if ($action === 'index') {
    $file = $cache.'index.json';
    if (file_exists($file) && time() - filemtime($file) < 3600) {
        echo file_get_contents($file);
    } else {
        passthru('sudo /usr/local/bin/moode-resource-index.sh 2>&1');
    }
} elseif ($action === 'metrics') {
    passthru('sudo /usr/local/bin/moode-resource-metrics.sh 2>&1');
} else {
    echo json_encode(["error"=>"unknown action"]);
}
?>
```

### D. JS Frontend

```javascript
async function moodeResource(action) {
    const res = await fetch('/inc/resource-monitor.php?action=' + action);
    const data = await res.json();
    console.log('Moode Enterprise Monitor:', data);
    return data;
}

// Voorbeeld calls:
moodeResource('index');   // volledige index
moodeResource('metrics'); // live CPU/RAM
```

---

## Installatie

```bash
# 1. Maak directories + sudoers
sudo mkdir -p /usr/local/bin /var/www/moode/inc /var/www/moode/js /var/log/moode /tmp/moode-cache /var/lib/prometheus/node-exporter
sudo chown -R www-data:www-data /tmp/moode-cache /var/log/moode /var/www/moode
sudo chmod 755 /usr/local/bin/moode-resource-*

# 2. Sudoers (alleen onze scripts)
cat | sudo tee /etc/sudoers.d/99-moode-resource <<EOF
www-data ALL=(ALL) NOPASSWD: /usr/local/bin/moode-resource-*
EOF
sudo chmod 0440 /etc/sudoers.d/99-moode-resource

# 3. Logrotate
cat | sudo tee /etc/logrotate.d/moode-resource <<EOF
/var/log/moode/resource-*.log {
    daily
    rotate 14
    compress
    missingok
    notifempty
}
EOF
```

---

## Systemd Timer (elke 60s metrics)

```bash
sudo cat > /etc/systemd/system/moode-resource.timer <<EOF
[Unit]
Description=Moode Resource Metrics every 60s

[Timer]
OnBootSec=30
OnUnitActiveSec=60s

[Install]
WantedBy=timers.target
EOF
sudo systemctl enable --now moode-resource.timer
```

---

## TODO voor uitwerking

- [ ] Dashboard UI integreren in ext-mgr
- [ ] Chart.js visualisaties
- [ ] Alerts bij hoge resource usage
- [ ] History tracking (SQLite of flat JSON)
- [ ] Integration met ext-mgr extension lifecycle
