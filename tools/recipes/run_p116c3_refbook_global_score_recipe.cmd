@echo off
setlocal EnableExtensions
cd /d "%~dp0..\.."
php -d display_errors=1 tools\recipes\p116c3_refbook_global_score_recipe.php
exit /b %ERRORLEVEL%
