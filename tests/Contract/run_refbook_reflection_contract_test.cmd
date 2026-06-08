@echo off
setlocal
cd /d "%~dp0\..\.."
php tests\Contract\RefBookReflectionContractTest.php
set EC=%ERRORLEVEL%
echo.
echo ExitCode=%EC%
pause
exit /b %EC%
