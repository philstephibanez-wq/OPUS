@echo off
setlocal EnableExtensions
cd /d "%~dp0"

echo == P4L_MOVE_SUPPORT_CLASS_TO_FOUNDATION ==
python tools\migrations\apply_p4l_move_support_class.py
if errorlevel 1 (
  echo P4L_MOVE_SUPPORT_CLASS_TO_FOUNDATION_FAILED
  exit /b 1
)

php -l Opus\Foundation\Support.php
if errorlevel 1 exit /b 1

php -l Opus\Http\Request.php
if errorlevel 1 exit /b 1

php -l Opus\Router.php
if errorlevel 1 exit /b 1

composer dump-autoload
if errorlevel 1 exit /b 1

php -r "require 'vendor/autoload.php'; if (!class_exists('Opus\\Foundation\\Support')) { fwrite(STDERR, 'AUTOLOAD_SUPPORT_FAILED'.PHP_EOL); exit(1); } echo 'AUTOLOAD_SUPPORT_OK', PHP_EOL;"
if errorlevel 1 exit /b 1

git status --short
