@echo off
setlocal
cd /d "%~dp0"
echo == P4R_ROOT_BOUNDARY_AUDIT ==
python tools\audits\audit_p4r_root_boundary.py
if errorlevel 1 (
  echo P4R_ROOT_BOUNDARY_AUDIT_FAILED
  goto :eof
)
echo P4R_ROOT_BOUNDARY_AUDIT_DONE
