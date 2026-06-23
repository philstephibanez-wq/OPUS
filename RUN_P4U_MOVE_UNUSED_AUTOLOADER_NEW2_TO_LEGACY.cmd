@echo off
setlocal
cd /d "%~dp0"
echo == P4U_MOVE_UNUSED_AUTOLOADER_NEW2_TO_LEGACY ==
python tools\migrations\apply_p4u_move_unused_autoloader_new2_to_legacy.py
if errorlevel 1 echo P4U_MOVE_UNUSED_AUTOLOADER_NEW2_TO_LEGACY_FAILED
if not errorlevel 1 echo P4U_MOVE_UNUSED_AUTOLOADER_NEW2_TO_LEGACY_DONE
