@echo off
setlocal EnableExtensions
cd /d "%~dp0\..\.."

echo P116B5_LEGACY_ALIAS: use run_p116b5_live_score_recipe_mailpit.cmd
call tools\recipes\run_p116b5_live_score_recipe_mailpit.cmd
exit /b %ERRORLEVEL%
