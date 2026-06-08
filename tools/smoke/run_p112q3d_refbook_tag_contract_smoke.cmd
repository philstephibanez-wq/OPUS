@echo off
setlocal
cd /d "%~dp0..\.."
php tools\smoke\p112q3d_refbook_tag_contract_smoke.php
set EXITCODE=%ERRORLEVEL%
echo.
echo ExitCode=%EXITCODE%
pause
exit /b %EXITCODE%
