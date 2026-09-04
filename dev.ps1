<#
    Starts everything the application needs in development.

    Run from the repository root:  ./dev.ps1

    The worker is started here rather than left to be remembered, and that is the point of
    this file existing at all. Nothing warns you that it is not running: results are recorded,
    the bell simply stays empty, and the messages sit in `messenger_messages` waiting for
    somebody to notice. A background job that fails silently is worse than one that fails.
#>

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot

Write-Host 'Starting the API on http://127.0.0.1:8000' -ForegroundColor Green
Push-Location (Join-Path $root 'backend')
symfony server:start -d --port=8000
Pop-Location

Write-Host 'Starting the SPA on http://localhost:5173' -ForegroundColor Green
Push-Location (Join-Path $root 'frontend')
Start-Process -FilePath 'pnpm' -ArgumentList 'run', 'dev' -NoNewWindow
Pop-Location

# --time-limit rather than an endless run, and it is not a stylistic choice: `messenger:consume`
# handles a stop signal through ext-pcntl, which does not exist on Windows. Without a limit the
# only way to stop the worker is to kill it, possibly halfway through handling a message.
#
# It also solves the other Windows nuisance. A worker holds the container it booted with, so it
# keeps running code you have already changed. Letting it end every ten minutes bounds how long
# stale code can survive; `php bin/console messenger:stop-workers` from backend/ ends it sooner.
Write-Host 'Starting the worker (async queue and the reminder schedule)' -ForegroundColor Green
Push-Location (Join-Path $root 'backend')
Start-Process -FilePath 'php' -ArgumentList @(
    'bin/console', 'messenger:consume', 'async', 'scheduler_reminders',
    '--time-limit=600', '--no-interaction'
) -NoNewWindow
Pop-Location

Write-Host ''
Write-Host 'API logs:      symfony server:log          (from backend/)' -ForegroundColor DarkGray
Write-Host 'Stop API:      symfony server:stop         (from backend/)' -ForegroundColor DarkGray
Write-Host 'Reload worker: php bin/console messenger:stop-workers  (from backend/)' -ForegroundColor DarkGray
Write-Host 'Failed jobs:   php bin/console messenger:failed:show   (from backend/)' -ForegroundColor DarkGray
