@echo off
setlocal
cd /d "%~dp0\..\.."
php tools\smoke\p114d3_refbook_doc_i18n_missing_extractor_smoke.php
exit /b %ERRORLEVEL%
