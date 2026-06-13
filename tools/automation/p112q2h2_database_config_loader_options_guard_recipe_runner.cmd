@echo off
setlocal EnableExtensions

set "OPUS_ROOT=H:\ASAP"
set "PHP=H:\UwAmp\bin\php\php-8.5.6\php.exe"
set "RECIPE=%OPUS_ROOT%\tests\recipe\p112q2h2_database_config_loader_options_guard_recipe.php"

echo P112Q2H2_DATABASE_CONFIG_LOADER_OPTIONS_GUARD_RECIPE_START

if not exist "%OPUS_ROOT%" goto opus_missing
if not exist "%PHP%" goto php_missing
if not exist "%RECIPE%" goto recipe_missing

"%PHP%" -d display_errors=1 "%RECIPE%" || goto recipe_failed

if exist "%OPUS_ROOT%\tools\automation\p112q2h_database_provider_site_config_foundation_recipe_runner.cmd" (
    call "%OPUS_ROOT%\tools\automation\p112q2h_database_provider_site_config_foundation_recipe_runner.cmd" || goto q2h_recipe_failed
)

echo P112Q2H2_DATABASE_CONFIG_LOADER_OPTIONS_GUARD_RECIPE_OK
exit /b 0

:opus_missing
echo OPUS_ROOT_MISSING
exit /b 1

:php_missing
echo UWAMP_PHP_MISSING
exit /b 1

:recipe_missing
echo P112Q2H2_RECIPE_MISSING
exit /b 1

:recipe_failed
echo P112Q2H2_RECIPE_FAILED
exit /b 1

:q2h_recipe_failed
echo P112Q2H_RECIPE_REGRESSION
exit /b 1
