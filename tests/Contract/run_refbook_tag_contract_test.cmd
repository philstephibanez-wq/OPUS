@echo off
setlocal
cd /d "%~dp0..\.."
php tests\Contract\RefBookTagContractTest.php
set EXITCODE=%ERRORLEVEL%
echo.
echo ExitCode=%EXITCODE%
pause
exit /b %EXITCODE%
