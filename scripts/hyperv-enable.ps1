param(
  [switch]$NoRestart
)

$ErrorActionPreference = 'Stop'

function Assert-Admin {
  $id = [Security.Principal.WindowsIdentity]::GetCurrent()
  $principal = New-Object Security.Principal.WindowsPrincipal($id)
  if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Run this script in an elevated PowerShell session (Run as Administrator).'
  }
}

Assert-Admin

$features = @(
  'Microsoft-Hyper-V-All',
  'VirtualMachinePlatform'
)

$needsRestart = $false

foreach ($feature in $features) {
  $state = (Get-WindowsOptionalFeature -Online -FeatureName $feature).State
  if ($state -eq 'Enabled') {
    Write-Host "Already enabled: $feature"
    continue
  }

  Write-Host "Enabling: $feature"
  Enable-WindowsOptionalFeature -Online -FeatureName $feature -All -NoRestart | Out-Null
  $needsRestart = $true
}

if ($needsRestart) {
  Write-Host 'Hyper-V features updated.'
  if ($NoRestart) {
    Write-Host 'Restart required. Re-run your terminal after reboot.'
  }
  else {
    Write-Host 'Restarting now...'
    Restart-Computer -Force
  }
}
else {
  Write-Host 'Nothing to change. Hyper-V is already enabled.'
}
