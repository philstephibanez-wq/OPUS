@echo off
setlocal
cd /d "%~dp0\..\.."
php tools\smoke\p112q3b3_recipe_final_status_smoke.php
set EXITCODE=%ERRORLEVEL%
echo.
echo ExitCode=%EXITCODE%
pause
exit /b %EXITCODE%
