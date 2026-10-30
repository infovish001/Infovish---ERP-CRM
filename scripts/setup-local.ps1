$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
Set-Location $projectRoot
if (!(Test-Path -LiteralPath '.env')) {
    $secretBytes = [byte[]]::new(32)
    [Security.Cryptography.RandomNumberGenerator]::Fill($secretBytes)
    $localPassword = [Convert]::ToHexString($secretBytes)
    Set-Content -LiteralPath '.env' -Value "DB_PASSWORD=$localPassword"
}
$passwordLine = Get-Content -LiteralPath '.env' | Where-Object { $_ -match '^DB_PASSWORD=' } | Select-Object -First 1
if (!$passwordLine) { throw 'Root .env needs a DB_PASSWORD entry.' }
if (!(Test-Path -LiteralPath 'backend/.env')) {
    Copy-Item -LiteralPath 'backend/.env.example' -Destination 'backend/.env'
}
$configuration = Get-Content -LiteralPath 'backend/.env' -Raw
$configuration = [regex]::Replace($configuration, '(?m)^DB_PASSWORD=.*$', $passwordLine)
if ($configuration -match '(?m)^APP_KEY=\s*$') {
    $keyBytes = [byte[]]::new(32)
    [Security.Cryptography.RandomNumberGenerator]::Fill($keyBytes)
    $configuration = [regex]::Replace($configuration, '(?m)^APP_KEY=\s*$', "APP_KEY=base64:$([Convert]::ToBase64String($keyBytes))")
}
Set-Content -LiteralPath 'backend/.env' -Value $configuration
Write-Output 'Local environment files prepared. Credentials were not printed.'
