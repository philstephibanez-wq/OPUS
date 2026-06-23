@echo off
setlocal
cd /d "%~dp0"

echo == P4T_MOVE_VALIDATOR_BOUNDARY ==
python tools\migrations\apply_p4t_move_validator_boundary.py
if errorlevel 1 (
  echo P4T_MOVE_VALIDATOR_BOUNDARY_FAILED
  exit /b 1
)

php -l Opus\Validation\Validator.class.php
if errorlevel 1 (
  echo P4T_MOVE_VALIDATOR_BOUNDARY_FAILED
  exit /b 1
)

where composer >nul 2>nul
if errorlevel 1 (
  echo COMPOSER_NOT_FOUND
  echo P4T_MOVE_VALIDATOR_BOUNDARY_FAILED
  exit /b 1
)

call composer dump-autoload
if errorlevel 1 (
  echo P4T_MOVE_VALIDATOR_BOUNDARY_FAILED
  exit /b 1
)

echo P4T_MOVE_VALIDATOR_BOUNDARY_DONE
