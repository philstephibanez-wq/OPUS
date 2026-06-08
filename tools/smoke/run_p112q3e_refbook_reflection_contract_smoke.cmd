@echo off
setlocal
cd /d "%~dp0\..\.."
php tools\smoke\p112q3e_refbook_reflection_contract_smoke.php
set EC=%ERRORLEVEL%
echo.
echo ExitCode=%EC%
pause
exit /b %EC%
