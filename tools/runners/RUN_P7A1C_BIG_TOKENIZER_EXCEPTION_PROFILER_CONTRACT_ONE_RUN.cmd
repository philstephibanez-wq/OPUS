@echo off
setlocal
cd /d "%~dp0..\.."
php tools\audits\run_p7a1c_big_tokenizer_exception_profiler_contract.php
set EXIT_CODE=%ERRORLEVEL%
echo P7A1C_BIG_TOKENIZER_EXCEPTION_PROFILER_CONTRACT_ONE_RUN_EXIT_CODE=%EXIT_CODE%
exit /b %EXIT_CODE%
