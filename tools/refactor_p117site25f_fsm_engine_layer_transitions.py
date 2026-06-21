#!/usr/bin/env python3
"""P117SITE25F — FSM engine versus layer-owned transition fuel.

This runner refines the post-P117SITE25D/E physical boundary tree:
- Link is a FRONT component and must live under FRONT/Component/Link.
- The FSM engine is shared and must live in COMMON/FSM/Engine.
- Layer-specific transitions live in FRONT/MIDDLE/BACK, not in COMMON.
- REST + FSM + ACL + SSO is documented as the mandatory end-to-end chain.
"""

from __future__ import annotations

import argparse
import json
import shutil
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OPUS_ROOT = ROOT / "framework" / "Opus"
BOUNDARY_MAP = OPUS_ROOT / "BOUNDARY_MAP.json"
ARCHITECTURE_DOC = OPUS_ROOT / "ARCHITECTURE_BOUNDARIES.md"
COMPOSER_JSON = ROOT / "composer.json"

BOUNDARY_ROOTS = {"FRONT", "MIDDLE", "BACK", "COMMON"}
ROOT_FILES = {"README.md", "BOUNDARY_MAP.json", "ARCHITECTURE_BOUNDARIES.md"}

MOVES = {
    "FRONT/Breadcrumb": "FRONT/Component/Breadcrumb",
    "FRONT/Form": "FRONT/Component/Form",
    "FRONT/Link": "FRONT/Component/Link",
    "FRONT/Menu": "FRONT/Component/Menu",
    "Breadcrumb": "FRONT/Component/Breadcrumb",
    "Form": "FRONT/Component/Form",
    "Link": "FRONT/Component/Link",
    "Menu": "FRONT/Component/Menu",
    "MIDDLE/FSM/Engine": "COMMON/FSM/Engine",
    "Fsm": "COMMON/FSM/Engine",
    "COMMON/Lstsa": "BACK/Lstsa",
    "Lstsa": "BACK/Lstsa",
}

TRANSITION_DIRS = {
    "FRONT": "FRONT/FSM/Transitions",
    "MIDDLE": "MIDDLE/FSM/Transitions",
    "BACK": "BACK/FSM/Transitions",
}

TRANSITION_FILES = {
    "FRONT/FSM/Transitions/front.intent.submitted.json": {
        "schema": "OPUS_FSM_TRANSITIONS_V1",
        "owner_boundary": "FRONT",
        "engine_boundary": "COMMON/FSM/Engine",
        "transition": "FRONT_INTENT_SUBMITTED",
        "from": "FRONT_IDLE",
        "signal": "UI_INTENT_SUBMITTED",
        "to": "FRONT_INTENT_CAPTURED",
        "description": "A frontend view or component has captured a user intent and prepares a typed REST request.",
    },
    "MIDDLE/FSM/Transitions/middle.rest.security.authorized.json": {
        "schema": "OPUS_FSM_TRANSITIONS_V1",
        "owner_boundary": "MIDDLE",
        "engine_boundary": "COMMON/FSM/Engine",
        "transition": "MIDDLE_REST_SECURITY_AUTHORIZED",
        "from": "MIDDLE_ROUTE_MATCHED",
        "signal": "ACL_OK_SSO_OK_FSM_GATE_OK",
        "to": "MIDDLE_DISPATCH_ALLOWED",
        "security": ["ACL", "SSO", "FSM_GATE"],
        "description": "A REST request was routed, authenticated, authorized and accepted by the FSM gate before backend dispatch.",
    },
    "BACK/FSM/Transitions/back.action.executed.json": {
        "schema": "OPUS_FSM_TRANSITIONS_V1",
        "owner_boundary": "BACK",
        "engine_boundary": "COMMON/FSM/Engine",
        "transition": "BACK_ACTION_EXECUTED",
        "from": "BACK_ACTION_REQUESTED",
        "signal": "BACK_ACTION_DONE",
        "to": "BACK_RESULT_BUILT",
        "description": "A backend action executed a business operation and produced a typed response payload.",
    },
}

