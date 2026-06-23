@echo off
setlocal
cd /d "%~dp0"

echo == P4M_MOVE_ACL_FSM_CLASSES ==
python tools\migrations\apply_p4m_move_acl_fsm_classes.py
if errorlevel 1 (
  echo P4M_MOVE_ACL_FSM_CLASSES_FAILED
  exit /b 1
)

php -l Opus\Security\Acl.php
if errorlevel 1 exit /b 1

php -l Opus\FSM\Fsm.php
if errorlevel 1 exit /b 1

composer dump-autoload
if errorlevel 1 exit /b 1

php -r "require 'vendor/autoload.php'; if (!class_exists('Opus\\Security\\Acl')) { fwrite(STDERR, 'AUTOLOAD_ACL_FAILED'.PHP_EOL); exit(1); } if (!class_exists('Opus\\FSM\\Fsm')) { fwrite(STDERR, 'AUTOLOAD_FSM_FAILED'.PHP_EOL); exit(1); } echo 'P4M_AUTOLOAD_OK', PHP_EOL;"
if errorlevel 1 exit /b 1

git status --short
endlocal
