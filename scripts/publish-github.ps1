param(
  [string]$RemoteUrl = '',
  [string]$Branch = 'main',
  [switch]$SkipPush
)

function Resolve-GitExecutable {
  $gitCmd = Get-Command git -ErrorAction SilentlyContinue
  if ($gitCmd -and $gitCmd.Source) {
    return $gitCmd.Source
  }

  $fallback = 'C:\Program Files\Git\cmd\git.exe'
  if (Test-Path -LiteralPath $fallback) {
    return $fallback
  }

  throw 'Git CLI is not installed or not reachable. Install Git and rerun this script.'
}

$gitExe = Resolve-GitExecutable

function Invoke-Git {
  param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$Args
  )

  & $gitExe @Args
  if ($LASTEXITCODE -ne 0) {
    throw ('Git command failed: ' + ($Args -join ' '))
  }
}

function Get-GitConfigValue {
  param(
    [string]$Key
  )

  $value = (& $gitExe config --get $Key | Out-String).Trim()
  if ($LASTEXITCODE -ne 0) {
    return ''
  }
  return $value
}

$repoUserName = Get-GitConfigValue -Key 'user.name'
if ([string]::IsNullOrWhiteSpace($repoUserName)) {
  Invoke-Git config user.name 'ext-mgr local'
}

$repoUserEmail = Get-GitConfigValue -Key 'user.email'
if ([string]::IsNullOrWhiteSpace($repoUserEmail)) {
  Invoke-Git config user.email 'ext-mgr@local.invalid'
}

if (-not (Test-Path -LiteralPath '.git')) {
  Invoke-Git init | Out-Null
}

# Ensure branch name is consistent before first push.
Invoke-Git checkout -B $Branch | Out-Null

Invoke-Git add .

$hasChanges = $false
try {
  $statusLines = & $gitExe status --porcelain
  $hasChanges = ($statusLines -and $statusLines.Count -gt 0)
}
catch {
  $hasChanges = $false
}

$hasCommit = $false
try {
  & $gitExe rev-parse --verify HEAD *> $null
  if ($LASTEXITCODE -ne 0) {
    throw 'No commit yet'
  }
  $hasCommit = $true
}
catch {
  $hasCommit = $false
}

if ($hasChanges) {
  if (-not $hasCommit) {
    Invoke-Git commit -m 'chore: bootstrap ext-mgr workspace' | Out-Null
  } else {
    Invoke-Git commit -m 'chore: update ext-mgr workspace' | Out-Null
  }
}

$remoteProvided = -not [string]::IsNullOrWhiteSpace($RemoteUrl)

$existingRemote = ''
try {
  $existingRemote = ((& $gitExe remote get-url origin) | Out-String).Trim()
  if ($LASTEXITCODE -ne 0) {
    throw 'origin missing'
  }
}
catch {
  $existingRemote = ''
}

if ($remoteProvided) {
  if ($existingRemote -eq '') {
    Invoke-Git remote add origin $RemoteUrl
  } elseif ($existingRemote -ne $RemoteUrl) {
    Invoke-Git remote set-url origin $RemoteUrl
  }
}

if (-not $SkipPush -and $remoteProvided) {
  Invoke-Git push -u origin $Branch
} elseif ($SkipPush -or -not $remoteProvided) {
  Write-Host "Local git setup complete on branch '$Branch'."
  if (-not $remoteProvided) {
    Write-Host 'No -RemoteUrl provided, so remote configuration and push were skipped.'
  }
}
