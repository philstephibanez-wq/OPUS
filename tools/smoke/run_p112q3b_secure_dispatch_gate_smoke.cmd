@echo off
setlocal
cd /d "%~dp0\..\.."
php tools\smoke\p112q3b_secure_dispatch_gate_smoke.php
set "EXITCODE=%ERRORLEVEL%"
echo.
echo ExitCode=%EXITCODE%
pause
exit /b %EXITCODE%
