@echo off
setlocal

cd /d "%~dp0"

echo == P4G_APPLICATION_USES_FSM_PROGRAM ==

python tools\migrations\apply_p4g_application_uses_fsm_program.py
if errorlevel 1 exit /b %errorlevel%

php -l Opus\Application.class.php
if errorlevel 1 exit /b %errorlevel%

php -r "require 'Opus/FSM/Program.class.php'; $fsm = OPUS_FSM_Program::fromFile('smoke', 'config/fsm.boot.php'); $fsm->run(); if (!$fsm->isReady()) { fwrite(STDERR, 'FSM_NOT_READY'.PHP_EOL); exit(1); } echo 'P4G_FSM_PROGRAM_SMOKE_OK'.PHP_EOL;"
if errorlevel 1 exit /b %errorlevel%

git status --short

echo P4G_APPLICATION_USES_FSM_PROGRAM_READY_TO_COMMIT
