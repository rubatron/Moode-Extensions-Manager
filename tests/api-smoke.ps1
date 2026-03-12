param(
  [string]$BaseUrl = 'http://localhost',
  [switch]$RequireServer,
  [int]$TimeoutSec = 8
)

function Assert-True([bool]$Condition, [string]$Message) {
  if (-not $Condition) {
    throw $Message
  }
}

function Test-ApiReachable([string]$Url, [int]$Timeout) {
  try {
    $null = Invoke-WebRequest -Method Get -Uri $Url -TimeoutSec $Timeout -ErrorAction Stop
    return $true
  }
  catch {
    return $false
  }
}

$apiUrl = "$BaseUrl/ext-mgr-api.php"

if (-not (Test-ApiReachable -Url $apiUrl -Timeout $TimeoutSec)) {
  $message = "Cannot reach ext-mgr API at $BaseUrl. Start a local server or pass a reachable -BaseUrl."
  if ($RequireServer) {
    throw $message
  }

  Write-Host "SKIPPED: $message"
  exit 0
}

$list = $null
try {
  $list = Invoke-RestMethod -Method Post -Uri $apiUrl -Body @{ action = 'list' } -TimeoutSec $TimeoutSec -ErrorAction Stop
}
catch {
  throw "List action failed against $apiUrl. Original error: $($_.Exception.Message)"
}

Assert-True $list.ok 'List action should return ok=true'
Assert-True ($list.data -ne $null) 'List action should return data'

$refresh = Invoke-RestMethod -Method Post -Uri $apiUrl -Body @{ action = 'refresh' } -TimeoutSec $TimeoutSec -ErrorAction Stop
Assert-True $refresh.ok 'Refresh action should return ok=true'

$checkUpdate = Invoke-RestMethod -Method Post -Uri $apiUrl -Body @{ action = 'check_update' } -TimeoutSec $TimeoutSec -ErrorAction Stop
Assert-True $checkUpdate.ok 'Check_update action should return ok=true'
Assert-True ($checkUpdate.data -ne $null) 'Check_update should return data payload'
Assert-True ($checkUpdate.data.providerStatus -ne $null) 'Check_update should return providerStatus'
Assert-True ($checkUpdate.data.providerStatus.signatureVerification -ne $null) 'Check_update should include signatureVerification mode'
Assert-True ($checkUpdate.data.providerStatus.checksumAlgorithm -eq 'sha256') 'Check_update should include checksumAlgorithm=sha256'

Write-Host 'API smoke tests passed.'
