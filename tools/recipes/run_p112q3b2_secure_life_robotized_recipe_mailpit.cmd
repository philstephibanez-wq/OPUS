@echo off
setlocal
cd /d "%~dp0\..\.."

if not defined OPUS_P112Q3B2_REPORT_EMAIL_TO (
  set /p OPUS_P112Q3B2_REPORT_EMAIL_TO=Email destinataire du rapport P112Q3B2 : 
)

if not defined OPUS_P112Q3B2_REPORT_EMAIL_FROM set OPUS_P112Q3B2_REPORT_EMAIL_FROM=opus-recipes@localhost
set OPUS_P112Q3B2_MAIL_MODE=smtp
set OPUS_P112Q3B2_MAIL_REQUIRED=1
if not defined OPUS_P112Q3B2_SMTP_HOST set OPUS_P112Q3B2_SMTP_HOST=127.0.0.1
if not defined OPUS_P112Q3B2_SMTP_PORT set OPUS_P112Q3B2_SMTP_PORT=1025
if not defined OPUS_P112Q3B2_REPORT_PORT set OPUS_P112Q3B2_REPORT_PORT=8792

tools\recipes\run_p112q3b2_secure_life_robotized_recipe.cmd
exit /b %ERRORLEVEL%
