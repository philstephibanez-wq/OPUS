@echo off
setlocal
cd /d "%~dp0\..\.."
if "%OPUS_P112Q3B_PANTHER_URL%"=="" set "OPUS_P112Q3B_PANTHER_URL=http://127.0.0.1/OPUS_REF_BOOK/?lang=fr"
if "%OPUS_P112Q3B_EXPECT_TEXT%"=="" set "OPUS_P112Q3B_EXPECT_TEXT=ASAP"
php tools\recipes\p112q3b_secure_dispatch_gate_panther_recipe.php
set "EXITCODE=%ERRORLEVEL%"
echo.
echo ExitCode=%EXITCODE%
pause
exit /b %EXITCODE%
