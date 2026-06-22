#!/usr/bin/env python3
"""
P117SITE26 — OPUS UI View State FSM Pipeline materializer.

This runner is intentionally local and idempotent. It does not move legacy framework
classes. It adds the missing state-processing contract material to an already migrated
FRONT/MIDDLE/BACK/COMMON tree.
"""
from __future__ import annotations

import argparse
import json
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
OPUS = ROOT / "framework" / "Opus"

REQUIRED_BOUNDARY_DIRS = ["BACK", "COMMON", "FRONT", "MIDDLE"]

FRONT_VIEW_STATES = {
    "contract": "OPUS_FRONT_VIEW_STATES_V1",
    "rule": "VIEW_IS_FSM_STATE",
    "layer": "FRONT",
    "states": [
        {"id": "HomeView", "kind": "ui_view", "blocked": False},
        {"id": "CatalogView", "kind": "ui_view", "blocked": False},
        {"id": "ProductView", "kind": "ui_view", "blocked": False},
        {"id": "LoginView", "kind": "ui_view", "blocked": False},
        {"id": "ForbiddenView", "kind": "ui_view", "blocked": False},
        {"id": "ErrorView", "kind": "ui_view", "blocked": False},
        {"id": "BlockedView", "kind": "ui_view", "blocked": True},
    ],
}

FRONT_TRANSITIONS = {
    "contract": "OPUS_FRONT_UI_TRANSITIONS_V1",
    "layer": "FRONT",
    "owner": "FRONT/FSM/Transitions",
    "engine": "COMMON/FSM/Engine",
    "rules": [
        "VIEW_IS_STATE",
        "ACTION_IS_SIGNAL",
        "INTERNAL_LINK_IS_ACTION",
        "EXTERNAL_LINK_IS_EXPLICIT_EXCEPTION",
    ],
    "transitions": [
        {
            "from": "HomeView",
            "action": "OPEN_CATALOG",
            "signal": "front.open_catalog.requested",
            "transport": "REST",
            "requires_middle": ["SSO", "ACL", "FSM_GATE"],
            "requires_back": ["Catalog.list"],
            "on_success": "CatalogView",
            "on_sso_required": "LoginView",
            "on_acl_denied": "ForbiddenView",
            "on_violation": "BlockedView",
        },
        {
            "from": "CatalogView",
            "action": "SEARCH",
            "signal": "front.catalog.search.requested",
            "transport": "REST",
            "requires_middle": ["SSO", "ACL", "FSM_GATE"],
            "requires_back": ["Catalog.search"],
            "on_success": "CatalogView",
            "on_error": "ErrorView",
            "on_violation": "BlockedView",
        },
        {
            "from": "CatalogView",
            "action": "OPEN_PRODUCT",
            "signal": "front.open_product.requested",
            "transport": "REST",
            "requires_middle": ["SSO", "ACL", "FSM_GATE"],
            "requires_back": ["Catalog.detail"],
            "on_success": "ProductView",
            "on_violation": "BlockedView",
        },
        {
            "from": "AnyView",
            "action": "EXTERNAL_LINK",
            "signal": "front.external_link.requested",
            "transport": "BROWSER_EXTERNAL",
            "bypass_opus_pipeline": True,
            "exception_reason": "External links leave the OPUS application boundary.",
        },
    ],
}

MIDDLE_TRANSITIONS = {
    "contract": "OPUS_MIDDLE_REST_ACL_SSO_TRANSITIONS_V1",
    "layer": "MIDDLE",
    "owner": "MIDDLE/FSM/Transitions",
    "engine": "COMMON/FSM/Engine",
    "mandatory_chain": ["REST", "SSO", "ACL", "FSM_GATE"],
    "transitions": [
        {"from": "REST_REQUEST_RECEIVED", "signal": "middle.route.match", "to": "ROUTE_MATCHED"},
        {"from": "ROUTE_MATCHED", "signal": "middle.sso.ok", "to": "SSO_ACCEPTED"},
        {"from": "ROUTE_MATCHED", "signal": "middle.sso.required", "to": "SSO_REQUIRED"},
        {"from": "SSO_ACCEPTED", "signal": "middle.acl.ok", "to": "ACL_ACCEPTED"},
        {"from": "SSO_ACCEPTED", "signal": "middle.acl.denied", "to": "ACL_DENIED"},
        {"from": "ACL_ACCEPTED", "signal": "middle.fsm.gate.allowed", "to": "BACK_DISPATCH_ALLOWED"},
        {"from": "ACL_ACCEPTED", "signal": "middle.fsm.gate.denied", "to": "BLOCKED_BY_INVALID_TRANSITION"},
    ],
}

