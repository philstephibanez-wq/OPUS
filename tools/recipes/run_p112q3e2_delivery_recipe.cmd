@echo off
setlocal EnableExtensions
cd /d "%~dp0..\.."
php "tools\recipes\p112q3e2_delivery_recipe.php"
set "EXITCODE=%ERRORLEVEL%"
echo.
echo ExitCode=%EXITCODE%
exit /b %EXITCODE%
