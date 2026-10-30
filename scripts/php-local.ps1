# Workspace-only extensions; leave system PHP configuration unchanged.
$projectRoot = Split-Path $PSScriptRoot -Parent
$iniDirectory = Join-Path $projectRoot '.runtime/php'
New-Item -ItemType Directory -Force -Path $iniDirectory | Out-Null
Set-Content -LiteralPath (Join-Path $iniDirectory 'extensions.ini') -Value "extension=fileinfo`nextension=zip`nextension=pdo_pgsql"
$env:PHP_INI_SCAN_DIR = $iniDirectory
Write-Output 'Project-local PHP extension configuration enabled for this shell.'