BACK_TRANSITIONS = {
    "contract": "OPUS_BACK_EXECUTION_TRANSITIONS_V1",
    "layer": "BACK",
    "owner": "BACK/FSM/Transitions",
    "engine": "COMMON/FSM/Engine",
    "transitions": [
        {"from": "BACK_DISPATCH_ALLOWED", "signal": "back.action.requested", "to": "BACK_ACTION_REQUESTED"},
        {"from": "BACK_ACTION_REQUESTED", "signal": "back.service.executed", "to": "BACK_SERVICE_EXECUTED"},
        {"from": "BACK_SERVICE_EXECUTED", "signal": "back.result.built", "to": "MIDDLE_RESPONSE_REQUIRED"},
        {"from": "BACK_ACTION_REQUESTED", "signal": "back.runner.failed", "to": "BLOCKED_BY_RUNNER_FAILURE"},
        {"from": "BACK_ACTION_REQUESTED", "signal": "back.contract.violation", "to": "BLOCKED_BY_CONTRACT_VIOLATION"},
    ],
}

BACKOFFICE_STATES = {
    "contract": "OPUS_BACKOFFICE_BLOCKED_STATE_UI_V1",
    "layer": "FRONT",
    "area": "Backoffice",
    "rule": "BACKOFFICE_IS_ADMIN_UI_NOT_BACKEND",
    "states": [
        {"id": "AdminDashboardView", "kind": "admin_ui_view", "purpose": "Overview"},
        {"id": "AdminBlockedStatesView", "kind": "admin_ui_view", "purpose": "Blocked state review"},
        {"id": "AdminTransitionInspectorView", "kind": "admin_ui_view", "purpose": "FSM transition inspection"},
        {"id": "AdminAuditTrailView", "kind": "admin_ui_view", "purpose": "Audit trail"},
    ],
}

BACKOFFICE_TRANSITIONS = {
    "contract": "OPUS_BACKOFFICE_ADMIN_TRANSITIONS_V1",
    "layer": "FRONT",
    "area": "Backoffice",
    "owner": "FRONT/Backoffice/FSM/Transitions",
    "engine": "COMMON/FSM/Engine",
    "transitions": [
        {"from": "BlockedView", "action": "ADMIN_REVIEW_REQUIRED", "to": "AdminBlockedStatesView"},
        {"from": "AdminBlockedStatesView", "action": "ADMIN_UNBLOCK", "to": "HomeView"},
        {"from": "AdminBlockedStatesView", "action": "ADMIN_REJECT", "to": "ForbiddenView"},
        {"from": "AdminBlockedStatesView", "action": "ADMIN_REPAIR_PENDING", "to": "BlockedView"},
    ],
}

BLOCKED_STATES = {
    "contract": "OPUS_BLOCKED_STATES_V1",
    "rule": "NO_SILENT_FALLBACK_ON_TRANSGRESSION",
    "states": [
        "BLOCKED_BY_INVALID_TRANSITION",
        "BLOCKED_BY_CONTRACT_VIOLATION",
        "BLOCKED_BY_ACL_VIOLATION",
        "BLOCKED_BY_SSO_REQUIRED",
        "BLOCKED_BY_CSRF_FAILURE",
        "BLOCKED_BY_BACK_EXCEPTION",
        "BLOCKED_BY_RUNNER_FAILURE",
        "BLOCKED_BY_DATA_VALIDATION_ERROR",
    ],
}

ENGINE_README = """# COMMON/FSM/Engine

`COMMON/FSM/Engine` is the shared FSM processor.

It owns generic concepts only:

- state primitives;
- signals;
- transition definitions;
- transition results;
- traces;
- typed FSM errors.

It does not own layer-specific or application-specific transition fuel.

Transition fuel belongs to:

- `FRONT/FSM/Transitions` for UI actions and view-state transitions;
- `MIDDLE/FSM/Transitions` for REST, SSO, ACL and security-gate transitions;
- `BACK/FSM/Transitions` for business execution transitions;
- application/module-local FSM folders for user-defined transitions.
"""

FRONT_UI_README = """# FRONT/UI

`FRONT` is the OPUS UI layer.

A `View` is an FSM state. A user action is an FSM signal. Components display state or emit actions, but components do not change state directly.

Internal links are OPUS actions and must pass through the FSM pipeline. External links are the only explicit exception because they leave the OPUS application boundary.
"""

BACKOFFICE_README = """# FRONT/Backoffice/Dashboard

The dashboard is the application's admin UI. It is a FRONT concern, not a BACK concern.

The dashboard exposes blocked FSM states, audit trails and transition inspection screens. Admin actions are FSM transitions used to review, unblock, reject, repair or audit blocked states.
"""


