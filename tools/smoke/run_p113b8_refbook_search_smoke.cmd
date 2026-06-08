@echo off
setlocal
cd /d "%~dp0\..\.."
php tools\smoke\p113b8_refbook_search_smoke.php
endlocal
