param(
  [ValidateSet('pi-zero', 'pi4-2gb')]
  [string]$Preset = 'pi4-2gb',

  [Parameter(Mandatory = $true)]
  [string]$IsoPath,

  [string]$VmName = 'moode-dev-ubuntu',
  [string]$VmRoot = 'D:\HyperV\moode-dev',
  [string]$SwitchName = 'Default Switch'
)

$ErrorActionPreference = 'Stop'

function Get-PresetSpec {
  param([string]$Name)

  switch ($Name) {
    'pi-zero' {
      return [pscustomobject]@{
        CpuCount           = 2
        MemoryStartupBytes = 2GB
        VhdSizeBytes       = 16GB
      }
    }
    'pi4-2gb' {
      return [pscustomobject]@{
        CpuCount           = 2
        MemoryStartupBytes = 3GB
        VhdSizeBytes       = 24GB
      }
    }
  }
}

if (-not (Get-Command New-VM -ErrorAction SilentlyContinue)) {
  throw 'Hyper-V PowerShell module not found. Enable Hyper-V first.'
}

if (-not (Test-Path -LiteralPath $IsoPath)) {
  throw "ISO not found: $IsoPath"
}

$spec = Get-PresetSpec -Name $Preset

$vmDir = Join-Path $VmRoot $VmName
$vhdPath = Join-Path $vmDir "$VmName.vhdx"

New-Item -ItemType Directory -Path $vmDir -Force | Out-Null

$existing = Get-VM -Name $VmName -ErrorAction SilentlyContinue
if ($existing) {
  throw "VM already exists: $VmName"
}

$switch = Get-VMSwitch -Name $SwitchName -ErrorAction SilentlyContinue
if (-not $switch) {
  throw "VMSwitch not found: $SwitchName"
}

Write-Host "Creating VM $VmName using profile $Preset"
New-VM -Name $VmName -Generation 2 -MemoryStartupBytes $spec.MemoryStartupBytes -SwitchName $SwitchName -Path $vmDir | Out-Null
Set-VMProcessor -VMName $VmName -Count $spec.CpuCount
Set-VMMemory -VMName $VmName -DynamicMemoryEnabled $false

New-VHD -Path $vhdPath -SizeBytes $spec.VhdSizeBytes -Dynamic | Out-Null
Add-VMHardDiskDrive -VMName $VmName -Path $vhdPath
Add-VMDvdDrive -VMName $VmName -Path $IsoPath

Set-VMFirmware -VMName $VmName -EnableSecureBoot Off -FirstBootDevice (Get-VMDvdDrive -VMName $VmName)

Write-Host 'VM created successfully.'
Write-Host "Name: $VmName"
Write-Host "CPU: $($spec.CpuCount)"
Write-Host "Memory: $([math]::Round($spec.MemoryStartupBytes / 1GB, 2)) GB"
Write-Host "Disk: $([math]::Round($spec.VhdSizeBytes / 1GB, 2)) GB"
Write-Host "Start with: Start-VM -Name $VmName"
