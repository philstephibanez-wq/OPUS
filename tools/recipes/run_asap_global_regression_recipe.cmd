@echo off
setlocal
cd /d "%~dp0\..\.."
php tools\recipes\asap_global_regression_recipe.php
set EC=%ERRORLEVEL%
echo.
echo ExitCode=%EC%
pause
exit /b %EC%
