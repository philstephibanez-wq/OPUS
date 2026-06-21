from __future__ import annotations

import json
import os
import shutil
import subprocess
import sys
from pathlib import Path


def resolve_composer_command() -> list[str]:
    if os.name == "nt":
        if shutil.which("composer"):
            return ["cmd", "/d", "/c", "composer"]
        for candidate in ("composer.bat", "composer.cmd"):
            resolved = shutil.which(candidate)
            if resolved:
                return [resolved]
        raise RuntimeError("COMPOSER_EXECUTABLE_NOT_FOUND: composer/composer.bat/composer.cmd")

    resolved = shutil.which("composer")
    if resolved:
        return [resolved]
    raise RuntimeError("COMPOSER_EXECUTABLE_NOT_FOUND: composer")


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


def run_command(command: list[str], cwd: Path) -> str:
    completed = subprocess.run(command, cwd=str(cwd), text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    if completed.returncode != 0:
        raise RuntimeError(f"COMMAND_FAILED({completed.returncode}): {' '.join(command)}\n{completed.stdout}")
    return completed.stdout


def main() -> int:
    root = Path(__file__).resolve().parents[1]
    application_id = "p117site20-smoke"
    target = root / "sites" / application_id
    composer_command = resolve_composer_command()

    if target.exists():
        shutil.rmtree(target)

    try:
        output = run_command([*composer_command, "opus:create-application", "--", application_id, "--write"], root)
        if "OPUS_CREATE_APPLICATION_WRITTEN" not in output:
            raise RuntimeError("CREATE_APPLICATION_OUTPUT_MISSING")

        required_dirs = [
            "frontend/views/home",
            "frontend/layouts/public",
            "frontend/sections/site-header",
            "frontend/sections/home-hero",
            "frontend/sections/home-main",
            "frontend/sections/site-footer",
            "frontend/custom-components",
            "frontend/navigation",
            "frontend/api-clients",
            "backend/modules/content",
            "backend/modules/navigation",
            "backend/services",
            "backend/actions",
            "backend/repositories",
            "backend/validators",
            "backend/policies",
            "backend/api-endpoints",
            "backend/runners",
            "backend/jobs",
            "backend/dto",
            "backend/viewmodels",
            "resources/i18n",
            "public/assets/css",
            "docs",
        ]
        for relative in required_dirs:
            require_path(target / relative, f"CHECK_DIR_{relative.replace('/', '_').upper()}")

        required_files = [
            "application.opus.json",
            "frontend/views/home/home.view.json",
            "frontend/layouts/public/public.layout.json",
            "frontend/layouts/public/public.layout.score",
            "frontend/sections/site-header/site-header.section.json",
            "frontend/sections/site-header/site-header.section.score",
            "frontend/sections/home-hero/home-hero.section.json",
            "frontend/sections/home-hero/home-hero.section.score",
            "frontend/sections/home-main/home-main.section.json",
            "frontend/sections/home-main/home-main.section.score",
            "frontend/navigation/main.navigation.json",
            "backend/api-endpoints/home-viewmodel.endpoint.json",
            "backend/viewmodels/home.viewmodel.json",
            "resources/i18n/fr.json",
            "public/index.php",
        ]
        for relative in required_files:
            require_path(target / relative, f"CHECK_FILE_{relative.replace('/', '_').replace('.', '_').upper()}")

        application = require_json(target / "application.opus.json", "CHECK_APPLICATION_CONTRACT")
        if application.get("contract") != "OPUS_FULLSTACK_APPLICATION_V1":
            raise RuntimeError("CHECK_FULLSTACK_CONTRACT_INVALID")
        if application.get("frontend_root") != "frontend" or application.get("backend_root") != "backend":
            raise RuntimeError("CHECK_FRONT_BACK_ROOTS_INVALID")
        if application.get("backoffice_is_backend") is not False:
            raise RuntimeError("CHECK_BACKOFFICE_BACKEND_SEPARATION_INVALID")

        view = require_json(target / "frontend/views/home/home.view.json", "CHECK_HOME_VIEW")
        if view.get("contract") != "OPUS_FRONTEND_VIEW_V1":
            raise RuntimeError("CHECK_VIEW_CONTRACT_INVALID")
        if view.get("layout") != "public":
            raise RuntimeError("CHECK_VIEW_LAYOUT_LINK_INVALID")
        sections = view.get("sections")
        if not isinstance(sections, list) or not sections:
            raise RuntimeError("CHECK_VIEW_SECTIONS_INVALID")

        endpoint = require_json(target / "backend/api-endpoints/home-viewmodel.endpoint.json", "CHECK_API_ENDPOINT")
        if endpoint.get("contract") != "OPUS_BACKEND_API_ENDPOINT_V1":
            raise RuntimeError("CHECK_API_ENDPOINT_CONTRACT_INVALID")

        forbid_path(target / "application", "CHECK_LEGACY_APPLICATION_ROOT")
        generated_text = "\n".join(p.read_text(encoding="utf-8", errors="ignore") for p in target.rglob("*") if p.is_file())
        forbidden_terms = ["application/modules/Home", "application/modules/Articles", "application/modules/Rubriques"]
        for term in forbidden_terms:
            if term in generated_text:
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
