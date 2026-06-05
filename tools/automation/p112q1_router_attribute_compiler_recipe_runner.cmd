@echo off
setlocal EnableExtensions

set "ASAP_ROOT=H:\ASAP"
set "PHP=H:\UwAmp\bin\php\php-8.5.6\php.exe"
set "RECIPE=%ASAP_ROOT%\tests\recipe\p112q1_router_attribute_compiler_recipe.php"

echo P112Q1_ROUTER_ATTRIBUTE_COMPILER_RECIPE_START

if not exist "%ASAP_ROOT%" goto asap_missing
if not exist "%PHP%" goto php_missing
if not exist "%RECIPE%" goto recipe_missing

"%PHP%" -d display_errors=1 "%RECIPE%" || goto recipe_failed

echo P112Q1_ROUTER_ATTRIBUTE_COMPILER_RECIPE_OK
exit /b 0

:asap_missing
echo ASAP_ROOT_MISSING
exit /b 1

:php_missing
echo UWAMP_PHP_MISSING
exit /b 1

:recipe_missing
echo P112Q1_RECIPE_MISSING
exit /b 1

:recipe_failed
echo P112Q1_ROUTER_ATTRIBUTE_COMPILER_RECIPE_FAILED
exit /b 1
