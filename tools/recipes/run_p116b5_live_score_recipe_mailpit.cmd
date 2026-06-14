@echo off
setlocal EnableExtensions
cd /d "%~dp0\..\.."

if not defined OPUS_P112Q3B2_REPORT_EMAIL_TO (
  set /p OPUS_P112Q3B2_REPORT_EMAIL_TO=Email destinataire du rapport P116B5 : 
)

if not defined OPUS_P112Q3B2_REPORT_EMAIL_FROM set OPUS_P112Q3B2_REPORT_EMAIL_FROM=opus-recipes@localhost
if not defined OPUS_P112Q3B2_SMTP_HOST set OPUS_P112Q3B2_SMTP_HOST=127.0.0.1
if not defined OPUS_P112Q3B2_SMTP_PORT set OPUS_P112Q3B2_SMTP_PORT=1025
if not defined OPUS_P116B5_REPORT_PORT set OPUS_P116B5_REPORT_PORT=8795

php tools\recipes\p116b5_live_score_recipe.php
set EXITCODE=%ERRORLEVEL%

if exist "var\reports\p116b5\p116b5_live_score_recipe.html" (
  echo.
  echo Ouverture du rapport ScoreTemplate local...
  start "Opus P116B5 Score Report Server" cmd /k php -S 127.0.0.1:%OPUS_P116B5_REPORT_PORT% -t "var\reports\p116b5"
  timeout /t 2 /nobreak >nul
  start "" "http://127.0.0.1:%OPUS_P116B5_REPORT_PORT%/p116b5_live_score_recipe.html"
)

echo.
echo ExitCode=%EXITCODE%
pause
exit /b %EXITCODE%
