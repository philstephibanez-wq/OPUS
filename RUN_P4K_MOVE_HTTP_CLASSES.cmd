@echo off
setlocal
cd /d "%~dp0"

echo == P4K_MOVE_HTTP_CLASSES ==
python tools\migrations\apply_p4k_move_http_classes.py
if errorlevel 1 (
  echo P4K_MOVE_HTTP_CLASSES_FAILED
  exit /b 1
)

php -l Opus\Http\Request.php
if errorlevel 1 exit /b 1

php -l Opus\Http\Response.php
if errorlevel 1 exit /b 1

php -l Opus\Kernel.php
if errorlevel 1 exit /b 1

php -l Opus\Router.php
if errorlevel 1 exit /b 1

composer dump-autoload
if errorlevel 1 exit /b 1

php -r "require 'vendor/autoload.php'; foreach (['Opus\\Http\\Request','Opus\\Http\\Response','Opus\\Kernel','Opus\\Router'] as $c) { if (!class_exists($c)) { fwrite(STDERR, 'AUTOLOAD_MISSING=' . $c . PHP_EOL); exit(1); } } echo 'P4K_AUTOLOAD_OK', PHP_EOL;"
if errorlevel 1 exit /b 1

git status --short

echo P4K_MOVE_HTTP_CLASSES_OK
endlocal
