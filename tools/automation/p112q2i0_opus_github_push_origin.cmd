@echo off
setlocal EnableExtensions
cd /d H:\ASAP
set "OPUS_GITHUB_REMOTE_URL=https://github.com/philstephibanez-wq/Opus.git"
for /f "tokens=*" %%A in ('git rev-parse --abbrev-ref HEAD') do set "BRANCH=%%A"
if "%BRANCH%"=="" set "BRANCH=master"
git remote get-url origin >nul 2>nul
if errorlevel 1 git remote add origin "%OPUS_GITHUB_REMOTE_URL%"
git remote -v
git push -u origin "%BRANCH%"