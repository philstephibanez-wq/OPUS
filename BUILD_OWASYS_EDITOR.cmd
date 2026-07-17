@echo off
setlocal
cd /d "%~dp0"

echo OWASYS_EDITOR_BUILD_START

where node >nul 2>&1
if errorlevel 1 (
  echo OWASYS_EDITOR_BUILD_NODE_NOT_FOUND
  endlocal & exit /b 1
)

where npm >nul 2>&1
if errorlevel 1 (
  echo OWASYS_EDITOR_BUILD_NPM_NOT_FOUND
  endlocal & exit /b 1
)

for /f "delims=" %%V in ('node --version') do echo OWASYS_EDITOR_BUILD_NODE_VERSION=%%V
for /f "delims=" %%V in ('npm --version') do echo OWASYS_EDITOR_BUILD_NPM_VERSION=%%V

if not exist "sites\owasys\www\asset\vendor\codemirror" mkdir "sites\owasys\www\asset\vendor\codemirror"
if errorlevel 1 (
  echo OWASYS_EDITOR_BUILD_TARGET_DIRECTORY_FAILED
  endlocal & exit /b 1
)

echo OWASYS_EDITOR_BUILD_INSTALLING_DEPENDENCIES
call npm install
if errorlevel 1 goto :fail

echo OWASYS_EDITOR_BUILD_BUNDLING
call npm run build:owasys-editor
if errorlevel 1 goto :fail

if not exist "sites\owasys\www\asset\vendor\codemirror\owasys-codemirror.js" (
  echo OWASYS_EDITOR_BUILD_BUNDLE_NOT_CREATED
  goto :fail
)

echo OWASYS_EDITOR_BUILD_SMOKE
php tools\smoke_owasys_codemirror.php
if errorlevel 1 goto :fail

if exist node_modules rmdir /s /q node_modules

echo OWASYS_EDITOR_BUILD_OK
endlocal
exit /b 0

:fail
set "OWASYS_EDITOR_BUILD_ERROR=%ERRORLEVEL%"
if "%OWASYS_EDITOR_BUILD_ERROR%"=="0" set "OWASYS_EDITOR_BUILD_ERROR=1"
if exist node_modules rmdir /s /q node_modules
echo OWASYS_EDITOR_BUILD_FAILED=%OWASYS_EDITOR_BUILD_ERROR%
endlocal & exit /b %OWASYS_EDITOR_BUILD_ERROR%
