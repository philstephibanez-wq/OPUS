@echo off
setlocal EnableExtensions
cd /d "%%~dp0"
php -S 127.0.0.1:8078 -t "%%~dp0sites\opus-p7-ops\public" "%%~dp0sites\opus-p7-ops\public\router.php"
