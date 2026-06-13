@echo off
setlocal EnableExtensions

set "ASAP=H:\ASAP"
set "PHP=H:\UwAmp\bin\php\php-8.5.6\php.exe"
set "SMOKE=%ASAP%\tests\smoke\p112o_safe_medium_compat_shims_smoke.php"

echo P112O_SAFE_MEDIUM_COMPAT_SMOKE_START

if not exist "%ASAP%" goto opus_missing
if not exist "%PHP%" goto php_missing
if not exist "%SMOKE%" goto smoke_missing

"%PHP%" -d display_errors=1 "%SMOKE%" || goto smoke_failed

echo P112O_SAFE_MEDIUM_COMPAT_SMOKE_OK
exit /b 0

:opus_missing
echo OPUS_ROOT_MISSING
exit /b 1

:php_missing
echo UWAMP_PHP_MISSING
exit /b 1

:smoke_missing
echo P112O_SMOKE_MISSING
exit /b 1

:smoke_failed
echo P112O_SAFE_MEDIUM_COMPAT_SMOKE_FAILED
exit /b 1
