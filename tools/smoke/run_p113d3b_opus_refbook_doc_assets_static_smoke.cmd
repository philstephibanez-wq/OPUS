@echo off
setlocal
cd /d "%~dp0..\.."
php tools\smoke\p113d3b_opus_refbook_doc_assets_static_smoke.php
exit /b %ERRORLEVEL%
