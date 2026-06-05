@echo off
setlocal EnableExtensions
cd /d "%~dp0.."
php tools\automation\asap_lstsa_runner.php %*
exit /b %ERRORLEVEL%
