@echo off
setlocal
cd /d "%~dp0\..\.."
php tools\i18n\p114d3_refbook_doc_i18n_missing_extractor.php
exit /b %ERRORLEVEL%