DOC_APPEND = """

## P117SITE25F — FSM engine versus layer transition fuel

The FSM engine is shared, but transition definitions are owned by each layer. The engine is not the fuel.

```mermaid
classDiagram
    namespace FRONT {
      class View
      class Component
      class Breadcrumb
      class Form
      class Link
      class Menu
      class FrontTransitions
      class ApiClient
    }
    namespace MIDDLE {
      class Router
      class RestTransport
      class ApiGateway
      class SecurityPipeline
      class ACL
      class SSO
      class FsmGate
      class MiddleTransitions
    }
    namespace BACK {
      class Action
      class Service
      class Repository
      class Runner
      class Job
      class Worker
      class BackTransitions
    }
    namespace COMMON {
      class FsmEngine
      class State
      class Signal
      class TransitionDefinition
      class FsmTrace
      class TypedError
      class Result
    }
    Component <|-- Breadcrumb
    Component <|-- Form
    Component <|-- Link
    Component <|-- Menu
    FrontTransitions --> FsmEngine : evaluated by
    MiddleTransitions --> FsmEngine : evaluated by
    BackTransitions --> FsmEngine : evaluated by
    ApiClient --> RestTransport : REST request
    SecurityPipeline --> FsmGate : ACL + SSO + FSM
    FsmGate --> ApiGateway : allow only if FSM allows
    ApiGateway --> Action : dispatch backend command
```

```mermaid
sequenceDiagram
    actor User
    participant Front as FRONT View/Component
    participant FrontFSM as FRONT FSM Transitions
    participant Client as FRONT ApiClient
    participant Middle as MIDDLE REST Router/Gateway
    participant Security as MIDDLE ACL/SSO/SecurityPipeline
    participant MiddleFSM as MIDDLE FSM Transitions
    participant Engine as COMMON FSM Engine
    participant Back as BACK Action/Service
    participant BackFSM as BACK FSM Transitions

    User->>Front: UI intent
    Front->>Engine: FRONT signal + FRONT transitions
    Engine-->>Front: UI transition accepted
    Front->>Client: build typed REST request
    Client->>Middle: REST request
    Middle->>Security: route + request contract
    Security->>Engine: MIDDLE signal + MIDDLE transitions + ACL + SSO
    Engine-->>Security: allow or deny
    alt allowed
        Security->>Middle: dispatch allowed
        Middle->>Back: backend command
        Back->>Engine: BACK signal + BACK transitions
        Engine-->>Back: backend transition accepted
        Back-->>Middle: typed response DTO
        Middle-->>Client: REST response
        Client-->>Front: view model / typed error
    else denied
        Security-->>Middle: typed denial
        Middle-->>Client: REST error response
        Client-->>Front: safe error view model
    end
```

```mermaid
stateDiagram-v2
    [*] --> FRONT_INTENT_CAPTURED
    FRONT_INTENT_CAPTURED --> FRONT_TRANSITION_ACCEPTED: FRONT_TRANSITION_OK
    FRONT_TRANSITION_ACCEPTED --> MIDDLE_REST_REQUEST_CREATED: REST_REQUEST_BUILT
    MIDDLE_REST_REQUEST_CREATED --> MIDDLE_ROUTE_MATCHED: ROUTE_MATCHED
    MIDDLE_ROUTE_MATCHED --> MIDDLE_ACL_CHECKED: ACL_OK
    MIDDLE_ACL_CHECKED --> MIDDLE_SSO_CHECKED: SSO_OK
    MIDDLE_SSO_CHECKED --> MIDDLE_FSM_GATE_ALLOWED: MIDDLE_TRANSITION_OK
    MIDDLE_FSM_GATE_ALLOWED --> BACK_ACTION_REQUESTED: DISPATCH_BACK
    BACK_ACTION_REQUESTED --> BACK_TRANSITION_ACCEPTED: BACK_TRANSITION_OK
    BACK_TRANSITION_ACCEPTED --> BACK_RESULT_BUILT: ACTION_DONE
    BACK_RESULT_BUILT --> MIDDLE_RESPONSE_BUILT: RESPONSE_DTO
    MIDDLE_RESPONSE_BUILT --> FRONT_VIEW_UPDATED: REST_RESPONSE
    FRONT_VIEW_UPDATED --> [*]
```

### Mandatory transition placement

- `COMMON/FSM/Engine` owns the generic engine only.
- `FRONT/FSM/Transitions` owns UI and representation transitions.
- `MIDDLE/FSM/Transitions` owns REST routing, ACL, SSO, security and dispatch transitions.
- `BACK/FSM/Transitions` owns business, data, runner, job, worker and adapter transitions.
- Generated applications own their own transitions under their own `frontend`, `middle` and `backend` trees.

Mermaid UML, sequence and FSM state diagrams are mandatory for architecture changes. FSM transition definitions must be machine-readable and must never be hidden inside prose only.
"""


