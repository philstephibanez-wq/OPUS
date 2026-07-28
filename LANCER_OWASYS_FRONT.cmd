@echo off
setlocal
cd /d "%~dp0"
composer opus:dev-server -- owasys-front
if errorlevel 1 pause
endlocal
