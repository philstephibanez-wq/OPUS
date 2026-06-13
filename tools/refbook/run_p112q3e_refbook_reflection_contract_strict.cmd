@echo off
setlocal EnableExtensions
cd /d "%~dp0..\.."
set "OPUS_P112Q3E_STRICT=1"
php "tools\refbook\p112q3e_refbook_reflection_contract.php" --strict
set "EXITCODE=%ERRORLEVEL%"
echo.
echo ExitCode=%EXITCODE%
exit /b %EXITCODE%
