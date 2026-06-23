@echo off
setlocal EnableExtensions
cd /d "%~dp0"

echo == P4J_MOVE_LEGACY_ROOT_CLASSES ==
python tools\migrations\apply_p4j_move_legacy_root_classes.py
if errorlevel 1 goto fail

php -l Opus\Accessor\AccessorInterface.class.php
if errorlevel 1 goto fail
php -l Opus\Config\ConfigLoader.class.php
if errorlevel 1 goto fail
php -l Opus\Config\Configuration.class.php
if errorlevel 1 goto fail
php -l Opus\Debug\Debug.class.php
if errorlevel 1 goto fail
php -l Opus\Exception\Exception.class.php
if errorlevel 1 goto fail
php -l Opus\Xml\SimpleXMLElementExtended.class.php
if errorlevel 1 goto fail
php -l Opus\Core\Singleton.class.php
if errorlevel 1 goto fail

composer dump-autoload
if errorlevel 1 goto fail

php -r "require 'vendor/autoload.php'; $symbols = ['OPUS_AccessorInterface', 'OPUS_ConfigLoader', 'OPUS_Configuration', 'OPUS_Debug', 'OPUS_Exception', 'OPUS_SimpleXMLElementExtended', 'OPUS_Singleton']; foreach ($symbols as $symbol) { if (!class_exists($symbol) && !interface_exists($symbol)) { fwrite(STDERR, 'AUTOLOAD_FAILED=' . $symbol . PHP_EOL); exit(1); } } echo 'P4J_AUTOLOAD_OK', PHP_EOL;"
if errorlevel 1 goto fail

git status --short
echo P4J_MOVE_LEGACY_ROOT_CLASSES_OK
exit /b 0

:fail
echo P4J_MOVE_LEGACY_ROOT_CLASSES_FAILED
exit /b 1
