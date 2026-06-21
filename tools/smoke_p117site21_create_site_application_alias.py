from __future__ import annotations

import json
import shutil
import subprocess
import sys
from pathlib import Path


def resolve_composer_command() -> str:
    for candidate in ("composer.bat", "composer.cmd", "composer"):
        resolved = shutil.which(candidate)
        if resolved:
            return resolved
    raise RuntimeError("COMPOSER_EXECUTABLE_NOT_FOUND: composer.bat/composer.cmd/composer")


def run_command(command: list[str], cwd: Path) -> str:
    completed = subprocess.run(command, cwd=str(cwd), text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    if completed.returncode != 0:
        raise RuntimeError(f"COMMAND_FAILED({completed.returncode}): {' '.join(command)}\n{completed.stdout}")
    return completed.stdout


def require_path(path: Path, label: str) -> None:
    if not path.exists():
        raise RuntimeError(f"{label}_MISSING: {path.as_posix()}")


def forbid_path(path: Path, label: str) -> None:
    if path.exists():
        raise RuntimeError(f"{label}_FORBIDDEN: {path.as_posix()}")


def require_json(path: Path, label: str) -> dict:
    require_path(path, label)
    try:
        value = json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"{label}_INVALID_JSON: {path.as_posix()}:{exc.lineno}:{exc.colno}") from exc
    if not isinstance(value, dict):
        raise RuntimeError(f"{label}_NOT_OBJECT: {path.as_posix()}")
    return value


def cleanup(path: Path) -> None:
    if path.exists():
        shutil.rmtree(path)
    if path.exists():
        raise RuntimeError("CHECK_CLEANUP_FAILED")


def main() -> int:
    root = Path(__file__).resolve().parents[1]
    application_id = "p117site21-site-alias-smoke"
    target = root / "sites" / application_id
    composer_command = resolve_composer_command()

    cleanup(target)

    try:
        output = run_command([composer_command, "opus:create-site", "--", application_id, "--write"], root)
        if "OPUS_CREATE_APPLICATION_WRITTEN" not in output:
            raise RuntimeError("CHECK_CREATE_SITE_ALIAS_OUTPUT_MISSING")

        require_path(target / "frontend", "CHECK_ALIAS_FRONTEND_ROOT")
        require_path(target / "backend", "CHECK_ALIAS_BACKEND_ROOT")
        require_path(target / "frontend/views/home/home.view.json", "CHECK_ALIAS_HOME_VIEW")
        require_path(target / "frontend/layouts/public/public.layout.json", "CHECK_ALIAS_PUBLIC_LAYOUT")
        require_path(target / "frontend/sections/home-main/home-main.section.json", "CHECK_ALIAS_HOME_MAIN_SECTION")
        require_path(target / "backend/api-endpoints/home-viewmodel.endpoint.json", "CHECK_ALIAS_BACKEND_ENDPOINT")
        forbid_path(target / "application", "CHECK_ALIAS_LEGACY_APPLICATION_ROOT")

        application = require_json(target / "application.opus.json", "CHECK_ALIAS_APPLICATION_CONTRACT")
        if application.get("contract") != "OPUS_FULLSTACK_APPLICATION_V1":
            raise RuntimeError("CHECK_ALIAS_FULLSTACK_CONTRACT_INVALID")
        if application.get("frontend_root") != "frontend" or application.get("backend_root") != "backend":
            raise RuntimeError("CHECK_ALIAS_FRONT_BACK_ROOTS_INVALID")
        if application.get("backoffice_is_backend") is not False:
            raise RuntimeError("CHECK_ALIAS_BACKOFFICE_BACKEND_SEPARATION_INVALID")

        print("CHECK_CREATE_SITE_ALIAS_COMMAND=OK")
        print("CHECK_CREATE_SITE_ALIAS_FULLSTACK_STRUCTURE=OK")
        print("CHECK_CREATE_SITE_ALIAS_FRONTEND_BACKEND_SEPARATION=OK")
        print("CHECK_CREATE_SITE_ALIAS_NO_LEGACY_APPLICATION_ROOT=OK")
        print("P117SITE21_CREATE_SITE_APPLICATION_ALIAS_SMOKE_OK")
        return 0
    finally:
        cleanup(target)
        print("CHECK_CLEANUP=OK")


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:
        print(str(exc), file=sys.stderr)
        raise SystemExit(1)
