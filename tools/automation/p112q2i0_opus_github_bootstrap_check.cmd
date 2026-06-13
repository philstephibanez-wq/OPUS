@echo off
setlocal EnableExtensions
cd /d H:\ASAP
if not exist ".git" echo P112Q2I0_FAIL_GIT_REPO_MISSING
if not exist "DOC\P112Q2I0_OPUS_GITHUB_BOOTSTRAP.md" echo P112Q2I0_FAIL_DOC_MISSING
if not exist "composer.json" echo P112Q2I0_FAIL_COMPOSER_MISSING
git status --short --branch
git remote -v