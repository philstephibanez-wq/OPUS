@echo off
setlocal
cd /d "%~dp0"
start "OWASYS BACK" cmd /k call "%~dp0LANCER_OWASYS_BACK.cmd"
start "OWASYS FRONT" cmd /k call "%~dp0LANCER_OWASYS_FRONT.cmd"
endlocal
