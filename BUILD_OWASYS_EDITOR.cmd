@echo off
setlocal
cd /d "%~dp0"
where npm >nul 2>&1 || exit /b 1
call npm install
if errorlevel 1 goto :fail
call npm run build:owasys-editor
if errorlevel 1 goto :fail
php tools\smoke_owasys_codemirror.php
if errorlevel 1 goto :fail
rmdir /s /q node_modules
endlocal
exit /b 0

:fail
set "OWASYS_EDITOR_BUILD_ERROR=%ERRORLEVEL%"
if exist node_modules rmdir /s /q node_modules
endlocal & exit /b %OWASYS_EDITOR_BUILD_ERROR%
