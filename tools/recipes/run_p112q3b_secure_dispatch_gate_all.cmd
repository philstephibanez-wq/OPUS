@echo off
setlocal
cd /d "%~dp0\..\.."
php tools\smoke\p112q3b_secure_dispatch_gate_smoke.php
if errorlevel 1 goto fail
if "%ASAP_P112Q3B_PANTHER_URL%"=="" set "ASAP_P112Q3B_PANTHER_URL=http://127.0.0.1/ASAP_REF_BOOK/?lang=fr"
if "%ASAP_P112Q3B_EXPECT_TEXT%"=="" set "ASAP_P112Q3B_EXPECT_TEXT=ASAP"
php tools\recipes\p112q3b_secure_dispatch_gate_panther_recipe.php
set "EXITCODE=%ERRORLEVEL%"
goto end
:fail
set "EXITCODE=%ERRORLEVEL%"
:end
echo.
echo ExitCode=%EXITCODE%
pause
exit /b %EXITCODE%
