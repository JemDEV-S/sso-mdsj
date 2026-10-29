param(
    [ValidateSet('serve', 'serve-net', 'migrate', 'admin', 'keys', 'test', 'test-pg')]
    [string]$Task = 'serve'
)
$ErrorActionPreference = 'Stop'
Push-Location $PSScriptRoot
try {
    $phpBinary = (Get-Command php -ErrorAction Stop).Source
    $phpOptions = @()
    $modules = & $phpBinary -m
    if ($modules -notcontains 'pdo_pgsql') { $phpOptions += @('-d', 'extension=pdo_pgsql') }
    $opensslConfig = Join-Path (Split-Path $phpBinary) 'extras/ssl/openssl.cnf'
    if (!$env:OPENSSL_CONF -and (Test-Path -LiteralPath $opensslConfig)) { $env:OPENSSL_CONF = $opensslConfig }
    switch ($Task) {
        'serve' { & $phpBinary @phpOptions -S 127.0.0.1:8085 -t public }
        # Expone el SSO en la red local (otras PCs). Requiere APP_URL/SSO_ISSUER
        # apuntando a la IP de red y el firewall abierto en el puerto 8085.
        'serve-net' { & $phpBinary @phpOptions -S 0.0.0.0:8085 -t public }
        'migrate' { & $phpBinary @phpOptions artisan migrate }
        'admin' { & $phpBinary @phpOptions artisan sso:create-admin }
        'keys' { & $phpBinary @phpOptions artisan sso:keys }
        'test' { & $phpBinary @phpOptions vendor/bin/phpunit }
        'test-pg' { & $phpBinary @phpOptions vendor/bin/phpunit -c phpunit.pgsql.xml }
    }
    $taskExitCode = $LASTEXITCODE
} finally {
    Pop-Location
}
exit $taskExitCode
