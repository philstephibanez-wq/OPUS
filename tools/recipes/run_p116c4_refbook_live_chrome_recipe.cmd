@echo off
setlocal EnableExtensions
cd /d "%~dp0..\.."
php -d display_errors=1 tools\recipes\p116c4_refbook_live_chrome_recipe.php
exit /b %ERRORLEVEL%
