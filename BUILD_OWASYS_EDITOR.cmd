@echo off
setlocal
cd /d "%~dp0"
where npm >nul 2>&1 || exit /b 1
call npm install
if errorlevel 1 exit /b 1
call npm run build:owasys-editor
if errorlevel 1 exit /b 1
php tools\smoke_owasys_codemirror.php
if errorlevel 1 exit /b 1
endlocal
