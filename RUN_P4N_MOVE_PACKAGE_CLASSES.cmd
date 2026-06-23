@echo off
setlocal
cd /d "%~dp0"
echo == P4N_MOVE_PACKAGE_CLASSES ==

python tools\migrations\apply_p4n_move_package_classes.py
if errorlevel 1 goto fail

php -l Opus\Package\Package.php
if errorlevel 1 goto fail
php -l Opus\Package\PackageRepository.php
if errorlevel 1 goto fail
php -l Opus\Kernel.php
if errorlevel 1 goto fail
php -l Opus\Router.php
if errorlevel 1 goto fail

composer dump-autoload
if errorlevel 1 goto fail

php -r "require 'vendor/autoload.php'; foreach (['Opus\\Package\\Package','Opus\\Package\\PackageRepository'] as $class) { if (!class_exists($class)) { fwrite(STDERR, 'AUTOLOAD_MISSING=' . $class . PHP_EOL); exit(1); } } foreach (['Opus\\Package','Opus\\PackageRepository'] as $class) { if (class_exists($class)) { fwrite(STDERR, 'LEGACY_CLASS_STILL_AUTOLOADS=' . $class . PHP_EOL); exit(1); } } echo 'P4N_PACKAGE_AUTOLOAD_OK', PHP_EOL;"
if errorlevel 1 goto fail

echo P4N_MOVE_PACKAGE_CLASSES_OK
exit /b 0

:fail
echo P4N_MOVE_PACKAGE_CLASSES_FAILED
exit /b 1
