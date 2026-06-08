@echo off
setlocal
cd /d "%~dp0\..\.."
php tools\refbook\p112q3e_refbook_reflection_contract.php
set EC=%ERRORLEVEL%
echo.
echo ExitCode=%EC%
pause
exit /b %EC%
