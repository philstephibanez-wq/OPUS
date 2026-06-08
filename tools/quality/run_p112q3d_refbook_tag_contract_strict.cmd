@echo off
setlocal
cd /d "%~dp0..\.."
set ASAP_P112Q3D_STRICT=1
php tools\quality\p112q3d_refbook_tag_contract.php
set EXITCODE=%ERRORLEVEL%
echo.
echo ExitCode=%EXITCODE%
pause
exit /b %EXITCODE%
