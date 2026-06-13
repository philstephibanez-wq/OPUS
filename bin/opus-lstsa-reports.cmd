@echo off
setlocal EnableExtensions
cd /d "%~dp0.."
php tools\automation\opus_lstsa_reports.php %*
