param([switch]$SameOrigin, [switch]$Public, [switch]$BundledFrontend, [int]$TestDatabasePort = 0, [switch]$MySql, [string]$TestGrep = "")
$ErrorActionPreference = 'Stop'
$taskRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$taskBackend = Join-Path $taskRoot 'backend'
$taskFrontend = Join-Path $taskRoot 'frontend'
$taskPgBin = 'C:\Program Files\PostgreSQL\18\bin'
$taskEnv = Get-Content -LiteralPath (Join-Path $taskBackend '.env')
function Read-TaskEnv([string]$key) { ($taskEnv | Where-Object { $_.StartsWith($key + '=') } | Select-Object -First 1).Substring($key.Length + 1) }
$taskOriginalDbPort = $env:DB_PORT
$taskOriginalDriver = $env:DB_CONNECTION
$taskOriginalUser = $env:DB_USERNAME
$taskOriginalPassword = $env:DB_PASSWORD
$taskOriginalHost = $env:DB_HOST
if ($MySql) {
    $taskPort = if ($TestDatabasePort) { "$TestDatabasePort" } else { '3306' }
    $env:DB_CONNECTION = 'mysql'
    $env:DB_HOST = '127.0.0.1'
    $env:DB_PORT = $taskPort
    $env:DB_USERNAME = 'root'
    $taskMySqlPassword = if ($env:TEST_MYSQL_PASSWORD) { $env:TEST_MYSQL_PASSWORD } else { '' }
    $env:DB_PASSWORD = if ($taskMySqlPassword) { $taskMySqlPassword } else { '(empty)' }
    $env:MYSQL_PWD = $taskMySqlPassword
    & 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe' --host=127.0.0.1 --port=$taskPort --user=root --execute='CREATE DATABASE IF NOT EXISTS mason_mysql_browser CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
    $env:MYSQL_PWD = $null
    if ($LASTEXITCODE -ne 0) { throw 'Browser test database setup failed' }
    $env:DB_DATABASE = 'mason_mysql_browser'
} else {
    $env:PGPASSWORD = Read-TaskEnv 'DB_PASSWORD'
    $taskHost = Read-TaskEnv 'DB_HOST'
    $taskPort = Read-TaskEnv 'DB_PORT'
    if ($TestDatabasePort) { $taskPort = "$TestDatabasePort" }
    $env:DB_PORT = $taskPort
    $taskUser = Read-TaskEnv 'DB_USERNAME'
    & (Join-Path $taskPgBin 'createdb.exe') -w -h $taskHost -p $taskPort -U $taskUser mason_browser
    $env:PGPASSWORD = $null
    $env:DB_DATABASE = 'mason_browser'
}
$env:TEST_ADMIN_EMAIL = 'browser-admin@example.test'
$env:TEST_ADMIN_PASSWORD = 'Browser!' + [guid]::NewGuid().ToString('N') + 'Aa42'
$env:LODGE_ADMIN_1_USERNAME = 'browser-master'
$env:LODGE_ADMIN_1_EMAIL = $env:TEST_ADMIN_EMAIL
$env:LODGE_ADMIN_1_PASSWORD = $env:TEST_ADMIN_PASSWORD
$env:LODGE_ADMIN_2_USERNAME = 'browser-warden'
$env:LODGE_ADMIN_2_EMAIL = 'browser-warden@example.test'
$env:LODGE_ADMIN_2_PASSWORD = $env:TEST_ADMIN_PASSWORD
$env:LODGE_ADMIN_EMAIL = $env:TEST_ADMIN_EMAIL
$env:LODGE_ADMIN_PASSWORD = $env:TEST_ADMIN_PASSWORD
$env:TEST_BASE_URL = 'http://127.0.0.1:5174'
if ($BundledFrontend) { $SameOrigin = $true; $env:TEST_BASE_URL = 'http://127.0.0.1:8001' }
$env:FRONTEND_URL = $env:TEST_BASE_URL
$env:FRONTEND_ALLOWED_ORIGINS = $env:TEST_BASE_URL
$env:SANCTUM_STATEFUL_DOMAINS = ([uri]$env:TEST_BASE_URL).Authority
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
    if ($BundledFrontend) {
        Push-Location $taskFrontend
        npm.cmd run build
        if ($LASTEXITCODE -ne 0) { throw 'Bundled frontend build failed' }
        Get-ChildItem -LiteralPath (Join-Path $taskFrontend 'dist') | Copy-Item -Destination (Join-Path $taskBackend 'public') -Recurse -Force
        Pop-Location
    }
    $taskServer = Start-Process -FilePath (Get-Command php.exe).Source -ArgumentList @('-S','127.0.0.1:8001','-t','.', $taskRouter) -WorkingDirectory (Join-Path $taskBackend 'public') -WindowStyle Hidden -PassThru
    Pop-Location
    Push-Location $taskFrontend
    if (-not $BundledFrontend) {
        $taskVite = Start-Process -FilePath (Get-Command node.exe).Source -ArgumentList @('node_modules/vite/bin/vite.js','--host','127.0.0.1','--port','5174','--strictPort') -WorkingDirectory $taskFrontend -WindowStyle Hidden -PassThru
    }
    $taskPlaywrightArgs = @('playwright','test','tests/admin.spec.ts','tests/performance.spec.ts','--project=desktop','--workers=1','--output=test-results-admin')
    if ($TestGrep) { $taskPlaywrightArgs += @('--grep',$TestGrep) }
    & npx.cmd @taskPlaywrightArgs
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
    $env:DB_PORT = $taskOriginalDbPort
    $env:DB_CONNECTION = $taskOriginalDriver
    $env:DB_USERNAME = $taskOriginalUser
    $env:DB_PASSWORD = $taskOriginalPassword
    $env:DB_HOST = $taskOriginalHost
    $env:TEST_ADMIN_EMAIL = $null
    $env:TEST_ADMIN_PASSWORD = $null
    foreach ($taskSeedNumber in @(1,2)) {
        foreach ($taskSeedSuffix in @('USERNAME','EMAIL','PASSWORD')) {
            [Environment]::SetEnvironmentVariable("LODGE_ADMIN_${taskSeedNumber}_${taskSeedSuffix}", $null, 'Process')
        }
    }
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
