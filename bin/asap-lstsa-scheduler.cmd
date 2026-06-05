@echo off
setlocal EnableExtensions
cd /d "%~dp0.."
php tools\automation\asap_lstsa_scheduler.php %*
exit /b %ERRORLEVEL%
