param(
  [string]$BaseUrl = 'http://localhost',
  [switch]$RequireServer,
  [int]$TimeoutSec = 8
)

$root = Split-Path -Parent $PSScriptRoot
$apiSmoke = Join-Path $root 'tests/api-smoke.ps1'

if (-not (Test-Path -LiteralPath $apiSmoke)) {
  throw "Smoke test script not found at $apiSmoke"
}

& $apiSmoke -BaseUrl $BaseUrl -RequireServer:$RequireServer -TimeoutSec $TimeoutSec
if ($LASTEXITCODE -ne 0) {
  exit $LASTEXITCODE
}
