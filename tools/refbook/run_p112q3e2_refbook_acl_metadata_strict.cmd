@echo off
setlocal EnableExtensions
cd /d "%~dp0..\.."
php "tools\refbook\p112q3e2_refbook_acl_metadata_audit.php" --strict
set "EXITCODE=%ERRORLEVEL%"
echo.
echo ExitCode=%EXITCODE%
exit /b %EXITCODE%
