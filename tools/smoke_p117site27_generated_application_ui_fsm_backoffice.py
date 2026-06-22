#!/usr/bin/env python3
"""Smoke test for P117SITE27 generated application UI/FSM/backoffice contracts."""
from __future__ import annotations

import json
import shutil
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLAN_CANDIDATES = [
    ROOT / "framework" / "Opus" / "BACK" / "Scaffold" / "FullstackApplicationScaffoldPlan.php",
    ROOT / "framework" / "Opus" / "Scaffold" / "FullstackApplicationScaffoldPlan.php",
]
APP_ID = "p117site27-ui-fsm-smoke"
APP_ROOT = ROOT / "sites" / APP_ID


def ok(name: str) -> None:
    print(f"{name}=OK")


def fail(name: str, detail: str) -> None:
    print(f"{name}=FAIL: {detail}")
    raise SystemExit(1)


def read_plan() -> str:
    for plan in PLAN_CANDIDATES:
        if plan.exists():
            return plan.read_text(encoding="utf-8")
    fail("CHECK_PLAN_EXISTS", "FullstackApplicationScaffoldPlan.php not found")
    raise AssertionError("unreachable")


def run_composer_create() -> None:
    if APP_ROOT.exists():
        shutil.rmtree(APP_ROOT)
    cmd = ["cmd", "/d", "/c", "composer", "opus:create-application", "--", APP_ID, "--write"]
    proc = subprocess.run(cmd, cwd=str(ROOT), text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    if proc.returncode != 0:
        fail("CHECK_COMPOSER_CREATE_APPLICATION", proc.stdout.strip())
    if not APP_ROOT.exists():
        fail("CHECK_COMPOSER_CREATE_APPLICATION", "generated application directory missing")
    ok("CHECK_COMPOSER_CREATE_APPLICATION")


def load_json(rel: str) -> dict:
    path = APP_ROOT / rel
    if not path.exists():
        fail("CHECK_GENERATED_FILE", f"missing {rel}")
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        fail("CHECK_GENERATED_JSON", f"{rel}: {exc}")
    return data


def main() -> int:
    plan = read_plan()
    for marker in [
        "OPUS_UI_VIEW_STATE_FSM_PIPELINE_V1",
        "front.view.states.json",
        "front.ui.actions.transitions.json",
        "middle.rest_acl_sso.transitions.json",
        "back.execution.transitions.json",
        "backoffice.admin.view.states.json",
        "backoffice.admin.transitions.json",
        "blocked.states.json",
        "fsm_state",
        "backoffice_is_front_admin_ui",
    ]:
        if marker not in plan:
            fail("CHECK_PLAN_PATCHED", f"missing marker {marker}")
    ok("CHECK_PLAN_PATCHED")

    run_composer_create()

    app_contract = load_json("application.opus.json")
    if app_contract.get("front_view_is_fsm_state") is not True:
        fail("CHECK_APPLICATION_FSM_CONTRACT", "front_view_is_fsm_state not true")
    if app_contract.get("backoffice_is_front_admin_ui") is not True:
        fail("CHECK_APPLICATION_FSM_CONTRACT", "backoffice_is_front_admin_ui not true")
    if app_contract.get("rest_acl_sso_fsm_chain_required") is not True:
        fail("CHECK_APPLICATION_FSM_CONTRACT", "rest_acl_sso_fsm_chain_required not true")
    ok("CHECK_APPLICATION_FSM_CONTRACT")

    view_states = load_json("frontend/fsm/states/views/front.view.states.json")
    state_ids = {state.get("id") for state in view_states.get("states", [])}
    for state_id in ["home", "catalog-index", "catalog-detail", "backoffice", "blocked"]:
        if state_id not in state_ids:
            fail("CHECK_FRONT_VIEW_STATES", f"missing {state_id}")
    if view_states.get("rule") != "VIEW_IS_FSM_STATE":
        fail("CHECK_FRONT_VIEW_STATES", "VIEW_IS_FSM_STATE rule missing")
    ok("CHECK_FRONT_VIEW_STATES")

    home_view = load_json("frontend/views/home/home.view.json")
    if home_view.get("fsm_state") != "home":
        fail("CHECK_VIEW_HAS_FSM_STATE", "home view fsm_state mismatch")
    if home_view.get("state_owner") != "frontend/fsm/states/views":
        fail("CHECK_VIEW_HAS_FSM_STATE", "home view state_owner mismatch")
    ok("CHECK_VIEW_HAS_FSM_STATE")

    front_transitions = load_json("frontend/fsm/transitions/front.ui.actions.transitions.json")
    actions = {transition.get("action") for transition in front_transitions.get("transitions", [])}
    for action in ["OPEN_CATALOG", "SEARCH", "OPEN_PRODUCT", "EXTERNAL_LINK"]:
        if action not in actions:
            fail("CHECK_FRONT_ACTION_TRANSITIONS", f"missing action {action}")
    if "INTERNAL_LINK_IS_ACTION" not in front_transitions.get("rules", []):
        fail("CHECK_FRONT_ACTION_TRANSITIONS", "internal link rule missing")
    external = [transition for transition in front_transitions.get("transitions", []) if transition.get("action") == "EXTERNAL_LINK"]
    if not external or external[0].get("bypass_opus_pipeline") is not True:
        fail("CHECK_FRONT_ACTION_TRANSITIONS", "external link boundary exception missing")
    ok("CHECK_FRONT_ACTION_TRANSITIONS")

    middle_transitions = load_json("middle/fsm/transitions/middle.rest_acl_sso.transitions.json")
    chain = middle_transitions.get("mandatory_chain", [])
    for required in ["REST_ROUTE", "REQUEST_CONTRACT", "SSO", "ACL", "FSM_GATE", "AUDIT"]:
        if required not in chain:
            fail("CHECK_MIDDLE_REST_ACL_SSO", f"missing {required}")
    ok("CHECK_MIDDLE_REST_ACL_SSO")

    back_transitions = load_json("backend/fsm/transitions/back.execution.transitions.json")
    back_signals = {transition.get("signal") for transition in back_transitions.get("transitions", [])}
    for signal in ["back.action.requested", "back.service.executed", "back.result.built", "back.contract.violation"]:
        if signal not in back_signals:
            fail("CHECK_BACK_EXECUTION_TRANSITIONS", f"missing {signal}")
    ok("CHECK_BACK_EXECUTION_TRANSITIONS")

    catalog_transitions = load_json("backend/modules/catalog/fsm/transitions/catalog.transitions.json")
    if catalog_transitions.get("module") != "catalog":
        fail("CHECK_MODULE_OWN_TRANSITIONS", "catalog module transitions missing module owner")
    ok("CHECK_MODULE_OWN_TRANSITIONS")

    blocked_states = load_json("common/fsm/state/blocked.states.json")
    if blocked_states.get("rule") != "NO_SILENT_FALLBACK_ON_TRANSGRESSION":
        fail("CHECK_BLOCKED_STATES", "no silent fallback rule missing")
    if "BLOCKED_BY_ACL_VIOLATION" not in blocked_states.get("states", []):
        fail("CHECK_BLOCKED_STATES", "ACL blocked state missing")
    ok("CHECK_BLOCKED_STATES")

    backoffice_states = load_json("frontend/backoffice/fsm/states/backoffice.admin.view.states.json")
    if backoffice_states.get("rule") != "DASHBOARD_IS_FRONT_ADMIN_UI_NOT_BACKEND":
        fail("CHECK_BACKOFFICE_IS_FRONT_UI", "dashboard/front rule missing")
    ok("CHECK_BACKOFFICE_IS_FRONT_UI")

    backoffice_transitions = load_json("frontend/backoffice/fsm/transitions/backoffice.admin.transitions.json")
    admin_actions = {transition.get("action") for transition in backoffice_transitions.get("transitions", [])}
    for action in ["ADMIN_REVIEW_REQUIRED", "ADMIN_UNBLOCK", "ADMIN_REJECT", "ADMIN_REPAIR_PENDING"]:
        if action not in admin_actions:
            fail("CHECK_BACKOFFICE_ADMIN_TRANSITIONS", f"missing {action}")
    ok("CHECK_BACKOFFICE_ADMIN_TRANSITIONS")

    engine_readme = (APP_ROOT / "common/fsm/engine/README.md").read_text(encoding="utf-8")
    if "The engine is shared" not in engine_readme or "transition fuel" not in engine_readme:
        fail("CHECK_COMMON_ENGINE_NOT_FUEL", "engine/fuel separation missing")
    ok("CHECK_COMMON_ENGINE_NOT_FUEL")

    pipeline_doc = (APP_ROOT / "docs/fsm-pipeline.md").read_text(encoding="utf-8")
    for marker in ["stateDiagram-v2", "sequenceDiagram", "FRONT -> MIDDLE -> BACK -> MIDDLE -> FRONT", "ADMIN_REVIEW_REQUIRED"]:
        if marker not in pipeline_doc:
            fail("CHECK_GENERATED_MERMAID_DOC", f"missing {marker}")
    ok("CHECK_GENERATED_MERMAID_DOC")

    shutil.rmtree(APP_ROOT)
    ok("CHECK_CLEANUP")
    print("P117SITE27_GENERATED_APPLICATION_UI_FSM_BACKOFFICE_SMOKE_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
