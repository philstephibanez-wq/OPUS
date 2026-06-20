from __future__ import annotations

import os
import shutil
import subprocess
import sys
from pathlib import Path

ROOT = Path(os.environ.get("OPUS_ROOT", r"H:\OPUS"))
SITE = "skeleton"
SITE_ROOT = ROOT / "sites" / SITE


def run(args: list[str]) -> None:
    print("RUN=" + " ".join(args))
    completed = subprocess.run(["cmd", "/c", *args], cwd=str(ROOT), text=True)
    if completed.returncode != 0:
        raise RuntimeError("Command failed: " + " ".join(args))


def expect(path: Path, needle: str, code: str) -> None:
    data = path.read_text(encoding="utf-8")
    if needle not in data:
        raise RuntimeError(code)


def reject(path: Path, needle: str, code: str) -> None:
    data = path.read_text(encoding="utf-8")
    if needle in data:
        raise RuntimeError(code)


def main() -> int:
    print("P117SITE14_GENERATED_SITE_WORKFLOW_SMOKE_START")
    if SITE_ROOT.exists():
        shutil.rmtree(SITE_ROOT)

    try:
        run(["composer", "dump-autoload"])
        run(["composer", "opus:create-site", "--", SITE, "--write"])
        run(["composer", "opus:validate-site", "--", SITE])

        start = SITE_ROOT / "START_HERE.md"
        home_readme = SITE_ROOT / "application" / "modules" / "Home" / "README.md"
        public_index = SITE_ROOT / "public" / "index.php"

        expect(start, "Route -> module -> template -> i18n -> assets.", "P117SITE14_START_HERE_WORKFLOW_MISSING")
        expect(start, "resources/i18n/<locale>.json", "P117SITE14_START_HERE_I18N_MISSING")
        expect(start, "[[ ignore ]] ... [[ endignore ]]", "P117SITE14_START_HERE_IGNORE_DOC_MISSING")
        expect(home_readme, "resources/i18n/<locale>.json", "P117SITE14_MODULE_README_I18N_MISSING")
        reject(home_readme, ".fr.json", "P117SITE14_MODULE_README_LEGACY_MODULE_JSON_REFERENCE")
        expect(public_index, "ScoreTemplateRenderer", "P117SITE14_PUBLIC_SCORE_RENDERER_MISSING")
        reject(public_index, "opus_render_score", "P117SITE14_PUBLIC_LEGACY_RENDERER_PRESENT")

        print("CHECK_START_HERE_WORKFLOW=OK")
        print("CHECK_MODULE_README_WORKFLOW=OK")
        print("CHECK_PUBLIC_SCORE_RENDERER=OK")
    finally:
        if SITE_ROOT.exists():
            shutil.rmtree(SITE_ROOT)

    print("CHECK_CLEANUP=OK")
    print("P117SITE14_GENERATED_SITE_WORKFLOW_SMOKE_OK")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:
        if SITE_ROOT.exists():
            shutil.rmtree(SITE_ROOT)
        print("P117SITE14_UNEXPECTED_ERROR=" + repr(exc))
        raise SystemExit(1)
