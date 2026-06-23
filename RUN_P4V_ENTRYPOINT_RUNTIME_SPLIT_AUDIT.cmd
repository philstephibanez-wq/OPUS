@echo off
setlocal
cd /d "%~dp0"
echo == P4V_ENTRYPOINTS_RUNTIME_SPLIT ==
python tools\audits\audit_p4v_entrypoints_runtime_split.py
if errorlevel 1 (
  echo P4V_ENTRYPOINTS_RUNTIME_SPLIT_FAILED
  exit /b 1
)
echo P4V_ENTRYPOINTS_RUNTIME_SPLIT_DONE
