@echo off
setlocal
cd /d "%~dp0"
set "OPUS_ENV=development"
set "OWASYS_ACCEPTANCE_PORT=18080"
start "OWASYS Visual Acceptance Server" cmd /k "cd /d ""%~dp0"" && set OPUS_ENV=development && php -S 127.0.0.1:%OWASYS_ACCEPTANCE_PORT% -t sites\owasys\www tools\owasys_acceptance_router.php"
timeout /t 2 /nobreak >nul
start "" "http://127.0.0.1:%OWASYS_ACCEPTANCE_PORT%/login"
endlocal
