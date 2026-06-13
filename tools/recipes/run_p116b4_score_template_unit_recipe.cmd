@echo off
setlocal EnableExtensions
cd /d "%~dp0..\.."
php -d display_errors=1 tools\recipes\p116b4_score_template_unit_recipe.php
exit /b %ERRORLEVEL%