def write_text(path: Path, content: str, *, write: bool) -> None:
    print(f"WRITE {path.relative_to(ROOT)}")
    if write:
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content, encoding="utf-8", newline="\n")


def write_json(path: Path, data: dict[str, Any], *, write: bool) -> None:
    write_text(path, json.dumps(data, indent=2, ensure_ascii=False) + "\n", write=write)


def require_boundary_tree() -> None:
    if not OPUS.exists():
        raise RuntimeError("OPUS framework root not found: framework/Opus")
    missing = [name for name in REQUIRED_BOUNDARY_DIRS if not (OPUS / name).is_dir()]
    if missing:
        raise RuntimeError("Missing boundary roots: " + ", ".join(missing))
    root_dirs = sorted(path.name for path in OPUS.iterdir() if path.is_dir())
    if root_dirs != REQUIRED_BOUNDARY_DIRS:
        raise RuntimeError("Unexpected OPUS root directories: " + ", ".join(root_dirs))


def load_boundary_map() -> dict[str, Any]:
    path = OPUS / "BOUNDARY_MAP.json"
    if not path.exists():
        raise RuntimeError("BOUNDARY_MAP.json missing. Apply P117SITE25D physical migration first.")
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"Invalid BOUNDARY_MAP.json: {exc}") from exc


def save_boundary_map(data: dict[str, Any], *, write: bool) -> None:
    data["fsm_pipeline_model"] = {
        "contract": "OPUS_UI_VIEW_STATE_FSM_PIPELINE_V1",
        "front": {
            "meaning": "UI",
            "view_state_path": "FRONT/FSM/States/Views",
            "transition_path": "FRONT/FSM/Transitions",
            "backoffice_state_path": "FRONT/Backoffice/FSM/States",
            "backoffice_transition_path": "FRONT/Backoffice/FSM/Transitions",
            "internal_link_rule": "Internal Link is a FRONT action signal.",
            "external_link_exception": "External Link leaves OPUS boundary and may bypass internal pipeline.",
        },
        "middle": {
            "meaning": "REST transport + ACL + SSO + FSM gates",
            "transition_path": "MIDDLE/FSM/Transitions",
            "mandatory_chain": ["REST", "SSO", "ACL", "FSM_GATE"],
        },
        "back": {
            "meaning": "business execution and resources",
            "transition_path": "BACK/FSM/Transitions",
        },
        "common": {
            "meaning": "generic FSM engine and shared language",
            "engine_path": "COMMON/FSM/Engine",
            "must_not_own_transition_fuel": True,
        },
        "blocked_state_rule": "Any transgression in any layer creates a blocked FSM state visible in the backoffice dashboard.",
    }
    write_json(OPUS / "BOUNDARY_MAP.json", data, write=write)


def materialize(write: bool) -> None:
    require_boundary_tree()
    boundary_map = load_boundary_map()

    write_text(OPUS / "COMMON" / "FSM" / "Engine" / "README.md", ENGINE_README, write=write)
    write_text(OPUS / "FRONT" / "UI" / "README.md", FRONT_UI_README, write=write)
    write_text(OPUS / "FRONT" / "Backoffice" / "Dashboard" / "README.md", BACKOFFICE_README, write=write)

    write_json(OPUS / "FRONT" / "FSM" / "States" / "Views" / "front.view.states.json", FRONT_VIEW_STATES, write=write)
    write_json(OPUS / "FRONT" / "FSM" / "Transitions" / "front.ui.actions.transitions.json", FRONT_TRANSITIONS, write=write)
    write_json(OPUS / "MIDDLE" / "FSM" / "Transitions" / "middle.rest_acl_sso.transitions.json", MIDDLE_TRANSITIONS, write=write)
    write_json(OPUS / "BACK" / "FSM" / "Transitions" / "back.execution.transitions.json", BACK_TRANSITIONS, write=write)
    write_json(OPUS / "FRONT" / "Backoffice" / "FSM" / "States" / "backoffice.admin.view.states.json", BACKOFFICE_STATES, write=write)
    write_json(OPUS / "FRONT" / "Backoffice" / "FSM" / "Transitions" / "backoffice.admin.transitions.json", BACKOFFICE_TRANSITIONS, write=write)
    write_json(OPUS / "COMMON" / "FSM" / "State" / "blocked.states.json", BLOCKED_STATES, write=write)

    save_boundary_map(boundary_map, write=write)

    print("P117SITE26_UI_VIEW_STATE_FSM_PIPELINE_OK")


def main() -> int:
    parser = argparse.ArgumentParser(description="Materialize OPUS UI View State FSM Pipeline contract.")
    parser.add_argument("--write", action="store_true", help="Write files. Without this flag, only print the plan.")
    args = parser.parse_args()
    materialize(write=args.write)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
