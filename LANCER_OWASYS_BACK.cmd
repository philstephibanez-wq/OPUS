@echo off
setlocal
cd /d "%~dp0"
composer opus:dev-server -- owasys-back
if errorlevel 1 pause
endlocal
