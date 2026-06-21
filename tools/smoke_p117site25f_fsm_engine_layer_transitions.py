#!/usr/bin/env python3
"""P117SITE25F smoke — verify FSM engine/layer transition separation and REST security chain."""

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
    "CHECK_FRONT_COMPONENT_LINK": "FRONT/Component/Link",
    "CHECK_FRONT_COMPONENT_MENU": "FRONT/Component/Menu",
    "CHECK_COMMON_FSM_ENGINE": "COMMON/FSM/Engine",
    "CHECK_FRONT_FSM_TRANSITIONS": "FRONT/FSM/Transitions",
    "CHECK_MIDDLE_FSM_TRANSITIONS": "MIDDLE/FSM/Transitions",
    "CHECK_BACK_FSM_TRANSITIONS": "BACK/FSM/Transitions",
    "CHECK_FRONT_TRANSITION_FILE": "FRONT/FSM/Transitions/front.intent.submitted.json",
    "CHECK_MIDDLE_TRANSITION_FILE": "MIDDLE/FSM/Transitions/middle.rest.security.authorized.json",
    "CHECK_BACK_TRANSITION_FILE": "BACK/FSM/Transitions/back.action.executed.json",
    "CHECK_BACK_LSTSA": "BACK/Lstsa",
}

FORBIDDEN_PATHS = {
    "CHECK_NO_FRONT_LINK_ROOT": "FRONT/Link",
    "CHECK_NO_LEGACY_LINK_ROOT": "Link",
    "CHECK_NO_COMMON_FSM_TRANSITIONS": "COMMON/FSM/Transitions",
    "CHECK_NO_MIDDLE_FSM_ENGINE": "MIDDLE/FSM/Engine",
    "CHECK_NO_COMMON_LSTSA": "COMMON/Lstsa",
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


def load_transition(relative: str) -> dict:
    return json.loads((OPUS_ROOT / relative).read_text(encoding="utf-8"))


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

    transition_expectations = {
        "CHECK_FRONT_TRANSITION_OWNER": ("FRONT/FSM/Transitions/front.intent.submitted.json", "FRONT"),
        "CHECK_MIDDLE_TRANSITION_OWNER": ("MIDDLE/FSM/Transitions/middle.rest.security.authorized.json", "MIDDLE"),
        "CHECK_BACK_TRANSITION_OWNER": ("BACK/FSM/Transitions/back.action.executed.json", "BACK"),
    }
    for marker, (relative, owner) in transition_expectations.items():
        payload = load_transition(relative)
        if payload.get("owner_boundary") != owner:
            return fail(marker, str(payload.get("owner_boundary")))
        if payload.get("engine_boundary") != "COMMON/FSM/Engine":
            return fail(marker, f"engine={payload.get('engine_boundary')}")
        ok(marker)

    middle_transition = load_transition("MIDDLE/FSM/Transitions/middle.rest.security.authorized.json")
    security = set(middle_transition.get("security", []))
    if not {"ACL", "SSO", "FSM_GATE"}.issubset(security):
        return fail("CHECK_REST_ACL_SSO_FSM_TRANSITION", str(sorted(security)))
    ok("CHECK_REST_ACL_SSO_FSM_TRANSITION")

    boundary_map = json.loads((OPUS_ROOT / "BOUNDARY_MAP.json").read_text(encoding="utf-8"))
    if boundary_map.get("schema") != "OPUS_FRAMEWORK_BOUNDARY_MAP_V5":
        return fail("CHECK_BOUNDARY_MAP_SCHEMA", str(boundary_map.get("schema")))
    ok("CHECK_BOUNDARY_MAP_SCHEMA")

    if boundary_map.get("fsm_engine_boundary") != "COMMON/FSM/Engine":
        return fail("CHECK_FSM_ENGINE_BOUNDARY", str(boundary_map.get("fsm_engine_boundary")))
    ok("CHECK_FSM_ENGINE_BOUNDARY")

    expected_transition_boundaries = {
        "FRONT": "FRONT/FSM/Transitions",
        "MIDDLE": "MIDDLE/FSM/Transitions",
        "BACK": "BACK/FSM/Transitions",
    }
    actual_transition_boundaries = boundary_map.get("fsm_transition_boundaries", {})
    for key, value in expected_transition_boundaries.items():
        if actual_transition_boundaries.get(key) != value:
            return fail("CHECK_LAYER_TRANSITION_BOUNDARIES", f"{key}={actual_transition_boundaries.get(key)}")
    ok("CHECK_LAYER_TRANSITION_BOUNDARIES")

    if boundary_map.get("rest_transport") != "FRONT -> MIDDLE -> BACK -> MIDDLE -> FRONT":
        return fail("CHECK_REST_TRANSPORT_CHAIN", str(boundary_map.get("rest_transport")))
    ok("CHECK_REST_TRANSPORT_CHAIN")

    security_pipeline = set(boundary_map.get("security_pipeline", []))
    if not {"REST", "FSM", "ACL", "SSO"}.issubset(security_pipeline):
        return fail("CHECK_SECURITY_PIPELINE_REST_FSM_ACL_SSO", str(sorted(security_pipeline)))
    ok("CHECK_SECURITY_PIPELINE_REST_FSM_ACL_SSO")

    legacy = boundary_map.get("legacy_root_directory_map", {})
    for key, value in {
        "Link": "FRONT/Component/Link",
        "Fsm": "COMMON/FSM/Engine",
        "Lstsa": "BACK/Lstsa",
    }.items():
        if legacy.get(key) != value:
            return fail("CHECK_BOUNDARY_MAP_REFINED_CLASSIFICATION", f"{key}={legacy.get(key)}")
    ok("CHECK_BOUNDARY_MAP_REFINED_CLASSIFICATION")

    architecture_doc = (OPUS_ROOT / "ARCHITECTURE_BOUNDARIES.md").read_text(encoding="utf-8")
    doc_needles = [
        "P117SITE25F — FSM engine versus layer transition fuel",
        "classDiagram",
        "sequenceDiagram",
        "stateDiagram-v2",
        "FRONT/FSM/Transitions",
        "MIDDLE/FSM/Transitions",
        "BACK/FSM/Transitions",
        "ACL + SSO + FSM",
        "REST",
        "Mermaid diagrams are not optional",
    ]
    for needle in doc_needles:
        if needle not in architecture_doc:
            return fail("CHECK_MANDATORY_MERMAID_AND_FSM_DOC", needle)
    ok("CHECK_MANDATORY_MERMAID_AND_FSM_DOC")

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

    print("P117SITE25F_FSM_ENGINE_LAYER_TRANSITIONS_SMOKE_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