def run(cmd: list[str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(cmd, cwd=str(ROOT), text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)


def rel(path: Path) -> str:
    return str(path.relative_to(ROOT)).replace("\\", "/")


def merge_move(src: Path, dst: Path) -> bool:
    if not src.exists():
        return False
    dst.parent.mkdir(parents=True, exist_ok=True)
    if not dst.exists():
        shutil.move(str(src), str(dst))
        print(f"OPUS_BOUNDARY_MOVE: {rel(src)} -> {rel(dst)}")
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
            print(f"OPUS_BOUNDARY_MOVE: {rel(child)} -> {rel(target)}")
    src.rmdir()
    return True


def assert_root_boundaries() -> None:
    roots = {p.name for p in OPUS_ROOT.iterdir() if p.is_dir()}
    extra = sorted(roots - BOUNDARY_ROOTS)
    missing = sorted(BOUNDARY_ROOTS - roots)
    if extra or missing:
        raise RuntimeError(f"INVALID_ROOT_BOUNDARIES extra={extra} missing={missing}")
    bad_files = sorted({p.name for p in OPUS_ROOT.iterdir() if p.is_file()} - ROOT_FILES)
    if bad_files:
        raise RuntimeError("UNEXPECTED_ROOT_FILES_REFUSED\n" + "\n".join(bad_files))


def patch_boundary_map() -> None:
    if not BOUNDARY_MAP.exists():
        raise RuntimeError(f"BOUNDARY_MAP_NOT_FOUND: {rel(BOUNDARY_MAP)}")
    data = json.loads(BOUNDARY_MAP.read_text(encoding="utf-8"))
    legacy = data.setdefault("legacy_root_directory_map", {})
    if not isinstance(legacy, dict):
        raise RuntimeError("BOUNDARY_MAP_LEGACY_MAP_NOT_OBJECT")
    legacy.update({
        "Breadcrumb": "FRONT/Component/Breadcrumb",
        "Form": "FRONT/Component/Form",
        "Link": "FRONT/Component/Link",
        "Menu": "FRONT/Component/Menu",
        "Fsm": "COMMON/FSM/Engine",
        "Lstsa": "BACK/Lstsa",
    })
    data["schema"] = "OPUS_FRAMEWORK_BOUNDARY_MAP_V5"
    data["fsm_processor"] = "mandatory"
    data["fsm_engine_boundary"] = "COMMON/FSM/Engine"
    data["fsm_transition_boundaries"] = {
        "FRONT": "FRONT/FSM/Transitions",
        "MIDDLE": "MIDDLE/FSM/Transitions",
        "BACK": "BACK/FSM/Transitions",
        "APPLICATION_FRONT": "sites/<app>/frontend/fsm/transitions",
        "APPLICATION_MIDDLE": "sites/<app>/middle/fsm/transitions",
        "APPLICATION_BACK": "sites/<app>/backend/fsm/transitions",
    }
    data["rest_transport"] = "FRONT -> MIDDLE -> BACK -> MIDDLE -> FRONT"
    data["security_pipeline"] = ["REST", "FSM", "ACL", "SSO"]
    data["common_rule"] = "strict shared language and shared engine only; layer transitions are forbidden in COMMON"
    BOUNDARY_MAP.write_text(json.dumps(data, indent=2, ensure_ascii=False) + "\n", encoding="utf-8", newline="\n")


def patch_transition_files() -> None:
    for relative in TRANSITION_DIRS.values():
        path = OPUS_ROOT / relative
        path.mkdir(parents=True, exist_ok=True)
        (path / "README.md").write_text(
            f"# OPUS {relative}\n\nLayer-specific FSM transition fuel. The engine is shared in COMMON/FSM/Engine.\n",
            encoding="utf-8",
            newline="\n",
        )
    for relative, payload in TRANSITION_FILES.items():
        path = OPUS_ROOT / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(json.dumps(payload, indent=2, ensure_ascii=False) + "\n", encoding="utf-8", newline="\n")


def patch_docs() -> None:
    if not ARCHITECTURE_DOC.exists():
        raise RuntimeError(f"ARCHITECTURE_DOC_NOT_FOUND: {rel(ARCHITECTURE_DOC)}")
    text = ARCHITECTURE_DOC.read_text(encoding="utf-8")
    if "P117SITE25F — FSM engine versus layer transition fuel" not in text:
        ARCHITECTURE_DOC.write_text(text.rstrip() + DOC_APPEND + "\n", encoding="utf-8", newline="\n")

    (OPUS_ROOT / "FRONT" / "README.md").write_text(
        "# OPUS FRONT\n\nRepresentation boundary only.\n\nComponents live under `FRONT/Component/*`; Breadcrumb, Form, Link and Menu are FRONT components.\n\nLayer-specific FSM transition fuel lives under `FRONT/FSM/Transitions`. The shared engine lives in `COMMON/FSM/Engine`.\n\nForbidden: business services, repositories, database access, runners, jobs, workers, system calls, security decisions and FSM engine implementation.\n",
        encoding="utf-8",
        newline="\n",
    )
    (OPUS_ROOT / "MIDDLE" / "README.md").write_text(
        "# OPUS MIDDLE\n\nSecure transport and orchestration boundary.\n\nAllowed: REST routing, request/response contracts, API gateway, middleware pipeline, ACL, SSO, CSRF, session, audit, rate limiting and mandatory FSM gates.\n\nLayer-specific FSM transition fuel lives under `MIDDLE/FSM/Transitions`. The shared engine lives in `COMMON/FSM/Engine`.\n\nForbidden: UI rendering, business services, repositories, direct domain persistence, runners, jobs and workers.\n",
        encoding="utf-8",
        newline="\n",
    )
    (OPUS_ROOT / "BACK" / "README.md").write_text(
        "# OPUS BACK\n\nBusiness, processing and data boundary.\n\nAllowed: modules, actions, services, repositories, validators, policies, runners, jobs, workers and external adapters.\n\nLayer-specific FSM transition fuel lives under `BACK/FSM/Transitions`. The shared engine lives in `COMMON/FSM/Engine`.\n\nForbidden: frontend rendering, UI components, navigation rendering, REST routing policy and shared FSM engine ownership.\n",
        encoding="utf-8",
        newline="\n",
    )
    (OPUS_ROOT / "COMMON" / "README.md").write_text(
        "# OPUS COMMON\n\nStrict shared language and shared engine only.\n\nAllowed: contracts, DTOs, value objects, typed errors, results, enums, identifiers, assertions, pure helpers, technical primitives shared by at least two boundaries, and the shared FSM engine in `COMMON/FSM/Engine`.\n\nForbidden: rendering, route dispatch, security policy implementation, business workflows, repositories, database gateways, runners, jobs, workers, app-specific logic and layer-specific FSM transitions.\n",
        encoding="utf-8",
        newline="\n",
    )


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--write", action="store_true")
    args = parser.parse_args()

    print("P117SITE25F_FSM_ENGINE_LAYER_TRANSITIONS_PLAN")
    for source, target in MOVES.items():
        print(f"MOVE {source} -> {target}")
    for owner, relative in TRANSITION_DIRS.items():
        print(f"ENSURE {owner} transitions -> {relative}")

    if not args.write:
        print("DRY_RUN_ONLY")
        return 0

    assert_root_boundaries()
    for source, target in MOVES.items():
        merge_move(OPUS_ROOT / source, OPUS_ROOT / target)
    patch_transition_files()
    patch_boundary_map()
    patch_docs()
    assert_root_boundaries()

    proc = run(["composer", "dump-autoload"])
    print(proc.stdout.strip())
    if proc.returncode != 0:
        raise RuntimeError(proc.stdout)

    print("P117SITE25F_FSM_ENGINE_LAYER_TRANSITIONS_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
