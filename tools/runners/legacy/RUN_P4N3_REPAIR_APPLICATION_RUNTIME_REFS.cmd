@echo off
setlocal EnableExtensions

cd /d "%~dp0" || exit /b 1

echo == P4N3_REPAIR_APPLICATION_RUNTIME_REFS ==
python tools\migrations\apply_p4n3_repair_application_runtime_refs.py
if errorlevel 1 (
  echo P4N3_REPAIR_APPLICATION_RUNTIME_REFS_FAILED
  exit /b 1
)

php -l Opus\Application\ApplicationDefinition.php || exit /b 1
php -l Opus\Application\ApplicationRegistry.php || exit /b 1
php -l Opus\Bootstrap.php || exit /b 1
php -l Opus\Kernel.php || exit /b 1
php -l Opus\Router.php || exit /b 1
php -l Opus\Security\Acl.php || exit /b 1
php -l Opus\FSM\Fsm.php || exit /b 1

composer dump-autoload
if errorlevel 1 exit /b 1

echo P4N3_REPAIR_APPLICATION_RUNTIME_REFS_OK
