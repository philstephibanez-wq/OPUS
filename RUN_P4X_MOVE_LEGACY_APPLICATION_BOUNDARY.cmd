@echo off
setlocal
cd /d "%~dp0"
echo == P4X_MOVE_LEGACY_APPLICATION_BOUNDARY ==
python tools\migrations\apply_p4x_move_legacy_application_boundary.py
if errorlevel 1 (
  echo P4X_MOVE_LEGACY_APPLICATION_BOUNDARY_FAILED
  exit /b 1
)
echo P4X_MOVE_LEGACY_APPLICATION_BOUNDARY_DONE
