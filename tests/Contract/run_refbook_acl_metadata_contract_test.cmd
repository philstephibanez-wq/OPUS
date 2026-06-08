@echo off
setlocal EnableExtensions
cd /d "%~dp0..\.."
php "tests\Contract\RefBookAclMetadataContractTest.php"
set "EXITCODE=%ERRORLEVEL%"
echo.
echo ExitCode=%EXITCODE%
exit /b %EXITCODE%
