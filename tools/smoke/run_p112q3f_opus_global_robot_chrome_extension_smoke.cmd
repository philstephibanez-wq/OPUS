@echo off
setlocal EnableExtensions
cd /d "%~dp0..\.."
php "tools\smoke\p112q3f_opus_global_robot_chrome_extension_smoke.php"
set "EXITCODE=%ERRORLEVEL%"
echo.
echo ExitCode=%EXITCODE%
exit /b %EXITCODE%
