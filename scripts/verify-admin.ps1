param([switch]$SameOrigin, [switch]$Public)
$ErrorActionPreference = 'Stop'
$taskRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$taskBackend = Join-Path $taskRoot 'backend'
$taskFrontend = Join-Path $taskRoot 'frontend'
$taskPgBin = 'C:\Program Files\PostgreSQL\18\bin'
$taskEnv = Get-Content -LiteralPath (Join-Path $taskBackend '.env')
function Read-TaskEnv([string]$key) { ($taskEnv | Where-Object { $_.StartsWith($key + '=') } | Select-Object -First 1).Substring($key.Length + 1) }
$env:PGPASSWORD = Read-TaskEnv 'DB_PASSWORD'
$taskHost = Read-TaskEnv 'DB_HOST'
$taskPort = Read-TaskEnv 'DB_PORT'
$taskUser = Read-TaskEnv 'DB_USERNAME'
& (Join-Path $taskPgBin 'createdb.exe') -w -h $taskHost -p $taskPort -U $taskUser mason_browser
$env:PGPASSWORD = $null
$env:DB_DATABASE = 'mason_browser'
$env:TEST_ADMIN_EMAIL = 'browser-admin@example.test'
$env:TEST_ADMIN_PASSWORD = 'Browser!' + [guid]::NewGuid().ToString('N') + 'Aa42'
$env:LODGE_ADMIN_EMAIL = $env:TEST_ADMIN_EMAIL
$env:LODGE_ADMIN_PASSWORD = $env:TEST_ADMIN_PASSWORD
$env:TEST_BASE_URL = 'http://127.0.0.1:5174'
$env:FRONTEND_URL = $env:TEST_BASE_URL
$env:FRONTEND_ALLOWED_ORIGINS = $env:TEST_BASE_URL
$env:SANCTUM_STATEFUL_DOMAINS = '127.0.0.1:5174'
$env:VITE_API_BASE_URL = 'http://127.0.0.1:8001'
if ($SameOrigin) {
    $env:VITE_API_BASE_URL = '/'
    $env:DEV_API_PROXY_TARGET = 'http://127.0.0.1:8001'
    $env:TEST_SAME_ORIGIN = 'true'
}
$env:MAIL_MAILER = 'array'
$taskServer = $null
$taskVite = $null
try {
    Push-Location $taskBackend
    php artisan migrate:fresh --seed --force
    if ($LASTEXITCODE -ne 0) { throw 'Test migrations failed' }
    php artisan lodge:admin --from-env
    if ($LASTEXITCODE -ne 0) { throw 'Test administrator setup failed' }
    $taskRouter = Join-Path $taskBackend 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
    $taskServer = Start-Process -FilePath (Get-Command php.exe).Source -ArgumentList @('-S','127.0.0.1:8001','-t','.', $taskRouter) -WorkingDirectory (Join-Path $taskBackend 'public') -WindowStyle Hidden -PassThru
    Pop-Location
    Push-Location $taskFrontend
    $taskVite = Start-Process -FilePath (Get-Command node.exe).Source -ArgumentList @('node_modules/vite/bin/vite.js','--host','127.0.0.1','--port','5174','--strictPort') -WorkingDirectory $taskFrontend -WindowStyle Hidden -PassThru
    npx.cmd playwright test tests/admin.spec.ts tests/performance.spec.ts --project=desktop --workers=1 --output=test-results-admin
    if ($LASTEXITCODE -ne 0) { throw 'Admin browser test failed' }
    if ($Public) {
        npx.cmd playwright test tests/public.spec.ts --workers=1 --output=test-results-public
        if ($LASTEXITCODE -ne 0) { throw 'Public browser test failed' }
    }
    Pop-Location
} finally {
    if ($taskServer -and -not $taskServer.HasExited) { Stop-Process -Id $taskServer.Id }
    if ($taskVite -and -not $taskVite.HasExited) { Stop-Process -Id $taskVite.Id }
    $env:DB_DATABASE = $null
    $env:TEST_ADMIN_EMAIL = $null
    $env:TEST_ADMIN_PASSWORD = $null
    $env:LODGE_ADMIN_EMAIL = $null
    $env:LODGE_ADMIN_PASSWORD = $null
    $env:TEST_BASE_URL = $null
    $env:FRONTEND_URL = $null
    $env:FRONTEND_ALLOWED_ORIGINS = $null
    $env:SANCTUM_STATEFUL_DOMAINS = $null
    $env:VITE_API_BASE_URL = $null
    $env:DEV_API_PROXY_TARGET = $null
    $env:TEST_SAME_ORIGIN = $null
    $env:MAIL_MAILER = $null
}
