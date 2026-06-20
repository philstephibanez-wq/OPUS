#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""P117SITE16 smoke - create module/page/rubric commands."""
from __future__ import annotations

import os
import shutil
import subprocess
import sys
from pathlib import Path

OPUS_ROOT = Path(os.environ.get("OPUS_ROOT", r"H:\OPUS"))
SITE_ID = "skeleton"


def run_cmd(args: list[str]) -> str:
    print("RUN=" + " ".join(args))
    completed = subprocess.run(
        args,
        cwd=str(OPUS_ROOT),
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        shell=False,
    )
    print(completed.stdout, end="")
    if completed.returncode != 0:
        raise RuntimeError(f"COMMAND_FAILED[{completed.returncode}]: {' '.join(args)}")
    return completed.stdout


def cleanup(site_root: Path) -> None:
    if site_root.exists():
        shutil.rmtree(site_root)


def contains(output: str, needle: str, marker: str) -> bool:
    if needle not in output:
        print(marker + "=FAIL: " + needle)
        return False
    print(marker + "=OK")
    return True


def main() -> int:
    print("P117SITE16_CREATE_PAGE_MODULE_RUBRIC_COMMANDS_SMOKE_START")
    site_root = OPUS_ROOT / "sites" / SITE_ID
    try:
        cleanup(site_root)
        run_cmd(["cmd", "/c", "composer", "dump-autoload"])
        run_cmd(["cmd", "/c", "composer", "opus:create-site", "--", SITE_ID, "--write"])
        run_cmd(["cmd", "/c", "composer", "opus:validate-site", "--", SITE_ID])

        module_out = run_cmd(["cmd", "/c", "composer", "opus:create-module", "--", SITE_ID, "Blog", "--title", "Blog", "--write"])
        if not contains(module_out, "OPUS_CREATE_MODULE_WRITTEN: skeleton/Blog", "CHECK_CREATE_MODULE_COMMAND"):
            return 1

        page_out = run_cmd(["cmd", "/c", "composer", "opus:create-page", "--", SITE_ID, "Blog", "archive", "/blog/archive", "--title", "Blog archive", "--write"])
        if not contains(page_out, "OPUS_CREATE_PAGE_WRITTEN: skeleton/Blog/archive /blog/archive", "CHECK_CREATE_PAGE_COMMAND"):
            return 1

        rubric_out = run_cmd(["cmd", "/c", "composer", "opus:create-rubric", "--", SITE_ID, "News", "/news", "--title", "News", "--write"])
        if not contains(rubric_out, "OPUS_CREATE_RUBRIC_WRITTEN: skeleton/News /news", "CHECK_CREATE_RUBRIC_COMMAND"):
            return 1

        validate_out = run_cmd(["cmd", "/c", "composer", "opus:validate-site", "--", SITE_ID])
        if not contains(validate_out, "OPUS_VALIDATE_SITE_OK: skeleton", "CHECK_VALIDATE_AFTER_WRITES"):
            return 1

        routes_output = run_cmd(["cmd", "/c", "composer", "opus:list-routes", "--", SITE_ID])
        modules_output = run_cmd(["cmd", "/c", "composer", "opus:list-modules", "--", SITE_ID])

        if not contains(routes_output, "[ROUTE] blog.archive /blog/archive -> Blog :: application/modules/Blog/templates/pages/archive.score", "CHECK_LIST_ROUTES_AFTER_WRITES"):
            return 1
        if not contains(routes_output, "[ROUTE] news.index /news -> News :: application/modules/News/templates/pages/index.score", "CHECK_LIST_ROUTES_RUBRIC_AFTER_WRITES"):
            return 1
        if not contains(modules_output, "[MODULE] Blog enabled=yes root=application/modules/Blog", "CHECK_LIST_MODULES_AFTER_WRITES"):
            return 1
        if not contains(modules_output, "[MODULE] News enabled=yes root=application/modules/News", "CHECK_LIST_MODULES_RUBRIC_AFTER_WRITES"):
            return 1

        print("P117SITE16_CREATE_PAGE_MODULE_RUBRIC_COMMANDS_SMOKE_OK")
        return 0
    except Exception as exc:
        print("P117SITE16_UNEXPECTED_ERROR=" + repr(exc))
        return 1
    finally:
        cleanup(site_root)
        if site_root.exists():
            print("CHECK_CLEANUP=FAIL")
        else:
            print("CHECK_CLEANUP=OK")


if __name__ == "__main__":
    sys.exit(main())
