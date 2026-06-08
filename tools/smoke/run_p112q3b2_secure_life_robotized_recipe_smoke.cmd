@echo off
setlocal
cd /d "%~dp0\..\.."
php tools\smoke\p112q3b2_secure_life_robotized_recipe_smoke.php
set EXITCODE=%ERRORLEVEL%
echo.
echo ExitCode=%EXITCODE%
pause
exit /b %EXITCODE%
