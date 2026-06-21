#!/usr/bin/env python3
"""P117SITE25E smoke — verify refined OPUS FRONT/MIDDLE/BACK/COMMON classification."""

from __future__ import annotations

import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OPUS_ROOT = ROOT / "framework" / "Opus"
COMPOSER_JSON = ROOT / "composer.json"

ALLOWED_ROOT_DIRS = {"FRONT", "MIDDLE", "BACK", "COMMON"}
ALLOWED_ROOT_FILES = {"README.md", "BOUNDARY_MAP.json", "ARCHITECTURE_BOUNDARIES.md"}
REQUIRED_PATHS = {
    "CHECK_FRONT_COMPONENT_BREADCRUMB": "FRONT/Component/Breadcrumb",
    "CHECK_FRONT_COMPONENT_FORM": "FRONT/Component/Form",
    "CHECK_FRONT_COMPONENT_MENU": "FRONT/Component/Menu",
    "CHECK_COMMON_FSM_ENGINE": "COMMON/FSM/Engine",
    "CHECK_BACK_LSTSA": "BACK/Lstsa",
    "CHECK_MIDDLE_ROUTER_STILL_MIDDLE": "MIDDLE/Router",
    "CHECK_BACK_MODULE_STILL_BACK": "BACK/Module",
    "CHECK_COMMON_CONTRACT_STILL_COMMON": "COMMON/Contract",
}
FORBIDDEN_PATHS = {
    "CHECK_NO_FRONT_BREADCRUMB_ROOT": "FRONT/Breadcrumb",
    "CHECK_NO_FRONT_FORM_ROOT": "FRONT/Form",
    "CHECK_NO_FRONT_MENU_ROOT": "FRONT/Menu",
    "CHECK_NO_COMMON_LSTSA": "COMMON/Lstsa",
    "CHECK_NO_MIDDLE_FSM_ENGINE": "MIDDLE/FSM/Engine",
    "CHECK_NO_LEGACY_FSM_ROOT": "Fsm",
    "CHECK_NO_LEGACY_LSTSA_ROOT": "Lstsa",
}


def run(cmd: list[str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(cmd, cwd=str(ROOT), text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)


def ok(marker: str) -> None:
    print(f"{marker}=OK")


def fail(marker: str, detail: str) -> int:
    print(f"{marker}=FAIL: {detail}")
    return 1


def check_exists(marker: str, relative: str) -> int:
    path = OPUS_ROOT / relative
    if not path.exists():
        return fail(marker, str(path))
    ok(marker)
    return 0


def check_absent(marker: str, relative: str) -> int:
    path = OPUS_ROOT / relative
    if path.exists():
        return fail(marker, str(path))
    ok(marker)
    return 0


def main() -> int:
    roots = {p.name for p in OPUS_ROOT.iterdir() if p.is_dir()}
    if roots != ALLOWED_ROOT_DIRS:
        return fail("CHECK_ONLY_BOUNDARY_ROOTS", f"roots={sorted(roots)}")
    ok("CHECK_ONLY_BOUNDARY_ROOTS")

    root_files = {p.name for p in OPUS_ROOT.iterdir() if p.is_file()}
    bad_files = sorted(root_files - ALLOWED_ROOT_FILES)
    if bad_files:
        return fail("CHECK_ROOT_FILES_LIMITED", ", ".join(bad_files))
    ok("CHECK_ROOT_FILES_LIMITED")

    for marker, relative in REQUIRED_PATHS.items():
        rc = check_exists(marker, relative)
        if rc:
            return rc

    for marker, relative in FORBIDDEN_PATHS.items():
        rc = check_absent(marker, relative)
        if rc:
            return rc

    boundary_map_path = OPUS_ROOT / "BOUNDARY_MAP.json"
    data = json.loads(boundary_map_path.read_text(encoding="utf-8"))
    if data.get("schema") != "OPUS_FRAMEWORK_BOUNDARY_MAP_V4":
        return fail("CHECK_BOUNDARY_MAP_SCHEMA", str(data.get("schema")))
    ok("CHECK_BOUNDARY_MAP_SCHEMA")
    if data.get("fsm_engine_boundary") != "COMMON/FSM/Engine":
        return fail("CHECK_FSM_ENGINE_BOUNDARY", str(data.get("fsm_engine_boundary")))
    ok("CHECK_FSM_ENGINE_BOUNDARY")
    if data.get("fsm_gate_boundary") != "MIDDLE/FSM_GATE":
        return fail("CHECK_FSM_GATE_BOUNDARY", str(data.get("fsm_gate_boundary")))
    ok("CHECK_FSM_GATE_BOUNDARY")

    legacy = data.get("legacy_root_directory_map", {})
    expected = {
        "Breadcrumb": "FRONT/Component/Breadcrumb",
        "Form": "FRONT/Component/Form",
        "Menu": "FRONT/Component/Menu",
        "Fsm": "COMMON/FSM/Engine",
        "Lstsa": "BACK/Lstsa",
    }
    for key, value in expected.items():
        if legacy.get(key) != value:
            return fail("CHECK_BOUNDARY_MAP_REFINED_CLASSIFICATION", f"{key}={legacy.get(key)}")
    ok("CHECK_BOUNDARY_MAP_REFINED_CLASSIFICATION")

    doc = (OPUS_ROOT / "ARCHITECTURE_BOUNDARIES.md").read_text(encoding="utf-8")
    for needle in [
        "P117SITE25E — refined physical classification",
        "```mermaid",
        "classDiagram",
        "stateDiagram-v2",
        "FSM engine lives in `COMMON/FSM/Engine`",
        "MIDDLE owns the FSM gates",
        "COMMON anti-catch-all reinforcement",
    ]:
        if needle not in doc:
            return fail("CHECK_MERMAID_AND_FSM_DOC", needle)
    ok("CHECK_MERMAID_AND_FSM_DOC")

    composer = json.loads(COMPOSER_JSON.read_text(encoding="utf-8"))
    classmap = composer.get("autoload", {}).get("classmap", [])
    for entry in ["framework/Opus/FRONT/", "framework/Opus/MIDDLE/", "framework/Opus/BACK/", "framework/Opus/COMMON/"]:
        if entry not in classmap:
            return fail("CHECK_COMPOSER_CLASSMAP", entry)
    ok("CHECK_COMPOSER_CLASSMAP")

    proc = run(["git", "status", "--short"])
    if proc.returncode != 0:
        return fail("CHECK_GIT_STATUS_COMMAND", proc.stdout.strip())
    ok("CHECK_GIT_STATUS_COMMAND")

    print("P117SITE25E_BOUNDARY_CLASSIFICATION_TUNING_SMOKE_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
