@echo off
setlocal EnableExtensions
cd /d "%~dp0.."
php tools\automation\opus_lstsa_scheduler.php %*
exit /b %ERRORLEVEL%
