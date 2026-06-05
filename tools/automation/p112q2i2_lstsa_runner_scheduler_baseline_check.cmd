@echo off
setlocal EnableExtensions
cd /d H:\ASAP
if errorlevel 1 exit /b 1
php tools\automation\p112q2i2_lstsa_runner_scheduler_baseline_recipe.php
exit /b %ERRORLEVEL%
