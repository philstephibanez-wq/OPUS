#!/usr/bin/env python3
"""
P117SITE25E — OPUS boundary classification tuning.

This runner applies the refined physical tree requested after P117SITE25D:
- FRONT components are grouped under FRONT/Component/*;
- the FSM engine lives in COMMON/FSM/Engine because it is the mandatory shared processor;
- MIDDLE keeps FSM gates/orchestration, not the engine implementation;
- LSTSA belongs to BACK because it is a processing / transform engine;
- COMMON remains a strict shared-language boundary, never a catch-all.
"""

from __future__ import annotations

import argparse
import json
import shutil
import subprocess
import sys
from pathlib import Path
from typing import Iterable

ROOT = Path(__file__).resolve().parents[1]
OPUS_ROOT = ROOT / "framework" / "Opus"
BOUNDARY_MAP_PATH = OPUS_ROOT / "BOUNDARY_MAP.json"
ARCHITECTURE_DOC = OPUS_ROOT / "ARCHITECTURE_BOUNDARIES.md"

BOUNDARY_ROOTS = {"FRONT", "MIDDLE", "BACK", "COMMON"}
ROOT_FILES = {"README.md", "BOUNDARY_MAP.json", "ARCHITECTURE_BOUNDARIES.md"}

MOVES = {
    # Component regrouping in FRONT.
    "FRONT/Breadcrumb": "FRONT/Component/Breadcrumb",
    "FRONT/Form": "FRONT/Component/Form",
    "FRONT/Menu": "FRONT/Component/Menu",
    "Breadcrumb": "FRONT/Component/Breadcrumb",
    "Form": "FRONT/Component/Form",
    "Menu": "FRONT/Component/Menu",

    # FSM engine is shared infrastructure, orchestrated through MIDDLE gates.
    "MIDDLE/FSM/Engine": "COMMON/FSM/Engine",
    "Fsm": "COMMON/FSM/Engine",

    # LSTSA is processing / transform machinery, therefore BACK.
    "COMMON/Lstsa": "BACK/Lstsa",
    "Lstsa": "BACK/Lstsa",
}

BOUNDARY_MAP_CORRECTIONS = {
    "Breadcrumb": "FRONT/Component/Breadcrumb",
    "Form": "FRONT/Component/Form",
    "Menu": "FRONT/Component/Menu",
    "Fsm": "COMMON/FSM/Engine",
    "Lstsa": "BACK/Lstsa",
}

DOC_APPEND = """

## P117SITE25E — refined physical classification

The physical tree is not only grouped by four root directories. It also documents why each item is placed there.

```mermaid
classDiagram
    namespace FRONT {
      class Component
      class Breadcrumb
      class Form
      class Menu
      class View
      class Renderer
    }
    namespace MIDDLE {
      class Router
      class SecurityPipeline
      class FsmGate
      class ApiGateway
    }
    namespace BACK {
      class Action
      class Module
      class Service
      class Repository
      class Runner
      class Lstsa
    }
    namespace COMMON {
      class Contract
      class Dto
      class TypedError
      class Result
      class SharedFsmEngine
    }
    Component <|-- Breadcrumb
    Component <|-- Form
    Component <|-- Menu
    FsmGate --> SharedFsmEngine : mandatory transition check
    ApiGateway --> Action : dispatch only after FSM allow
    Action --> Lstsa : processing / transform
```

### FSM processor placement

The FSM engine lives in `COMMON/FSM/Engine` because the FSM is the mandatory shared processor for every layer. `MIDDLE` owns the FSM gates and orchestration boundary, but it must not own the common engine implementation.

```mermaid
stateDiagram-v2
    [*] --> FrontIntent
    FrontIntent --> MiddleRouteResolved: ROUTE_MATCHED
    MiddleRouteResolved --> MiddleSecurityChecked: SECURITY_CHECK
    MiddleSecurityChecked --> CommonFsmEvaluating: FSM_SIGNAL
    CommonFsmEvaluating --> MiddleDispatchAllowed: FSM_ALLOW
    CommonFsmEvaluating --> MiddleDispatchDenied: FSM_DENY
    MiddleDispatchAllowed --> BackActionRunning: DISPATCH_BACK_ACTION
    BackActionRunning --> MiddleResponseBuilt: BUILD_RESPONSE
    MiddleDispatchDenied --> MiddleErrorBuilt: BUILD_TYPED_ERROR
    MiddleResponseBuilt --> FrontUpdated: RETURN_VIEWMODEL
    MiddleErrorBuilt --> FrontUpdated: RETURN_ERROR
    FrontUpdated --> [*]
```

### COMMON anti-catch-all reinforcement

`COMMON` accepts only shared vocabulary and shared processors that are independent from presentation, routing policy, business domains, database persistence and external runners. LSTSA is not COMMON because it processes/transforms data. Breadcrumb, Form and Menu are not COMMON because they are FRONT components.
"""


