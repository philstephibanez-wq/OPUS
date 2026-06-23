@echo off
setlocal EnableExtensions DisableDelayedExpansion
cd /d "%~dp0"
echo == P4P_APPLICATION_RUNTIME_TERMINOLOGY ==
python tools\migrations\apply_p4p_application_runtime_terminology.py
if errorlevel 1 (
  echo P4P_APPLICATION_RUNTIME_TERMINOLOGY_FAILED
  exit /b 1
)
echo P4P_APPLICATION_RUNTIME_TERMINOLOGY_DONE
