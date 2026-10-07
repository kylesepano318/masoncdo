$ErrorActionPreference = 'Stop'
$taskRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$taskStage = Join-Path $taskRoot ('.local/hostinger-' + [guid]::NewGuid().ToString('N'))
$taskApp = Join-Path $taskStage 'lodge'
$taskPublic = Join-Path $taskStage 'public_html'
$taskOldApi = $env:VITE_API_BASE_URL
try {
    $env:VITE_API_BASE_URL = '/'
    Push-Location (Join-Path $taskRoot 'frontend')
    try {
        npm.cmd run build
        if ($LASTEXITCODE -ne 0) { throw 'Frontend build failed' }
    } finally { Pop-Location }
} finally { $env:VITE_API_BASE_URL = $taskOldApi }
New-Item -ItemType Directory -Force $taskApp,$taskPublic | Out-Null
foreach ($taskFolder in @('app','config','database','resources','routes')) {
    Copy-Item -LiteralPath (Join-Path $taskRoot "backend/$taskFolder") -Destination $taskApp -Recurse
}
New-Item -ItemType Directory -Force (Join-Path $taskApp 'bootstrap/cache') | Out-Null
Copy-Item -LiteralPath (Join-Path $taskRoot 'backend/bootstrap/app.php'),(Join-Path $taskRoot 'backend/bootstrap/providers.php') -Destination (Join-Path $taskApp 'bootstrap')
foreach ($taskFolder in @('storage/app/private','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs')) {
    New-Item -ItemType Directory -Force (Join-Path $taskApp $taskFolder) | Out-Null
    Set-Content -LiteralPath (Join-Path $taskApp "$taskFolder/.gitignore") -Value "*`n!.gitignore" -Encoding ascii
}
Copy-Item -LiteralPath (Join-Path $taskRoot 'backend/artisan'),(Join-Path $taskRoot 'backend/composer.json'),(Join-Path $taskRoot 'backend/composer.lock') -Destination $taskApp
Copy-Item -LiteralPath (Join-Path $taskRoot 'deployment/hostinger/.env.example') -Destination (Join-Path $taskApp '.env.example')
Get-ChildItem -LiteralPath (Join-Path $taskRoot 'frontend/dist') -Force | Copy-Item -Destination $taskPublic -Recurse
Copy-Item -LiteralPath (Join-Path $taskRoot 'deployment/hostinger/index.php') -Destination $taskPublic
Copy-Item -LiteralPath (Join-Path $taskRoot 'deployment/hostinger/public.htaccess') -Destination (Join-Path $taskPublic '.htaccess')
New-Item -ItemType Directory -Force (Join-Path $taskPublic 'storage') | Out-Null
Copy-Item -LiteralPath (Join-Path $taskRoot 'deployment/hostinger/storage.htaccess') -Destination (Join-Path $taskPublic 'storage/.htaccess')
$taskArchive = Join-Path $taskRoot ('.local/hostinger-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.zip')
& python (Join-Path $PSScriptRoot 'package-hostinger.py') $taskStage $taskArchive
if ($LASTEXITCODE -ne 0) { throw 'Linux-compatible ZIP creation failed; Python 3 is required' }
Write-Output "Upload package (Composer dependencies must be installed on Hostinger): $taskArchive"
