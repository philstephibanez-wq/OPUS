@echo off
setlocal EnableExtensions

set "OPUS_ROOT=H:\ASAP"
set "PHP=H:\UwAmp\bin\php\php-8.5.6\php.exe"
set "SMOKE=%OPUS_ROOT%\tests\smoke\p112p1_remaining_non_risky_compat_smoke.php"

echo P112P1_REMAINING_NON_RISKY_COMPAT_START

if not exist "%OPUS_ROOT%" goto opus_missing
if not exist "%PHP%" goto php_missing
if not exist "%SMOKE%" goto smoke_missing

"%PHP%" -d display_errors=1 "%SMOKE%" || goto smoke_failed

echo P112P1_REMAINING_NON_RISKY_COMPAT_OK
exit /b 0

:opus_missing
echo OPUS_ROOT_MISSING
exit /b 1

:php_missing
echo UWAMP_PHP_MISSING
exit /b 1

:smoke_missing
echo P112P1_SMOKE_MISSING
exit /b 1

:smoke_failed
echo P112P1_REMAINING_NON_RISKY_COMPAT_FAILED
exit /b 1
