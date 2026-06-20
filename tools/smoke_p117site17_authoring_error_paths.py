from __future__ import annotations

import json
import shutil
import subprocess
from pathlib import Path

OPUS_ROOT = Path(r"H:\OPUS")
SITE = "skeleton"
SITE_ROOT = OPUS_ROOT / "sites" / SITE


def run_cmd(args: list[str], cwd: Path, expected_returncode: int = 0) -> str:
    print("RUN=" + " ".join(args))
    completed = subprocess.run(
        args,
        cwd=str(cwd),
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
    )
    output = completed.stdout or ""
    if output:
        print(output, end="" if output.endswith("\n") else "\n")
    if completed.returncode != expected_returncode:
        raise RuntimeError(
            f"COMMAND_FAILED[{completed.returncode}] expected={expected_returncode}: {' '.join(args)}"
        )
    return output


def composer(*args: str, expected_returncode: int = 0) -> str:
    return run_cmd(["cmd", "/c", "composer", *args], OPUS_ROOT, expected_returncode)


def cleanup_site() -> None:
    if SITE_ROOT.exists():
        shutil.rmtree(SITE_ROOT)


def require_contains(name: str, haystack: str, needle: str) -> None:
    if needle not in haystack:
        print(f"{name}=FAIL: {needle}")
        raise RuntimeError(f"{name}=FAIL")
    print(f"{name}=OK")


def require_not_exists(name: str, path: Path) -> None:
    if path.exists():
        print(f"{name}=FAIL: {path}")
        raise RuntimeError(f"{name}=FAIL")
    print(f"{name}=OK")


def require_no_route_path(name: str, route_path: str) -> None:
    routes_file = SITE_ROOT / "application" / "config" / "routes.json"
    data = json.loads(routes_file.read_text(encoding="utf-8"))
    for route in data.get("routes", []):
        if isinstance(route, dict) and route.get("path") == route_path:
            print(f"{name}=FAIL: {route_path}")
            raise RuntimeError(f"{name}=FAIL")
    print(f"{name}=OK")


def require_no_module_registered(name: str, module_id: str) -> None:
    modules_file = SITE_ROOT / "application" / "config" / "modules.json"
    data = json.loads(modules_file.read_text(encoding="utf-8"))
    for module in data.get("modules", []):
        if isinstance(module, dict) and module.get("id") == module_id:
            print(f"{name}=FAIL: {module_id}")
            raise RuntimeError(f"{name}=FAIL")
    print(f"{name}=OK")


def main() -> int:
    print("P117SITE17_AUTHORING_ERROR_PATHS_SMOKE_START")
    cleanup_site()
    try:
        composer("dump-autoload")
        composer("opus:create-site", "--", SITE, "--write")
        composer("opus:create-module", "--", SITE, "Blog", "--title", "Blog", "--write")
        composer("opus:create-page", "--", SITE, "Blog", "archive", "/blog/archive", "--title", "Blog archive", "--write")
        composer("opus:create-rubric", "--", SITE, "News", "/news", "--title", "News", "--write")

        out = composer("opus:create-module", "--", SITE, "Blog", "--title", "Blog", "--write", expected_returncode=20)
        require_contains("CHECK_DUPLICATE_MODULE_ERROR", out, "OPUS_CREATE_MODULE_ALREADY_EXISTS")

        out = composer("opus:create-page", "--", SITE, "Blog", "archive", "/blog/archive-2", "--title", "Duplicate archive", "--write", expected_returncode=20)
        require_contains("CHECK_DUPLICATE_PAGE_ERROR", out, "OPUS_CREATE_PAGE_TEMPLATE_ALREADY_EXISTS")
        require_no_route_path("CHECK_DUPLICATE_PAGE_NO_ROUTE", "/blog/archive-2")

        out = composer("opus:create-page", "--", SITE, "Blog", "other", "/blog/archive", "--title", "Other", "--write", expected_returncode=20)
        require_contains("CHECK_DUPLICATE_PAGE_ROUTE_PATH_ERROR", out, "OPUS_SITE_COMMAND_ROUTE_PATH_ALREADY_EXISTS")
        require_not_exists("CHECK_DUPLICATE_PAGE_ROUTE_PATH_NO_TEMPLATE", SITE_ROOT / "application" / "modules" / "Blog" / "templates" / "pages" / "other.score")

        out = composer("opus:create-rubric", "--", SITE, "Events", "/news", "--title", "Events", "--write", expected_returncode=20)
        require_contains("CHECK_DUPLICATE_RUBRIC_ROUTE_PATH_ERROR", out, "OPUS_SITE_COMMAND_ROUTE_PATH_ALREADY_EXISTS")
        require_not_exists("CHECK_DUPLICATE_RUBRIC_ROUTE_PATH_NO_MODULE_DIR", SITE_ROOT / "application" / "modules" / "Events")
        require_no_module_registered("CHECK_DUPLICATE_RUBRIC_ROUTE_PATH_NO_MODULE_REGISTER", "Events")

        out = composer("opus:create-module", "--", SITE, "1Blog", "--title", "Invalid", "--write", expected_returncode=20)
        require_contains("CHECK_INVALID_MODULE_ID_ERROR", out, "OPUS_CREATE_MODULE_INVALID_MODULE_ID")
        require_not_exists("CHECK_INVALID_MODULE_ID_NO_DIR", SITE_ROOT / "application" / "modules" / "1Blog")
        require_no_module_registered("CHECK_INVALID_MODULE_ID_NO_REGISTER", "1Blog")

        out = composer("opus:create-page", "--", SITE, "Blog", "BadPage", "/blog/bad-page", "--title", "Bad page", "--write", expected_returncode=20)
        require_contains("CHECK_INVALID_PAGE_ID_ERROR", out, "OPUS_CREATE_PAGE_INVALID_PAGE_ID")
        require_not_exists("CHECK_INVALID_PAGE_ID_NO_TEMPLATE", SITE_ROOT / "application" / "modules" / "Blog" / "templates" / "pages" / "BadPage.score")
        require_no_route_path("CHECK_INVALID_PAGE_ID_NO_ROUTE", "/blog/bad-page")

        out = composer("opus:create-page", "--", SITE, "Blog", "bad-route", "blog/bad-route", "--title", "Bad route", "--write", expected_returncode=20)
        require_contains("CHECK_INVALID_ROUTE_PATH_ERROR", out, "OPUS_CREATE_PAGE_INVALID_ROUTE_PATH")
        require_not_exists("CHECK_INVALID_ROUTE_PATH_NO_TEMPLATE", SITE_ROOT / "application" / "modules" / "Blog" / "templates" / "pages" / "bad-route.score")
        require_no_route_path("CHECK_INVALID_ROUTE_PATH_NO_ROUTE", "blog/bad-route")

        out = composer("opus:create-module", "--", SITE, "Draft", "--title", "Draft", expected_returncode=20)
        require_contains("CHECK_MISSING_WRITE_ERROR", out, "OPUS_SITE_COMMAND_WRITE_FLAG_REQUIRED")
        require_not_exists("CHECK_MISSING_WRITE_NO_DIR", SITE_ROOT / "application" / "modules" / "Draft")
        require_no_module_registered("CHECK_MISSING_WRITE_NO_REGISTER", "Draft")

        composer("opus:validate-site", "--", SITE)
        print("CHECK_VALIDATE_AFTER_ERROR_PATHS=OK")
        print("P117SITE17_AUTHORING_ERROR_PATHS_SMOKE_OK")
        return 0
    finally:
        cleanup_site()
        print("CHECK_CLEANUP=OK")


if __name__ == "__main__":
    raise SystemExit(main())
