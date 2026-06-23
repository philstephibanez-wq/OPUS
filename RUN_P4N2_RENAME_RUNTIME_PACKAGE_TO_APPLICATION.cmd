@echo off
setlocal
cd /d "%~dp0"
echo == P4N2_RUNTIME_PACKAGE_TO_APPLICATION ==

python tools\migrations\apply_p4n2_runtime_package_to_application.py
if errorlevel 1 goto fail

php -l Opus\Application\ApplicationDefinition.php
if errorlevel 1 goto fail
php -l Opus\Application\ApplicationRegistry.php
if errorlevel 1 goto fail
php -l Opus\Kernel.php
if errorlevel 1 goto fail
php -l Opus\Router.php
if errorlevel 1 goto fail
php -l Opus\View.php
if errorlevel 1 goto fail
php -l Opus\I18n.php
if errorlevel 1 goto fail

composer dump-autoload
if errorlevel 1 goto fail

php -r "require 'vendor/autoload.php'; foreach (['Opus\\Application\\ApplicationDefinition','Opus\\Application\\ApplicationRegistry'] as $class) { if (!class_exists($class)) { fwrite(STDERR, 'AUTOLOAD_MISSING=' . $class . PHP_EOL); exit(1); } } foreach (['Opus\\Package','Opus\\PackageRepository'] as $class) { if (class_exists($class)) { fwrite(STDERR, 'LEGACY_PACKAGE_CLASS_STILL_AUTOLOADS=' . $class . PHP_EOL); exit(1); } } echo 'P4N2_APPLICATION_AUTOLOAD_OK', PHP_EOL;"
if errorlevel 1 goto fail

echo P4N2_RUNTIME_PACKAGE_TO_APPLICATION_OK
exit /b 0

:fail
echo P4N2_RUNTIME_PACKAGE_TO_APPLICATION_FAILED
exit /b 1
