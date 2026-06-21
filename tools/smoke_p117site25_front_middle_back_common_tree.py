#!/usr/bin/env python3
"""Smoke for P117SITE25 physical FRONT/MIDDLE/BACK/COMMON boundaries."""

from __future__ import annotations

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OPUS_ROOT = ROOT / "framework" / "Opus"
DOC = ROOT / "DOC" / "P117SITE25_FRONT_MIDDLE_BACK_COMMON_REORGANIZATION.md"
COMPOSER_JSON = ROOT / "composer.json"

REQUIRED_ROOT_DIRS = {"FRONT", "MIDDLE", "BACK", "COMMON"}
ALLOWED_ROOT_FILES = {"README.md", "BOUNDARY_MAP.json", "ARCHITECTURE_BOUNDARIES.md"}
FORBIDDEN_ROOT_DIRS = {
    "Database", "Date", "Debug", "Directory", "Documentation", "Event", "Exception", "File", "Form",
    "Fsm", "Ftp", "Helper", "Http", "I18n", "Javascript", "Json", "Language", "Link", "Log",
    "Lstsa", "Mail", "Menu", "Model", "Module", "Package", "PublicSite", "RefBook", "Renderer",
    "Request", "Response", "Rest", "Router", "Routing", "Runtime", "Scaffold", "Security", "Server",
    "Session", "Site", "Smtp", "Support", "Template", "Theme", "Uri", "Validation", "View", "Xml",
}
FRONT_EXPECTED = ["Form", "Javascript", "Link", "Menu", "Renderer", "Template", "Theme", "View"]
MIDDLE_EXPECTED = ["FSM", "Http", "Request", "Response", "Rest", "Router", "Routing", "Security", "Server", "Session", "Uri"]
BACK_EXPECTED = ["Database", "Ftp", "Mail", "Model", "Module", "Package", "PublicSite", "RefBook", "Runtime", "Scaffold", "Site", "Smtp"]
COMMON_EXPECTED = ["Date", "Debug", "Directory", "Documentation", "Event", "Error", "File", "Helper", "I18n", "Language", "Log", "Lstsa", "Serialization", "Support", "Validation"]
COMMON_FORBIDDEN = {"Router", "Routing", "Security", "Database", "Repository", "Runner", "Job", "Worker", "View", "Renderer", "Template", "Theme", "Module", "Model"}


def fail(message: str) -> int:
    print(message)
    return 1


def check(condition: bool, marker: str) -> bool:
    if condition:
        print(f"{marker}=OK")
        return True
    print(f"{marker}=FAIL")
    return False


def main() -> int:
    ok = True

    if not OPUS_ROOT.exists():
        return fail(f"OPUS_ROOT_NOT_FOUND: {OPUS_ROOT}")

    root_dirs = {p.name for p in OPUS_ROOT.iterdir() if p.is_dir()}
    root_files = {p.name for p in OPUS_ROOT.iterdir() if p.is_file()}
    unexpected_root_dirs = sorted(root_dirs - REQUIRED_ROOT_DIRS)
    forbidden_present = sorted(root_dirs & FORBIDDEN_ROOT_DIRS)

    ok &= check(REQUIRED_ROOT_DIRS.issubset(root_dirs), "CHECK_BOUNDARY_ROOT_DIRS")
    ok &= check(not unexpected_root_dirs, "CHECK_ONLY_BOUNDARY_ROOTS")
    ok &= check(not forbidden_present, "CHECK_NO_LEGACY_ROOT_DIRS")
    ok &= check(root_files.issubset(ALLOWED_ROOT_FILES), "CHECK_ROOT_FILES_LIMITED")

    ok &= check(all((OPUS_ROOT / "FRONT" / name).exists() for name in FRONT_EXPECTED), "CHECK_FRONT_MAPPED")
    ok &= check(all((OPUS_ROOT / "MIDDLE" / name).exists() for name in MIDDLE_EXPECTED), "CHECK_MIDDLE_MAPPED")
    ok &= check((OPUS_ROOT / "MIDDLE" / "FSM" / "Engine").exists(), "CHECK_MIDDLE_FSM_ENGINE_MAPPED")
    ok &= check(all((OPUS_ROOT / "BACK" / name).exists() for name in BACK_EXPECTED), "CHECK_BACK_MAPPED")
    ok &= check(all((OPUS_ROOT / "COMMON" / name).exists() for name in COMMON_EXPECTED), "CHECK_COMMON_MAPPED")
    ok &= check(not any((OPUS_ROOT / "COMMON" / name).exists() for name in COMMON_FORBIDDEN), "CHECK_COMMON_NOT_CATCH_ALL")
    ok &= check((OPUS_ROOT / "BOUNDARY_MAP.json").exists(), "CHECK_BOUNDARY_MAP_EXISTS")

    if (OPUS_ROOT / "BOUNDARY_MAP.json").exists():
        boundary = json.loads((OPUS_ROOT / "BOUNDARY_MAP.json").read_text(encoding="utf-8"))
        ok &= check(boundary.get("schema") == "OPUS_FRAMEWORK_BOUNDARY_MAP_V1", "CHECK_BOUNDARY_MAP_SCHEMA")
        ok &= check(boundary.get("rules", {}).get("fsm") == "mandatory processor for every operation path", "CHECK_FSM_MANDATORY_RULE")

    if not COMPOSER_JSON.exists():
        ok &= check(False, "CHECK_COMPOSER_JSON_EXISTS")
    else:
        composer = json.loads(COMPOSER_JSON.read_text(encoding="utf-8"))
        classmap = composer.get("autoload", {}).get("classmap", [])
        required_classmap = [
            "framework/Opus/FRONT/",
            "framework/Opus/MIDDLE/",
            "framework/Opus/BACK/",
            "framework/Opus/COMMON/",
        ]
        ok &= check(all(entry in classmap for entry in required_classmap), "CHECK_COMPOSER_CLASSMAP")

    if not DOC.exists():
        ok &= check(False, "CHECK_DOC_EXISTS")
    else:
        content = DOC.read_text(encoding="utf-8")
        ok &= check("```mermaid" in content, "CHECK_MERMAID_UML_DOC")
        ok &= check("stateDiagram-v2" in content, "CHECK_FSM_TRANSITION_DOC")
        ok &= check("COMMON must never become a catch-all" in content, "CHECK_COMMON_NOT_CATCH_ALL_DOC")

    if not ok:
        return 1
    print("P117SITE25_FRONT_MIDDLE_BACK_COMMON_TREE_SMOKE_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
