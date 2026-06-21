#!/usr/bin/env python3
"""P117SITE25C smoke — verify OPUS physical FRONT / MIDDLE / BACK / COMMON tree."""

from __future__ import annotations

import json
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OPUS_ROOT = ROOT / "framework" / "Opus"
COMPOSER_JSON = ROOT / "composer.json"

ALLOWED_ROOT_DIRS = {"FRONT", "MIDDLE", "BACK", "COMMON"}
ALLOWED_ROOT_FILES = {"README.md", "BOUNDARY_MAP.json", "ARCHITECTURE_BOUNDARIES.md"}
REQUIRED_FRONT = ["View", "Template", "Renderer", "Theme", "Menu", "Form"]
REQUIRED_MIDDLE = ["Router", "Routing", "Request", "Response", "Security", "FSM/Engine"]
REQUIRED_BACK = ["Module", "Scaffold", "Runtime", "Database"]
REQUIRED_COMMON = ["Contract", "Error/Exception", "Serialization/Json", "Serialization/Xml", "Validation"]
FORBIDDEN_COMMON = ["Router", "Routing", "Security", "Module", "Scaffold", "Runtime", "Database", "Renderer", "Template", "View"]


def run(cmd: list[str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(cmd, cwd=str(ROOT), text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)


def ok(marker: str) -> None:
    print(f"{marker}=OK")


def fail(marker: str, detail: str) -> int:
    print(f"{marker}=FAIL: {detail}")
    return 1


def check_path(marker: str, relative: str) -> int:
    path = OPUS_ROOT / relative
    if not path.exists():
        return fail(marker, str(path))
    ok(marker)
    return 0


def main() -> int:
    if not OPUS_ROOT.exists():
        return fail("CHECK_OPUS_ROOT", str(OPUS_ROOT))

    roots = {p.name for p in OPUS_ROOT.iterdir() if p.is_dir()}
    if roots != ALLOWED_ROOT_DIRS:
        return fail("CHECK_ONLY_BOUNDARY_ROOTS", ", ".join(sorted(roots - ALLOWED_ROOT_DIRS)) or f"missing {sorted(ALLOWED_ROOT_DIRS - roots)}")
    ok("CHECK_ONLY_BOUNDARY_ROOTS")

    root_files = {p.name for p in OPUS_ROOT.iterdir() if p.is_file()}
    bad_files = sorted(root_files - ALLOWED_ROOT_FILES)
    if bad_files:
        return fail("CHECK_ROOT_FILES_LIMITED", ", ".join(bad_files))
    ok("CHECK_ROOT_FILES_LIMITED")

    for item in REQUIRED_FRONT:
        rc = check_path("CHECK_FRONT_" + item.replace("/", "_").upper(), "FRONT/" + item)
        if rc:
            return rc
    for item in REQUIRED_MIDDLE:
        rc = check_path("CHECK_MIDDLE_" + item.replace("/", "_").upper(), "MIDDLE/" + item)
        if rc:
            return rc
    for item in REQUIRED_BACK:
        rc = check_path("CHECK_BACK_" + item.replace("/", "_").upper(), "BACK/" + item)
        if rc:
            return rc
    for item in REQUIRED_COMMON:
        rc = check_path("CHECK_COMMON_" + item.replace("/", "_").upper(), "COMMON/" + item)
        if rc:
            return rc

    for item in FORBIDDEN_COMMON:
        path = OPUS_ROOT / "COMMON" / item
        if path.exists():
            return fail("CHECK_COMMON_NOT_CATCH_ALL", str(path))
    ok("CHECK_COMMON_NOT_CATCH_ALL")

    boundary_map = OPUS_ROOT / "BOUNDARY_MAP.json"
    if not boundary_map.exists():
        return fail("CHECK_BOUNDARY_MAP", str(boundary_map))
    data = json.loads(boundary_map.read_text(encoding="utf-8"))
    if data.get("schema") != "OPUS_FRAMEWORK_BOUNDARY_MAP_V2":
        return fail("CHECK_BOUNDARY_MAP_SCHEMA", str(data.get("schema")))
    if data.get("fsm_processor") != "mandatory":
        return fail("CHECK_BOUNDARY_MAP_FSM", str(data.get("fsm_processor")))
    ok("CHECK_BOUNDARY_MAP")
    ok("CHECK_BOUNDARY_MAP_SCHEMA")
    ok("CHECK_BOUNDARY_MAP_FSM")

    doc = OPUS_ROOT / "ARCHITECTURE_BOUNDARIES.md"
    text = doc.read_text(encoding="utf-8") if doc.exists() else ""
    for needle in ["```mermaid", "classDiagram", "sequenceDiagram", "stateDiagram-v2"]:
        if needle not in text:
            return fail("CHECK_MERMAID_ARCHITECTURE_DOC", needle)
    ok("CHECK_MERMAID_ARCHITECTURE_DOC")

    composer = json.loads(COMPOSER_JSON.read_text(encoding="utf-8"))
    classmap = composer.get("autoload", {}).get("classmap", [])
    for entry in ["framework/Opus/FRONT/", "framework/Opus/MIDDLE/", "framework/Opus/BACK/", "framework/Opus/COMMON/"]:
        if entry not in classmap:
            return fail("CHECK_COMPOSER_CLASSMAP", entry)
    ok("CHECK_COMPOSER_CLASSMAP")

    proc = run(["git", "status", "--short"])
    if proc.returncode != 0:
        return fail("CHECK_GIT_STATUS", proc.stdout.strip())
    print("P117SITE25C_FRONT_MIDDLE_BACK_COMMON_PHYSICAL_TREE_SMOKE_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
