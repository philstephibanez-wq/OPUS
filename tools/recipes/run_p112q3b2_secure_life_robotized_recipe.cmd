@echo off
setlocal
cd /d "%~dp0\..\.."

if not defined OPUS_P112Q3B2_REPORT_EMAIL_TO (
  set /p OPUS_P112Q3B2_REPORT_EMAIL_TO=Email destinataire du rapport P112Q3B2 : 
)

if not defined OPUS_P112Q3B2_REPORT_EMAIL_FROM set OPUS_P112Q3B2_REPORT_EMAIL_FROM=opus-recipes@localhost
if not defined OPUS_P112Q3B2_MAIL_MODE set OPUS_P112Q3B2_MAIL_MODE=phpmail
if not defined OPUS_P112Q3B2_MAIL_REQUIRED set OPUS_P112Q3B2_MAIL_REQUIRED=1
if not defined OPUS_P112Q3B2_PANTHER_REQUIRED set OPUS_P112Q3B2_PANTHER_REQUIRED=0
if not defined OPUS_P112Q3B2_REPORT_PORT set OPUS_P112Q3B2_REPORT_PORT=8792

php tools\recipes\p112q3b2_secure_life_robotized_recipe.php
set EXITCODE=%ERRORLEVEL%

if exist "var\reports\p112q3b2\p112q3b2_secure_life_robotized_recipe.html" (
  echo.
  echo Ouverture du rapport visuel local...
  start "Opus P112Q3B2 Report Server" cmd /k php -S 127.0.0.1:%OPUS_P112Q3B2_REPORT_PORT% -t "var\reports\p112q3b2"
  timeout /t 2 /nobreak >nul
  start "" "http://127.0.0.1:%OPUS_P112Q3B2_REPORT_PORT%/p112q3b2_secure_life_robotized_recipe.html"
)

echo.
echo ExitCode=%EXITCODE%
pause
exit /b %EXITCODE%
