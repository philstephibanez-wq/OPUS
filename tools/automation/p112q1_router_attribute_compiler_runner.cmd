@echo off
setlocal EnableExtensions

set "ASAP_ROOT=H:\ASAP"
set "PHP=H:\UwAmp\bin\php\php-8.5.6\php.exe"
set "SMOKE=%ASAP_ROOT%\tests\smoke\p112q1_router_attribute_compiler_smoke.php"

echo P112Q1_ROUTER_ATTRIBUTE_COMPILER_START

if not exist "%ASAP_ROOT%" goto asap_missing
if not exist "%PHP%" goto php_missing
if not exist "%SMOKE%" goto smoke_missing

"%PHP%" -d display_errors=1 "%SMOKE%" || goto smoke_failed

echo P112Q1_ROUTER_ATTRIBUTE_COMPILER_OK
exit /b 0

:asap_missing
echo ASAP_ROOT_MISSING
exit /b 1

:php_missing
echo UWAMP_PHP_MISSING
exit /b 1

:smoke_missing
echo P112Q1_SMOKE_MISSING
exit /b 1

:smoke_failed
echo P112Q1_ROUTER_ATTRIBUTE_COMPILER_FAILED
exit /b 1
