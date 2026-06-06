@echo off
setlocal EnableExtensions
cd /d H:\ASAP
if errorlevel 1 exit /b 1
php tools\automation\p112q2i3_lstsa_batch_checkpoint_executor_recipe.php
exit /b %ERRORLEVEL%
