@echo off
setlocal
cd /d "%~dp0"
echo == P4Q_MOVE_KERNEL_ROUTER_CLASSES ==
python tools\migrations\apply_p4q_move_kernel_router_classes.py
if errorlevel 1 (
  echo P4Q_MOVE_KERNEL_ROUTER_CLASSES_FAILED
  exit /b 1
)
