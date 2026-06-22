#!/usr/bin/env python3
"""Smoke test for P117SITE26 UI View State FSM Pipeline."""
from __future__ import annotations

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OPUS = ROOT / "framework" / "Opus"
DOC = ROOT / "DOC" / "P117SITE26_UI_VIEW_STATE_FSM_PIPELINE.md"

failures: list[str] = []


def check(name: str, ok: bool, detail: str = "") -> None:
    if ok:
        print(f"{name}=OK")
    else:
        message = f"{name}=FAIL" + (f": {detail}" if detail else "")
        print(message)
        failures.append(message)


def read_json(path: Path) -> dict:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception as exc:  # noqa: BLE001 - smoke output must be explicit
        failures.append(f"READ_JSON_FAIL: {path}: {exc}")
        return {}


def main() -> int:
    root_dirs = sorted(path.name for path in OPUS.iterdir() if path.is_dir()) if OPUS.exists() else []
    check("CHECK_ONLY_BOUNDARY_ROOTS", root_dirs == ["BACK", "COMMON", "FRONT", "MIDDLE"], ", ".join(root_dirs))

    front_states_path = OPUS / "FRONT" / "FSM" / "States" / "Views" / "front.view.states.json"
    front_transitions_path = OPUS / "FRONT" / "FSM" / "Transitions" / "front.ui.actions.transitions.json"
    middle_transitions_path = OPUS / "MIDDLE" / "FSM" / "Transitions" / "middle.rest_acl_sso.transitions.json"
    back_transitions_path = OPUS / "BACK" / "FSM" / "Transitions" / "back.execution.transitions.json"
    backoffice_states_path = OPUS / "FRONT" / "Backoffice" / "FSM" / "States" / "backoffice.admin.view.states.json"
    backoffice_transitions_path = OPUS / "FRONT" / "Backoffice" / "FSM" / "Transitions" / "backoffice.admin.transitions.json"
    blocked_states_path = OPUS / "COMMON" / "FSM" / "State" / "blocked.states.json"
    boundary_map_path = OPUS / "BOUNDARY_MAP.json"

    for name, path in {
        "CHECK_FRONT_VIEW_STATES_FILE": front_states_path,
        "CHECK_FRONT_UI_TRANSITIONS_FILE": front_transitions_path,
        "CHECK_MIDDLE_REST_ACL_SSO_TRANSITIONS_FILE": middle_transitions_path,
        "CHECK_BACK_EXECUTION_TRANSITIONS_FILE": back_transitions_path,
        "CHECK_BACKOFFICE_STATES_FILE": backoffice_states_path,
        "CHECK_BACKOFFICE_TRANSITIONS_FILE": backoffice_transitions_path,
        "CHECK_BLOCKED_STATES_FILE": blocked_states_path,
        "CHECK_BOUNDARY_MAP_FILE": boundary_map_path,
    }.items():
        check(name, path.exists(), str(path))

    front_states = read_json(front_states_path)
    front_transitions = read_json(front_transitions_path)
    middle_transitions = read_json(middle_transitions_path)
    back_transitions = read_json(back_transitions_path)
    backoffice_states = read_json(backoffice_states_path)
    backoffice_transitions = read_json(backoffice_transitions_path)
    blocked_states = read_json(blocked_states_path)
    boundary_map = read_json(boundary_map_path)

    state_ids = {item.get("id") for item in front_states.get("states", [])}
    check("CHECK_VIEW_IS_FSM_STATE_RULE", front_states.get("rule") == "VIEW_IS_FSM_STATE")
    check("CHECK_HOME_VIEW_STATE", "HomeView" in state_ids)
    check("CHECK_CATALOG_VIEW_STATE", "CatalogView" in state_ids)
    check("CHECK_BLOCKED_VIEW_STATE", "BlockedView" in state_ids)

    transition_rules = set(front_transitions.get("rules", []))
    check("CHECK_ACTION_IS_SIGNAL_RULE", "ACTION_IS_SIGNAL" in transition_rules)
    check("CHECK_INTERNAL_LINK_IS_ACTION_RULE", "INTERNAL_LINK_IS_ACTION" in transition_rules)
    check("CHECK_EXTERNAL_LINK_EXCEPTION_RULE", "EXTERNAL_LINK_IS_EXPLICIT_EXCEPTION" in transition_rules)
    check("CHECK_FRONT_ENGINE_REFERENCE", front_transitions.get("engine") == "COMMON/FSM/Engine")

    front_transition_actions = {item.get("action") for item in front_transitions.get("transitions", [])}
    check("CHECK_OPEN_CATALOG_ACTION", "OPEN_CATALOG" in front_transition_actions)
    check("CHECK_SEARCH_ACTION_SAME_VIEW", any(item.get("action") == "SEARCH" and item.get("on_success") == "CatalogView" for item in front_transitions.get("transitions", [])))
    check("CHECK_EXTERNAL_LINK_BYPASS_EXPLICIT", any(item.get("action") == "EXTERNAL_LINK" and item.get("bypass_opus_pipeline") is True for item in front_transitions.get("transitions", [])))

    mandatory_chain = middle_transitions.get("mandatory_chain", [])
    check("CHECK_REST_ACL_SSO_CHAIN", mandatory_chain == ["REST", "SSO", "ACL", "FSM_GATE"], str(mandatory_chain))
    middle_signals = {item.get("signal") for item in middle_transitions.get("transitions", [])}
    check("CHECK_MIDDLE_ROUTE_MATCH_SIGNAL", "middle.route.match" in middle_signals)
    check("CHECK_MIDDLE_SSO_SIGNAL", "middle.sso.ok" in middle_signals and "middle.sso.required" in middle_signals)
    check("CHECK_MIDDLE_ACL_SIGNAL", "middle.acl.ok" in middle_signals and "middle.acl.denied" in middle_signals)
    check("CHECK_MIDDLE_FSM_GATE_SIGNAL", "middle.fsm.gate.allowed" in middle_signals and "middle.fsm.gate.denied" in middle_signals)

    back_signals = {item.get("signal") for item in back_transitions.get("transitions", [])}
    check("CHECK_BACK_ACTION_SIGNAL", "back.action.requested" in back_signals)
    check("CHECK_BACK_SERVICE_SIGNAL", "back.service.executed" in back_signals)
    check("CHECK_BACK_RUNNER_FAILURE_BLOCKED", any(item.get("signal") == "back.runner.failed" and item.get("to") == "BLOCKED_BY_RUNNER_FAILURE" for item in back_transitions.get("transitions", [])))

    admin_state_ids = {item.get("id") for item in backoffice_states.get("states", [])}
    check("CHECK_BACKOFFICE_IS_FRONT_ADMIN_UI", backoffice_states.get("rule") == "BACKOFFICE_IS_ADMIN_UI_NOT_BACKEND")
    check("CHECK_ADMIN_DASHBOARD_VIEW_STATE", "AdminDashboardView" in admin_state_ids)
    check("CHECK_ADMIN_BLOCKED_STATES_VIEW", "AdminBlockedStatesView" in admin_state_ids)
    check("CHECK_ADMIN_REVIEW_TRANSITION", any(item.get("action") == "ADMIN_REVIEW_REQUIRED" for item in backoffice_transitions.get("transitions", [])))
    check("CHECK_ADMIN_UNBLOCK_TRANSITION", any(item.get("action") == "ADMIN_UNBLOCK" for item in backoffice_transitions.get("transitions", [])))

    blocked = set(blocked_states.get("states", []))
    check("CHECK_NO_SILENT_FALLBACK_RULE", blocked_states.get("rule") == "NO_SILENT_FALLBACK_ON_TRANSGRESSION")
    check("CHECK_BLOCKED_BY_INVALID_TRANSITION", "BLOCKED_BY_INVALID_TRANSITION" in blocked)
    check("CHECK_BLOCKED_BY_ACL_VIOLATION", "BLOCKED_BY_ACL_VIOLATION" in blocked)
    check("CHECK_BLOCKED_BY_SSO_REQUIRED", "BLOCKED_BY_SSO_REQUIRED" in blocked)
    check("CHECK_BLOCKED_BY_RUNNER_FAILURE", "BLOCKED_BY_RUNNER_FAILURE" in blocked)

    pipeline = boundary_map.get("fsm_pipeline_model", {})
    check("CHECK_BOUNDARY_MAP_PIPELINE_CONTRACT", pipeline.get("contract") == "OPUS_UI_VIEW_STATE_FSM_PIPELINE_V1")
    check("CHECK_BOUNDARY_MAP_FRONT_IS_UI", pipeline.get("front", {}).get("meaning") == "UI")
    check("CHECK_BOUNDARY_MAP_COMMON_ENGINE_ONLY", pipeline.get("common", {}).get("must_not_own_transition_fuel") is True)
    check("CHECK_BOUNDARY_MAP_BLOCKED_STATE_RULE", "blocked FSM state" in pipeline.get("blocked_state_rule", ""))

    doc = DOC.read_text(encoding="utf-8") if DOC.exists() else ""
    check("CHECK_DOC_EXISTS", DOC.exists(), str(DOC))
    check("CHECK_DOC_MERMAID_CLASS", "classDiagram" in doc)
    check("CHECK_DOC_MERMAID_SEQUENCE", "sequenceDiagram" in doc)
    check("CHECK_DOC_MERMAID_STATE", "stateDiagram-v2" in doc)
    check("CHECK_DOC_FRONT_MIDDLE_BACK_CHAIN", "FRONT -> MIDDLE -> BACK -> MIDDLE -> FRONT" in doc)
    check("CHECK_DOC_REST_FSM_ACL_SSO", "REST + FSM + ACL + SSO" in doc)
    check("CHECK_DOC_VIEW_IS_STATE", "VIEW = UI FSM state" in doc)
    check("CHECK_DOC_BACKOFFICE_IS_FRONT", "dashboard is the application's admin UI" in doc)
    check("CHECK_DOC_EXTERNAL_LINK_EXCEPTION", "External link is the only explicit exception" in doc)

    if failures:
        return 1
    print("P117SITE26_UI_VIEW_STATE_FSM_PIPELINE_SMOKE_OK")
    return 0


if __name__ == "__main__":
    sys.exit(main())
