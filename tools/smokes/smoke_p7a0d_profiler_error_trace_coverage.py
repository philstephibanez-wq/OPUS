#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
P7A0D smoke: validate profiler error trace coverage in a generated OPUS site.

It verifies that profiler traces are written for:
- 404 route not found;
- 400 unavailable locale;
- 500 render failure.

The smoke creates a disposable generated site, starts PHP's built-in server,
performs HTTP requests with profiler enabled, verifies trace JSON, then cleans up.
"""

from __future__ import annotations

import json
from pathlib import Path
import shutil
import subprocess
import time
import urllib.error
import urllib.request


SITE_ID = "_p7a0d_error_trace_site"
PORT = "8799"


def run(args: list[str], timeout: int = 60) -> subprocess.CompletedProcess[str]:
    return subprocess.run(args, check=False, text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=timeout)


def remove_site(site_root: Path) -> None:
    if site_root.exists():
        shutil.rmtree(site_root)


def resolve_composer() -> str | None:
    return shutil.which("composer") or shutil.which("composer.bat") or shutil.which("composer.cmd")


def http_get(path: str) -> tuple[int, str]:
    url = f"http://127.0.0.1:{PORT}{path}"
    try:
        with urllib.request.urlopen(url, timeout=15) as response:
            return response.status, response.read().decode("utf-8", errors="replace")
    except urllib.error.HTTPError as exc:
        return exc.code, exc.read().decode("utf-8", errors="replace")


def clear_traces(profiler_dir: Path) -> None:
    if profiler_dir.exists():
        for trace in profiler_dir.glob("*.json"):
            trace.unlink()


def load_single_trace(profiler_dir: Path) -> dict:
    traces = sorted(profiler_dir.glob("*.json")) if profiler_dir.exists() else []
    if len(traces) != 1:
        raise RuntimeError(f"TRACE_FILE_COUNT_INVALID:{len(traces)}")

    data = json.loads(traces[0].read_text(encoding="utf-8"))
    if data.get("schema") != "OPUS_PROFILER_TRACE_V1":
        raise RuntimeError("TRACE_SCHEMA_INVALID")

    return data


def event_names(trace: dict) -> set[str]:
    events = trace.get("events")
    if not isinstance(events, list):
        raise RuntimeError("TRACE_EVENTS_INVALID")

    return {str(event.get("name")) for event in events if isinstance(event, dict)}


def assert_trace(trace: dict, expected_status: int, required_event: str) -> None:
    summary = trace.get("summary")
    if not isinstance(summary, dict) or summary.get("status") != expected_status:
        raise RuntimeError(f"TRACE_SUMMARY_STATUS_INVALID:{summary}")

    names = event_names(trace)
    if required_event not in names:
        raise RuntimeError(f"TRACE_REQUIRED_EVENT_MISSING:{required_event}")


def break_home_template(site_root: Path) -> None:
    routes_path = site_root / "application" / "config" / "routes.json"
    routes = json.loads(routes_path.read_text(encoding="utf-8"))

    for route in routes.get("routes", []):
        if isinstance(route, dict) and route.get("path") == "/":
            template = str(route.get("template", ""))
            template_path = site_root / template
            if not template_path.is_file():
                raise RuntimeError(f"HOME_TEMPLATE_NOT_FOUND:{template_path}")
            template_path.unlink()
            return

    raise RuntimeError("HOME_ROUTE_NOT_FOUND")


def main() -> int:
    root = Path.cwd()
    site_root = root / "sites" / SITE_ID
    profiler_dir = site_root / "var" / "profiler"

    print("P7A0D_PROFILER_ERROR_TRACE_COVERAGE_SMOKE")

    remove_site(site_root)

    composer = resolve_composer()
    if composer is None:
        print("CHECK_COMPOSER_EXECUTABLE=FAIL")
        return 1
    print("CHECK_COMPOSER_EXECUTABLE=OK")

    create = run([composer, "opus:create-site", "--", SITE_ID], timeout=120)
    if create.returncode != 0:
        print(create.stdout)
        print(create.stderr)
        print("CHECK_CREATE_SITE=FAIL")
        return 1
    print("CHECK_CREATE_SITE=OK")

    server = subprocess.Popen(
        ["php", "-S", f"127.0.0.1:{PORT}", "-t", str(site_root / "public")],
        cwd=str(root),
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
    )

    try:
        time.sleep(1.5)

        clear_traces(profiler_dir)
        status, body = http_get("/does-not-exist?profiler=1")
        if status != 404 or "OPUS_STARTER_ROUTE_NOT_FOUND" not in body:
            print(f"CHECK_404_RESPONSE=FAIL status={status}")
            return 1
        trace = load_single_trace(profiler_dir)
        assert_trace(trace, 404, "route.not_found")
        print("CHECK_404_TRACE=OK")

        clear_traces(profiler_dir)
        status, body = http_get("/?lang=zz&profiler=1")
        if status != 400 or "OPUS_STARTER_LOCALE_UNAVAILABLE" not in body:
            print(f"CHECK_400_RESPONSE=FAIL status={status}")
            return 1
        trace = load_single_trace(profiler_dir)
        assert_trace(trace, 400, "locale.unavailable")
        print("CHECK_400_TRACE=OK")

        clear_traces(profiler_dir)
        break_home_template(site_root)
        status, body = http_get("/?profiler=1")
        if status != 500 or "OPUS_STARTER_RENDER_FAILED" not in body:
            print(f"CHECK_500_RESPONSE=FAIL status={status}")
            return 1
        trace = load_single_trace(profiler_dir)
        assert_trace(trace, 500, "response.failed")
        print("CHECK_500_TRACE=OK")

        print("P7A0D_PROFILER_ERROR_TRACE_COVERAGE_SMOKE_OK")
        return 0
    except Exception as exc:
        print(f"CHECK_ERROR_TRACE_COVERAGE=FAIL {exc}")
        return 1
    finally:
        server.terminate()
        try:
            server.wait(timeout=5)
        except subprocess.TimeoutExpired:
            server.kill()
        remove_site(site_root)


if __name__ == "__main__":
    raise SystemExit(main())
