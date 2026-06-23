@echo off
setlocal
cd /d "%~dp0"
echo == P4S_LEGACY_ROOT_USAGE_AUDIT ==
python tools\audits\audit_p4s_legacy_root_usage.py
if errorlevel 1 (
  echo P4S_LEGACY_ROOT_USAGE_AUDIT_FAILED
  exit /b 1
)
echo P4S_LEGACY_ROOT_USAGE_AUDIT_DONE
