@echo off
setlocal
cd /d "%~dp0..\.."
php tools\smoke\p113d3b_asap_refbook_doc_assets_live_smoke.php
exit /b %ERRORLEVEL%
