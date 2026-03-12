# Local VM Setup (Hyper-V)

This guide provides a lightweight local VM flow for moOde-related build and packaging work, while keeping day-to-day ext-mgr UI/API iteration in Docker.

## Why Hybrid

- Docker: fast UI/API and installer iteration for ext-mgr.
- Hyper-V VM: reliable environment for Linux system-level packaging tasks.

## Recommended Profiles

- pi-zero profile:
  - vCPU: 2
  - RAM: 2 GB
  - Disk: 16 GB
- pi4-2gb profile:
  - vCPU: 2
  - RAM: 3 GB
  - Disk: 24 GB

Note: The pi4-2gb profile uses 3 GB host memory to keep apt/build tooling responsive.

## 1) Enable Hyper-V

Run elevated PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/hyperv-enable.ps1
```

Optional no-restart mode:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/hyperv-enable.ps1 -NoRestart
```

## 2) Create Lightweight Ubuntu VM

- Download Ubuntu Server ISO (22.04 or 24.04 LTS) manually.
- Create VM:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/hyperv-create-moode-dev-vm.ps1 -Preset pi4-2gb -IsoPath "D:\ISO\ubuntu-24.04-live-server-amd64.iso"
```

For lower footprint:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/hyperv-create-moode-dev-vm.ps1 -Preset pi-zero -IsoPath "D:\ISO\ubuntu-24.04-live-server-amd64.iso"

Use `-Preset` to switch between `pi-zero` and `pi4-2gb`.
```

## 3) Install Base Toolchain Inside VM

```bash
sudo apt-get update
sudo apt-get install -y git curl wget rsync build-essential jq
```

## 4) Use This Repo From VM

Option A (recommended): clone directly in VM.

```bash
git clone https://github.com/rubatron/Moode-Extensions-Manager.git
cd Moode-Extensions-Manager
```

Option B: mount/share host folder (Hyper-V enhanced session/SMB).

## 5) Workflow Split

- During feature work:
  - Run local Docker moode-dev service on host for fast browser testing.
- During release/package validation:
  - Run package/build scripts in the Linux VM.

## Quick Decision Rule

- If task only touches ext-mgr PHP/JS/CSS/menu patching: use Docker.
- If task touches image/package build pipeline: use VM.
