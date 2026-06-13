@echo off
setlocal EnableExtensions

set "OPUS_ROOT=H:\ASAP"
set "PHP=H:\UwAmp\bin\php\php-8.5.6\php.exe"
set "AUDIT=%OPUS_ROOT%\tools\audit\opus_root_orphan_framework_audit.php"

echo P112Q2F_ROOT_ORPHAN_FRAMEWORK_AUDIT_START

if not exist "%OPUS_ROOT%" goto opus_missing
if not exist "%PHP%" goto php_missing
if not exist "%AUDIT%" goto audit_missing

"%PHP%" -d display_errors=1 "%AUDIT%" || goto audit_failed

echo P112Q2F_ROOT_ORPHAN_FRAMEWORK_AUDIT_OK
exit /b 0

:opus_missing
echo OPUS_ROOT_MISSING
exit /b 1

:php_missing
echo UWAMP_PHP_MISSING
exit /b 1

:audit_missing
echo P112Q2F_AUDIT_SCRIPT_MISSING
exit /b 1

:audit_failed
echo P112Q2F_ROOT_ORPHAN_FRAMEWORK_AUDIT_FAILED
exit /b 1