def run(cmd: list[str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(cmd, cwd=str(ROOT), text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)


def rel(path: Path) -> str:
    return str(path.relative_to(ROOT)).replace("\\", "/")


def root_dirs() -> list[str]:
    return sorted(path.name for path in OPUS_ROOT.iterdir() if path.is_dir())


def root_files() -> list[str]:
    return sorted(path.name for path in OPUS_ROOT.iterdir() if path.is_file())


def merge_move(src: Path, dst: Path) -> bool:
    if not src.exists():
        return False
    dst.parent.mkdir(parents=True, exist_ok=True)
    if not dst.exists():
        shutil.move(str(src), str(dst))
        return True
    if not src.is_dir() or not dst.is_dir():
        raise RuntimeError(f"BOUNDARY_MOVE_COLLISION: {rel(src)} -> {rel(dst)}")
    for child in sorted(src.iterdir(), key=lambda item: item.name.lower()):
        target = dst / child.name
        if target.exists():
            if child.is_dir() and target.is_dir():
                merge_move(child, target)
            else:
                raise RuntimeError(f"BOUNDARY_MOVE_COLLISION: {rel(child)} -> {rel(target)}")
        else:
            shutil.move(str(child), str(target))
    src.rmdir()
    return True


def remove_empty_dirs(paths: list[Path]) -> None:
    for path in paths:
        if path.exists() and path.is_dir() and not any(path.iterdir()):
            path.rmdir()


def assert_root_shape() -> None:
    roots = set(root_dirs())
    extra = sorted(roots - BOUNDARY_ROOTS)
    missing = sorted(BOUNDARY_ROOTS - roots)
    if extra or missing:
        raise RuntimeError(f"INVALID_ROOT_BOUNDARIES extra={extra} missing={missing}")
    bad_files = sorted(set(root_files()) - ROOT_FILES)
    if bad_files:
        raise RuntimeError("UNEXPECTED_ROOT_FILES_REFUSED\n" + "\n".join(bad_files))


def patch_boundary_map() -> None:
    if not BOUNDARY_MAP_PATH.exists():
        raise RuntimeError(f"BOUNDARY_MAP_NOT_FOUND: {rel(BOUNDARY_MAP_PATH)}")
    data = json.loads(BOUNDARY_MAP_PATH.read_text(encoding="utf-8"))
    legacy = data.setdefault("legacy_root_directory_map", {})
    if not isinstance(legacy, dict):
        raise RuntimeError("BOUNDARY_MAP_LEGACY_MAP_NOT_OBJECT")
    legacy.update(BOUNDARY_MAP_CORRECTIONS)
    data["schema"] = "OPUS_FRAMEWORK_BOUNDARY_MAP_V4"
    data["fsm_processor"] = "mandatory"
    data["fsm_engine_boundary"] = "COMMON/FSM/Engine"
    data["fsm_gate_boundary"] = "MIDDLE/FSM_GATE"
    data["common_rule"] = "strict shared language and shared processors only; never a catch-all"
    BOUNDARY_MAP_PATH.write_text(json.dumps(data, indent=2, ensure_ascii=False) + "\n", encoding="utf-8", newline="\n")


def patch_docs() -> None:
    if not ARCHITECTURE_DOC.exists():
        raise RuntimeError(f"ARCHITECTURE_DOC_NOT_FOUND: {rel(ARCHITECTURE_DOC)}")
    text = ARCHITECTURE_DOC.read_text(encoding="utf-8")
    if "P117SITE25E — refined physical classification" not in text:
        ARCHITECTURE_DOC.write_text(text.rstrip() + DOC_APPEND + "\n", encoding="utf-8", newline="\n")

    (OPUS_ROOT / "FRONT" / "README.md").write_text(
        "# OPUS FRONT\n\nRepresentation boundary only.\n\nComponents live under `FRONT/Component/*`; Breadcrumb, Form and Menu are FRONT components.\n\nForbidden: business services, repositories, database access, runners, jobs, workers, system calls, security decisions and FSM execution.\n",
        encoding="utf-8",
        newline="\n",
    )
    (OPUS_ROOT / "MIDDLE" / "README.md").write_text(
        "# OPUS MIDDLE\n\nSecure transport and orchestration boundary.\n\nAllowed: routing, request/response contracts, API gateway, middleware pipeline, ACL, SSO, CSRF, session, audit, rate limiting and mandatory FSM gates.\n\nThe shared FSM engine lives in `COMMON/FSM/Engine`; MIDDLE only gates and orchestrates it.\n\nForbidden: UI rendering, business services, repositories, direct domain persistence, runners, jobs and workers.\n",
        encoding="utf-8",
        newline="\n",
    )
    (OPUS_ROOT / "COMMON" / "README.md").write_text(
        "# OPUS COMMON\n\nStrict shared language and shared processors only.\n\nAllowed: contracts, DTOs, value objects, typed errors, results, enums, identifiers, assertions, pure helpers, technical primitives shared by at least two boundaries, and the shared FSM engine.\n\nForbidden: rendering, route dispatch, security policy implementation, business workflows, repositories, database gateways, runners, jobs, workers and app-specific logic.\n",
        encoding="utf-8",
        newline="\n",
    )
    (OPUS_ROOT / "COMMON" / "FSM" / "README.md").write_text(
        "# OPUS COMMON FSM\n\nShared mandatory processor.\n\nEvery FRONT intent, MIDDLE route/API dispatch and BACK action must pass through an explicit FSM signal, state, action and transition.\n\nMIDDLE owns gates; COMMON owns the reusable FSM engine vocabulary/processor.\n",
        encoding="utf-8",
        newline="\n",
    )


def composer_dump_autoload() -> None:
    proc = run(["cmd", "/d", "/c", "composer", "dump-autoload"])
    if proc.returncode != 0:
        raise RuntimeError("COMPOSER_DUMP_AUTOLOAD_FAILED\n" + proc.stdout.strip())
    print(proc.stdout.strip())


def print_plan() -> None:
    print("P117SITE25E_BOUNDARY_CLASSIFICATION_TUNING_PLAN")
    for source, target in MOVES.items():
        src = OPUS_ROOT / source
        if src.exists():
            print(f"MOVE {rel(src)} -> {rel(OPUS_ROOT / target)}")


def apply() -> None:
    assert_root_shape()
    for source, target in MOVES.items():
        src = OPUS_ROOT / source
        dst = OPUS_ROOT / target
        if merge_move(src, dst):
            print(f"OPUS_BOUNDARY_TUNING_MOVE: {rel(src)} -> {rel(dst)}")
    remove_empty_dirs([
        OPUS_ROOT / "MIDDLE" / "FSM",
        OPUS_ROOT / "FRONT" / "Component" / "Breadcrumb" / "Breadcrumb",
        OPUS_ROOT / "FRONT" / "Component" / "Form" / "Form",
        OPUS_ROOT / "FRONT" / "Component" / "Menu" / "Menu",
    ])
    patch_boundary_map()
    patch_docs()
    composer_dump_autoload()
    assert_root_shape()
    print("P117SITE25E_BOUNDARY_CLASSIFICATION_TUNING_OK")


def main(argv: Iterable[str]) -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--write", action="store_true")
    args = parser.parse_args(list(argv))
    try:
        print_plan()
        if not args.write:
            print("DRY_RUN_ONLY: add --write to apply")
            return 0
        apply()
        return 0
    except Exception as exc:
        print(str(exc))
        return 1


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
