@echo off
setlocal EnableExtensions

set "OPUS_ROOT=H:\ASAP"
set "PHP=H:\UwAmp\bin\php\php-8.5.6\php.exe"
set "RECIPE=%OPUS_ROOT%\tests\recipe\p112q2h_database_provider_site_config_foundation_recipe.php"

echo P112Q2H_DATABASE_PROVIDER_SITE_CONFIG_FOUNDATION_RECIPE_START

if not exist "%OPUS_ROOT%" goto opus_missing
if not exist "%PHP%" goto php_missing
if not exist "%RECIPE%" goto recipe_missing

"%PHP%" -d display_errors=1 "%RECIPE%" || goto recipe_failed

if exist "%OPUS_ROOT%\tools\automation\p112q2g_root_namespace_and_render_cleanup_recipe_runner.cmd" (
    call "%OPUS_ROOT%\tools\automation\p112q2g_root_namespace_and_render_cleanup_recipe_runner.cmd" || goto q2g_recipe_failed
)

echo P112Q2H_DATABASE_PROVIDER_SITE_CONFIG_FOUNDATION_RECIPE_OK
exit /b 0

:opus_missing
echo OPUS_ROOT_MISSING
exit /b 1

:php_missing
echo UWAMP_PHP_MISSING
exit /b 1

:recipe_missing
echo P112Q2H_RECIPE_MISSING
exit /b 1

:recipe_failed
echo P112Q2H_RECIPE_FAILED
exit /b 1

:q2g_recipe_failed
echo P112Q2G_RECIPE_REGRESSION
exit /b 1
