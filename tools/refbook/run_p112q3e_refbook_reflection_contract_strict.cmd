@echo off
setlocal
cd /d "%~dp0\..\.."
set ASAP_P112Q3E_STRICT=1
php tools\refbook\p112q3e_refbook_reflection_contract.php --strict
set EC=%ERRORLEVEL%
echo.
echo ExitCode=%EC%
pause
exit /b %EC%
