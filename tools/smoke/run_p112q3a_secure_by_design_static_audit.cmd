@echo off
setlocal
cd /d "%~dp0\..\.."
php tools\smoke\p112q3a_secure_by_design_static_audit.php
set EXITCODE=%ERRORLEVEL%
echo ExitCode=%EXITCODE%
exit /b %EXITCODE%
