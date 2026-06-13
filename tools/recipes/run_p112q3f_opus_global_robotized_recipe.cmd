@echo off
setlocal EnableExtensions
cd /d "%~dp0..\.."
php "tools\recipes\p112q3f_opus_global_robotized_recipe.php"
set "EXITCODE=%ERRORLEVEL%"
echo.
echo ExitCode=%EXITCODE%
exit /b %EXITCODE%
