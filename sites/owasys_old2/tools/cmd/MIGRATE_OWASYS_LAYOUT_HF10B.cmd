@echo off
setlocal EnableExtensions EnableDelayedExpansion
for %%I in ("%~dp0..\..\..\..") do set "OPUS_ROOT=%%~fI"
set "APP=%OPUS_ROOT%\sites\owasys\application"
if not exist "%OPUS_ROOT%\composer.json" goto :root_error
if not exist "%APP%\default" goto :source_error
if not exist "%APP%\api" goto :source_error
if not exist "%APP%\shared\i18n\default" mkdir "%APP%\shared\i18n\default"
if not exist "%APP%\shared\i18n\modules" mkdir "%APP%\shared\i18n\modules"
if not exist "%APP%\front\default" mkdir "%APP%\front\default"
if not exist "%APP%\front\modules" mkdir "%APP%\front\modules"
if not exist "%APP%\back\modules" mkdir "%APP%\back\modules"
if not exist "%APP%\back\api" mkdir "%APP%\back\api"
robocopy "%APP%\default" "%APP%\front\default" /E /XC /XN /XO /XD local /XF Application.php bootstrap.php ScorePageRenderer.php RuntimeController.php /COPY:DAT /DCOPY:DAT /R:1 /W:1 /NFL /NDL /NJH /NJS /NP
if errorlevel 8 goto :copy_error
robocopy "%APP%\default\local" "%APP%\shared\i18n\default" /E /XC /XN /XO /COPY:DAT /DCOPY:DAT /R:1 /W:1 /NFL /NDL /NJH /NJS /NP
if errorlevel 8 goto :copy_error
for /d %%D in ("%APP%\*") do (
  set "NAME=%%~nxD"
  if /I not "!NAME!"=="default" if /I not "!NAME!"=="shared" if /I not "!NAME!"=="front" if /I not "!NAME!"=="back" if /I not "!NAME!"=="api" (
    robocopy "%%~fD" "%APP%\front\modules\!NAME!" /E /XC /XN /XO /XD local /XF ApplicationSingletonInspector.php /COPY:DAT /DCOPY:DAT /R:1 /W:1 /NFL /NDL /NJH /NJS /NP
    if errorlevel 8 goto :copy_error
    if exist "%%~fD\local" (
      robocopy "%%~fD\local" "%APP%\shared\i18n\modules\!NAME!" /E /XC /XN /XO /COPY:DAT /DCOPY:DAT /R:1 /W:1 /NFL /NDL /NJH /NJS /NP
      if errorlevel 8 goto :copy_error
    )
  )
)
robocopy "%APP%\api" "%APP%\back\api" /E /XC /XN /XO /XF BackendApiController.php /COPY:DAT /DCOPY:DAT /R:1 /W:1 /NFL /NDL /NJH /NJS /NP
if errorlevel 8 goto :copy_error
if not exist "%APP%\shared\Application.php" goto :target_error
if not exist "%APP%\shared\i18n\default\fr.json" goto :target_error
if not exist "%APP%\front\bootstrap.php" goto :target_error
if not exist "%APP%\front\default\controllers\RuntimeController.php" goto :target_error
if not exist "%APP%\front\modules\registry\controllers\RegistryController.php" goto :target_error
if not exist "%APP%\front\modules\creation\controllers\CreationController.php" goto :target_error
if not exist "%APP%\back\bootstrap.php" goto :target_error
if not exist "%APP%\back\api\controllers\BackendApiController.php" goto :target_error
endlocal
exit /b 0
:root_error
endlocal
exit /b 10
:source_error
endlocal
exit /b 11
:copy_error
endlocal
exit /b 20
:target_error
endlocal
exit /b 30
