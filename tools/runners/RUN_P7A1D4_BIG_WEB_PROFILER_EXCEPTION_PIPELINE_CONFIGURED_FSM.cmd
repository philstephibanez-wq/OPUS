@echo off
setlocal
cd /d "%~dp0..\.."
php "tools/audits/run_p7a1d4_big_web_profiler_exception_pipeline_configured_fsm.php"
set EC=%ERRORLEVEL%
echo P7A1D4_BIG_WEB_PROFILER_EXCEPTION_PIPELINE_CONFIGURED_FSM_EXIT_CODE=%EC%
exit /b %EC%
