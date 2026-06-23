@echo off
setlocal

cd /d %~dp0

echo == P4I_MOVE_ROUTER_CLASS_OUT_OF_ROOT ==
python tools\migrations\apply_p4i_move_router_class_out_of_root.py
if errorlevel 1 goto fail

php -l Opus\Router\Router.class.php
if errorlevel 1 goto fail

composer dump-autoload
if errorlevel 1 goto fail

php -r "require 'vendor/autoload.php'; if (!class_exists('OPUS_Router')) { fwrite(STDERR, 'OPUS_ROUTER_CLASS_NOT_AUTOLOADED'.PHP_EOL); exit(1); } echo 'OPUS_ROUTER_AUTOLOAD_OK'.PHP_EOL;"
if errorlevel 1 goto fail

git status --short
if errorlevel 1 goto fail

echo P4I_MOVE_ROUTER_CLASS_OUT_OF_ROOT_OK
exit /b 0

:fail
echo P4I_MOVE_ROUTER_CLASS_OUT_OF_ROOT_FAILED
exit /b 1
