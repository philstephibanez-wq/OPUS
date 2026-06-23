@echo off
setlocal EnableExtensions
cd /d "%~dp0"
echo == P4W_MOVE_LEGACY_ROOT_AUTOLOADER_BOUNDARY ==
python tools\migrations\apply_p4w_move_legacy_root_autoloader_boundary.py
if errorlevel 1 (
  echo P4W_MOVE_LEGACY_ROOT_AUTOLOADER_BOUNDARY_FAILED
  exit /b 1
)
echo P4W_MOVE_LEGACY_ROOT_AUTOLOADER_BOUNDARY_DONE
