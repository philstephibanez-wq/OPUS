@echo off
setlocal EnableExtensions
cd /d "%~dp0..\.."
php "tools\smoke\p113d1_opus_refbook_rest_api_smoke.php"
set "EXITCODE=%ERRORLEVEL%"
echo.
echo ExitCode=%EXITCODE%
exit /b %EXITCODE%
