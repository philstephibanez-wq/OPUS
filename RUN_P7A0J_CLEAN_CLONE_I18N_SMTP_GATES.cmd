@echo off
setlocal EnableExtensions
cd /d "%~dp0"
python tools\smokes\smoke_p7a0j_clean_clone_i18n_smtp_gates.py
set "RC=%ERRORLEVEL%"
echo P7A0J_EXIT_CODE=%RC%
if not "%RC%"=="0" exit /b %RC%
endlocal
