param(
    [Parameter(Mandatory)][ValidateSet('Migrate','RuntimeRole','User','Tenant','Domain','State','Test')][string]$Action,
    [Parameter(ValueFromRemainingArguments)][string[]]$Arguments
)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$savedEnvironment = @{}
$names = @('PROVISIONING_DB_USERNAME','PROVISIONING_DB_PASSWORD','PROVISIONING_DB_DATABASE','DB_DATABASE','DB_USERNAME','DB_PASSWORD','RUN_INTEGRATION','PHP_INI_SCAN_DIR')
foreach ($name in $names) { $savedEnvironment[$name] = [Environment]::GetEnvironmentVariable($name, 'Process') }
$originalLocation = Get-Location
try {
    Set-Location $projectRoot
    $rootConfig = Get-Content -LiteralPath '.env'
    $adminLine = $rootConfig | Where-Object { $_ -match '^DB_PASSWORD=' } | Select-Object -First 1
    if (!$adminLine) { throw 'Run setup-local.ps1 first; root .env needs the local PostgreSQL operator password.' }
    $env:PROVISIONING_DB_USERNAME = 'infovish_local'
    $env:PROVISIONING_DB_PASSWORD = $adminLine.Substring(12)
    . ./scripts/php-local.ps1
    Set-Location backend
    switch ($Action) {
        'Migrate' { & php artisan migrate --database=provisioner --force }
        'RuntimeRole' { & php artisan platform:runtime-role infovish_platform_app --write-env }
        'User' { & php artisan platform:user @Arguments }
        'Tenant' { & php artisan platform:tenant @Arguments }
        'Domain' { & php artisan platform:tenant-domain @Arguments }
        'State' { & php artisan platform:tenant-state @Arguments }
        'Test' {
            $env:DB_DATABASE = 'infovish_test'
            $env:PROVISIONING_DB_DATABASE = 'infovish_test'
            & php artisan migrate --database=provisioner --force
            if ($LASTEXITCODE -ne 0) { throw 'Test migration failed' }
            $testBytes = [byte[]]::new(32)
            [Security.Cryptography.RandomNumberGenerator]::Fill($testBytes)
            $testPassword = [Convert]::ToHexString($testBytes)
            $testPassword | & php artisan platform:runtime-role infovish_test_app --password-stdin
            if ($LASTEXITCODE -ne 0) { throw 'Restricted test runtime setup failed' }
            $env:DB_USERNAME = 'infovish_test_app'
            $env:DB_PASSWORD = $testPassword
            $env:RUN_INTEGRATION = '1'
            & php vendor/bin/phpunit @Arguments
        }
    }
    if ($LASTEXITCODE -ne 0) { throw "Platform action $Action failed." }
} finally {
    foreach ($name in $names) { [Environment]::SetEnvironmentVariable($name, $savedEnvironment[$name], 'Process') }
    Set-Location $originalLocation
}
