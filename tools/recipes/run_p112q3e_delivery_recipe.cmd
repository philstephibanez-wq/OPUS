@echo off
setlocal
cd /d "%~dp0\..\.."
php tools\recipes\p112q3e_delivery_recipe.php
set EC=%ERRORLEVEL%
echo.
echo ExitCode=%EC%
pause
exit /b %EC%
