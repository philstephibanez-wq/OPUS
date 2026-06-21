from __future__ import annotations

import json
import os
import shutil
import subprocess
import sys
from pathlib import Path


def composer_command() -> list[str]:
    if os.name == "nt" and shutil.which("composer"):
        return ["cmd", "/d", "/c", "composer"]
    resolved = shutil.which("composer")
    if resolved:
        return [resolved]
    raise RuntimeError("COMPOSER_EXECUTABLE_NOT_FOUND")


def run(command: list[str], cwd: Path) -> str:
    completed = subprocess.run(command, cwd=str(cwd), text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    if completed.returncode != 0:
        raise RuntimeError(f"COMMAND_FAILED({completed.returncode}): {' '.join(command)}\n{completed.stdout}")
    return completed.stdout


def need(path: Path, label: str) -> None:
    if not path.exists():
        raise RuntimeError(f"{label}_MISSING: {path.as_posix()}")


def forbid(path: Path, label: str) -> None:
    if path.exists():
        raise RuntimeError(f"{label}_FORBIDDEN: {path.as_posix()}")


def load_json(path: Path, label: str) -> dict:
    need(path, label)
    value = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(value, dict):
        raise RuntimeError(f"{label}_NOT_OBJECT")
    return value


def main() -> int:
    root = Path(__file__).resolve().parents[1]
    app_id = "p117site20-smoke"
    target = root / "sites" / app_id
    if target.exists():
        shutil.rmtree(target)
    try:
        output = run([*composer_command(), "opus:create-application", "--", app_id, "--write"], root)
        if "OPUS_CREATE_APPLICATION_WRITTEN" not in output:
            raise RuntimeError("CREATE_APPLICATION_OUTPUT_MISSING")

        required = [
            "frontend/views/home/home.view.json",
            "frontend/views/architecture/architecture.view.json",
            "frontend/views/catalog-index/catalog-index.view.json",
            "frontend/layouts/public/public.layout.json",
            "frontend/layouts/backoffice/backoffice.layout.json",
            "frontend/sections/rich-hero/rich-hero.section.score",
            "frontend/sections/catalog-grid/catalog-grid.section.score",
            "middle/routes/routes.json",
            "middle/api/catalog.list.contract.json",
            "middle/security/security.pipeline.json",
            "backend/modules/catalog/catalog.items.json",
            "backend/api-endpoints/catalog-list.endpoint.json",
            "backend/viewmodels/home.viewmodel.json",
            "resources/i18n/fr.json",
            "resources/i18n/en.json",
            "resources/i18n/es.json",
            "public/index.php",
            "public/catalog/module-catalog/index.php",
            "public/api/catalog/index.php",
        ]
        for relative in required:
            need(target / relative, f"CHECK_{relative.replace('/', '_').replace('.', '_').upper()}")

        app = load_json(target / "application.opus.json", "CHECK_APPLICATION_CONTRACT")
        if app.get("contract") != "OPUS_FULLSTACK_APPLICATION_V1":
            raise RuntimeError("CHECK_FULLSTACK_CONTRACT_INVALID")
        if app.get("frontend_root") != "frontend" or app.get("middle_root") != "middle" or app.get("backend_root") != "backend":
            raise RuntimeError("CHECK_FRONT_MIDDLE_BACK_ROOTS_INVALID")
        if app.get("backoffice_is_backend") is not False:
            raise RuntimeError("CHECK_BACKOFFICE_BACKEND_SEPARATION_INVALID")

        view = load_json(target / "frontend/views/home/home.view.json", "CHECK_HOME_VIEW")
        if view.get("contract") != "OPUS_FRONTEND_VIEW_V1" or view.get("layout") != "public":
            raise RuntimeError("CHECK_VIEW_LAYOUT_LINK_INVALID")
        if not isinstance(view.get("sections"), list) or not view.get("sections"):
            raise RuntimeError("CHECK_VIEW_SECTIONS_INVALID")

        endpoint = load_json(target / "backend/api-endpoints/catalog-list.endpoint.json", "CHECK_API_ENDPOINT")
        if endpoint.get("contract") != "OPUS_BACKEND_API_ENDPOINT_V1":
            raise RuntimeError("CHECK_API_ENDPOINT_CONTRACT_INVALID")

        forbid(target / "application", "CHECK_LEGACY_APPLICATION_ROOT")
        generated = "\n".join(p.read_text(encoding="utf-8", errors="ignore") for p in target.rglob("*") if p.is_file())
        for term in ["application/modules/Home", "application/modules/Articles", "application/modules/Rubriques"]:
            if term in generated:
                raise RuntimeError(f"CHECK_FORBIDDEN_LEGACY_TERM: {term}")

        print("CHECK_CREATE_APPLICATION_COMMAND=OK")
        print("CHECK_FRONTEND_BACKEND_ROOTS=OK")
        print("CHECK_VIEW_LAYOUT_SECTION_LINKS=OK")
        print("CHECK_COMPONENT_CONTRACTS=OK")
        print("CHECK_BACKEND_API_ENDPOINT=OK")
        print("CHECK_BACKOFFICE_NOT_BACKEND=OK")
        print("CHECK_NO_LEGACY_APPLICATION_ROOT=OK")
        print("CHECK_NEUTRAL_NO_BLOG_CMS_DEFAULT=OK")
        print("P117SITE20_CREATE_APPLICATION_FULLSTACK_SKELETON_SMOKE_OK")
        return 0
    finally:
        if target.exists():
            shutil.rmtree(target)
        if target.exists():
            raise RuntimeError("CHECK_CLEANUP_FAILED")
        print("CHECK_CLEANUP=OK")


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:
        print(str(exc), file=sys.stderr)
        raise SystemExit(1)
