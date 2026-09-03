<#
    Starts everything the application needs in development.

    Run from the repository root:  ./dev.ps1
#>

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot

Write-Host 'Starting the API on http://127.0.0.1:8000' -ForegroundColor Green
Push-Location (Join-Path $root 'backend')
symfony server:start -d --port=8000
Pop-Location

Write-Host 'Starting the SPA on http://localhost:5173' -ForegroundColor Green
Push-Location (Join-Path $root 'frontend')
Start-Process -FilePath 'npm' -ArgumentList 'run', 'dev' -NoNewWindow
Pop-Location

Write-Host ''
Write-Host 'API logs:  symfony server:log   (from backend/)' -ForegroundColor DarkGray
Write-Host 'Stop API:  symfony server:stop  (from backend/)' -ForegroundColor DarkGray
