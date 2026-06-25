#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""P7A0H smoke: Runtime/Application diagnostics/profiler wiring contract."""

from pathlib import Path
import subprocess
import sys

REQUIRED_MARKERS = [
    "private $_runtimeProfiler = null;",
    "private $_runtimeProfilerTraceId = null;",
    "private function _initDiagnosticsProfiler($debug): void",
    r"\Opus\Diagnostics\Diagnostics::configureProfiler($this->_runtimeProfiler)",
    r"$this->_runtimeProfiler = new \Opus\Profiler\Profiler($this->_resolveRuntimeProfilerDir());",
    "$this->_runtimeProfiler->start($this->_runtimeProfilerTraceId);",
    "$this->_runtimeProfiler->stop($summary);",
    "diagnostics.profiler.started",
    "dispatch.start",
    "dispatch.end",
    "dispatch.404",
    "process_url.controller.path",
    "OPUS_PROFILER",
    "OPUS_DIAGNOSTICS",
    "OPUS_TRACE_ID",
]

def main() -> int:
    print("P7A0H_RUNTIME_DIAGNOSTICS_PROFILER_WIRING_SMOKE")

    app = Path("Opus/Runtime/Application.php")
    if not app.exists():
        print("CHECK_APPLICATION_EXISTS=FAIL")
        return 1

    text = app.read_text(encoding="utf-8", errors="replace")
    missing = [marker for marker in REQUIRED_MARKERS if marker not in text]
    if missing:
        print("CHECK_RUNTIME_WIRING_MARKERS=FAIL")
        for marker in missing:
            print(marker)
        return 1
    print("CHECK_RUNTIME_WIRING_MARKERS=OK")

    for target in [
        "Opus/Runtime/Application.php",
        "Opus/Diagnostics/Diagnostics.php",
        "Opus/Profiler/Profiler.php",
        "Opus/Profiler/Trace.php",
    ]:
        completed = subprocess.run(["php", "-l", target], check=False, text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        if completed.returncode != 0:
            print(completed.stdout)
            print(completed.stderr, file=sys.stderr)
            print(f"CHECK_LINT=FAIL {target}")
            return 1
    print("CHECK_LINT=OK")

    for command in [
        ["python", "tools/smokes/smoke_p7a0fg_delete_legacy_debug_class.py"],
        ["php", "tools/smokes/smoke_p7a0fg_diagnostics_replaces_debug.php"],
    ]:
        completed = subprocess.run(command, check=False, text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        if completed.returncode != 0:
            print(completed.stdout)
            print(completed.stderr, file=sys.stderr)
            print("CHECK_BASE_SMOKES=FAIL")
            return 1
    print("CHECK_BASE_SMOKES=OK")
    print("P7A0H_RUNTIME_DIAGNOSTICS_PROFILER_WIRING_SMOKE_OK")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
