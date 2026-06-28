#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
P7A0C smoke: validate profiler traces in a generated OPUS site runtime.

It creates a disposable site, starts PHP's built-in server, requests the home route
with profiler enabled, verifies the JSON trace, then cleans up.
"""

from __future__ import annotations

import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import time
import urllib.error
import urllib.request


SITE_ID = "_p7a0c_profiler_site"
PORT = "8798"


def run(args: list[str], timeout: int = 60) -> subprocess.CompletedProcess[str]:
    return subprocess.run(args, check=False, text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=timeout)


def remove_site(site_root: Path) -> None:
    if site_root.exists():
        shutil.rmtree(site_root)


def main() -> int:
    root = Path.cwd()
    site_root = root / "sites" / SITE_ID
    profiler_dir = site_root / "var" / "profiler"

    print("P7A0C_PROFILER_IN_GENERATED_SITE_RUNTIME_SMOKE")

    remove_site(site_root)

    composer = shutil.which("composer") or shutil.which("composer.bat") or shutil.which("composer.cmd")
    if composer is None:
        print("CHECK_COMPOSER_EXECUTABLE=FAIL")
        return 1
    print("CHECK_COMPOSER_EXECUTABLE=OK")

    create = run([composer, "opus:create-site", "--", SITE_ID], timeout=120)
    if create.returncode != 0:
        print(create.stdout)
        print(create.stderr, file=sys.stderr)
        print("CHECK_CREATE_SITE=FAIL")
        return 1
    print("CHECK_CREATE_SITE=OK")

    lint = run(["php", "-l", str(site_root / "public" / "index.php")])
    if lint.returncode != 0:
        print(lint.stdout)
        print(lint.stderr, file=sys.stderr)
        print("CHECK_GENERATED_INDEX_LINT=FAIL")
        remove_site(site_root)
        return 1
    print("CHECK_GENERATED_INDEX_LINT=OK")

    server = subprocess.Popen(
        ["php", "-S", f"127.0.0.1:{PORT}", "-t", str(site_root / "public")],
        cwd=str(root),
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
    )

    try:
        time.sleep(1.5)
        url = f"http://127.0.0.1:{PORT}/?profiler=1"
        try:
            with urllib.request.urlopen(url, timeout=15) as response:
                body = response.read().decode("utf-8", errors="replace")
                status = response.status
        except urllib.error.HTTPError as exc:
            print(f"CHECK_HTTP_HOME=FAIL status={exc.code}")
            return 1

        if status != 200 or body.strip() == "":
            print(f"CHECK_HTTP_HOME=FAIL status={status}")
            return 1
        print("CHECK_HTTP_HOME=OK")

        traces = sorted(profiler_dir.glob("*.json")) if profiler_dir.exists() else []
        if len(traces) != 1:
            print(f"CHECK_TRACE_FILE_COUNT=FAIL count={len(traces)}")
            return 1
        print("CHECK_TRACE_FILE_COUNT=OK")

        data = json.loads(traces[0].read_text(encoding="utf-8"))
        if data.get("schema") != "OPUS_PROFILER_TRACE_V1":
            print("CHECK_TRACE_SCHEMA=FAIL")
            return 1
        print("CHECK_TRACE_SCHEMA=OK")

        events = data.get("events")
        if not isinstance(events, list):
            print("CHECK_TRACE_EVENTS=FAIL")
            return 1

        names = {str(event.get("name")) for event in events if isinstance(event, dict)}
        required = {
            "request.received",
            "config.loaded",
            "route.matched",
            "locale.selected",
            "dictionary.loaded",
            "page_template.rendered",
            "layout.rendered",
            "trace.stopped",
        }
        missing = sorted(required - names)
        if missing:
            print("CHECK_TRACE_REQUIRED_EVENTS=FAIL missing=" + ",".join(missing))
            return 1
        print("CHECK_TRACE_REQUIRED_EVENTS=OK")

        summary = data.get("summary", {})
        if summary.get("status") != 200:
            print("CHECK_TRACE_SUMMARY=FAIL")
            return 1
        print("CHECK_TRACE_SUMMARY=OK")

        print("P7A0C_PROFILER_IN_GENERATED_SITE_RUNTIME_SMOKE_OK")
        return 0
    finally:
        server.terminate()
        try:
            server.wait(timeout=5)
        except subprocess.TimeoutExpired:
            server.kill()
        remove_site(site_root)


if __name__ == "__main__":
    raise SystemExit(main())
