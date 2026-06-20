#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
P117SITE15_LIST_ROUTES_MODULES_SMOKE

Verifies Composer inspection commands for generated OPUS sites:
- opus:list-routes
- opus:list-modules

The smoke generates `sites/skeleton`, validates it, checks command output, and
removes the generated site at the end.
"""
from __future__ import annotations

import shutil
import subprocess
from pathlib import Path

OPUS_ROOT = Path(r"H:\OPUS")
SITE = "skeleton"
SITE_ROOT = OPUS_ROOT / "sites" / SITE


def run_cmd(args: list[str], cwd: Path) -> str:
    printable = " ".join(args)
    print(f"RUN={printable}")
    completed = subprocess.run(
        args,
        cwd=str(cwd),
        text=True,
        encoding="utf-8",
        errors="replace",
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
    )
    if completed.stdout:
        print(completed.stdout.rstrip())
    if completed.returncode != 0:
        raise RuntimeError(f"COMMAND_FAILED[{completed.returncode}]: {printable}")
    return completed.stdout or ""


def remove_site() -> None:
    if SITE_ROOT.exists():
        shutil.rmtree(SITE_ROOT)


def require_contains(label: str, text: str, expected: str) -> None:
    if expected not in text:
        print(f"{label}=FAIL: {expected}")
        raise SystemExit(1)
    print(f"{label}=OK")


def main() -> int:
    print("P117SITE15_LIST_ROUTES_MODULES_SMOKE_START")
    exit_code = 0
    try:
        remove_site()
        run_cmd(["cmd", "/c", "composer", "dump-autoload"], OPUS_ROOT)
        run_cmd(["cmd", "/c", "composer", "opus:create-site", "--", SITE, "--write"], OPUS_ROOT)
        run_cmd(["cmd", "/c", "composer", "opus:validate-site", "--", SITE], OPUS_ROOT)

        routes = run_cmd(["cmd", "/c", "composer", "opus:list-routes", "--", SITE], OPUS_ROOT)
        modules = run_cmd(["cmd", "/c", "composer", "opus:list-modules", "--", SITE], OPUS_ROOT)

        require_contains("CHECK_LIST_ROUTES_HEADER", routes, "OPUS_LIST_ROUTES: skeleton")
        require_contains("CHECK_LIST_ROUTES_HOME", routes, "[ROUTE] home.index / -> Home :: application/modules/Home/templates/pages/index.score")
        require_contains("CHECK_LIST_ROUTES_PAGES", routes, "[ROUTE] pages.index /pages -> Pages :: application/modules/Pages/templates/pages/index.score")
        require_contains("CHECK_LIST_ROUTES_ARTICLES", routes, "[ROUTE] articles.index /articles -> Articles :: application/modules/Articles/templates/pages/index.score")
        require_contains("CHECK_LIST_ROUTES_RUBRIQUES", routes, "[ROUTE] rubriques.index /rubriques -> Rubriques :: application/modules/Rubriques/templates/pages/index.score")
        require_contains("CHECK_LIST_ROUTES_DOCUMENTATION", routes, "[ROUTE] documentation.index /documentation -> Documentation :: application/modules/Documentation/templates/pages/index.score")

        require_contains("CHECK_LIST_MODULES_HEADER", modules, "OPUS_LIST_MODULES: skeleton")
        require_contains("CHECK_LIST_MODULES_HOME", modules, "[MODULE] Home enabled=yes root=application/modules/Home default_template=application/modules/Home/templates/pages/index.score")
        require_contains("CHECK_LIST_MODULES_PAGES", modules, "[MODULE] Pages enabled=yes root=application/modules/Pages default_template=application/modules/Pages/templates/pages/index.score")
        require_contains("CHECK_LIST_MODULES_ARTICLES", modules, "[MODULE] Articles enabled=yes root=application/modules/Articles default_template=application/modules/Articles/templates/pages/index.score")
        require_contains("CHECK_LIST_MODULES_RUBRIQUES", modules, "[MODULE] Rubriques enabled=yes root=application/modules/Rubriques default_template=application/modules/Rubriques/templates/pages/index.score")
        require_contains("CHECK_LIST_MODULES_DOCUMENTATION", modules, "[MODULE] Documentation enabled=yes root=application/modules/Documentation default_template=application/modules/Documentation/templates/pages/index.score")

        print("P117SITE15_LIST_ROUTES_MODULES_SMOKE_OK")
    except Exception as exc:
        exit_code = 1
        print(f"P117SITE15_LIST_ROUTES_MODULES_SMOKE_ERROR={exc!r}")
    finally:
        remove_site()
        if not SITE_ROOT.exists():
            print("CHECK_CLEANUP=OK")
        else:
            print("CHECK_CLEANUP=FAIL")
            exit_code = 1
    return exit_code


if __name__ == "__main__":
    raise SystemExit(main())
