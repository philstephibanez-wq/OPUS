@echo off
setlocal EnableExtensions
cd /d "%~dp0..\.."
php "tools\smoke\p112q3e5a_router_legacy_vs_routing_canonical_smoke.php"
set "EXITCODE=%ERRORLEVEL%"
echo.
echo ExitCode=%EXITCODE%
exit /b %EXITCODE%
