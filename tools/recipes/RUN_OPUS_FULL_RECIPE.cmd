@echo off
setlocal EnableExtensions

set "OPUS_ROOT=%~dp0..\.."
pushd "%OPUS_ROOT%" >nul || goto opus_missing

php -d display_errors=1 tools\recipes\opus_full_recipe.php || goto recipe_failed

popd >nul
exit /b 0

:opus_missing
echo OPUS_ROOT_MISSING
exit /b 1

:recipe_failed
popd >nul
echo OPUS_GLOBAL_RECIPE_CMD_FAILED
exit /b 1
