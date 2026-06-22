#!/usr/bin/env python3
"""
P117SITE27 — Patch the generated OPUS application skeleton so it materializes
UI views as FSM states, layer-owned transitions, REST + FSM + ACL + SSO flow,
and the backoffice dashboard as FRONT admin UI for blocked-state intervention.

This script is idempotent and supports both pre-migration and post-physical-
boundary OPUS trees:
- framework/Opus/Scaffold/FullstackApplicationScaffoldPlan.php
- framework/Opus/BACK/Scaffold/FullstackApplicationScaffoldPlan.php
"""
from __future__ import annotations

import argparse
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLAN_CANDIDATES = [
    ROOT / "framework" / "Opus" / "BACK" / "Scaffold" / "FullstackApplicationScaffoldPlan.php",
    ROOT / "framework" / "Opus" / "Scaffold" / "FullstackApplicationScaffoldPlan.php",
]

MARKER = "OPUS_UI_VIEW_STATE_FSM_PIPELINE_V1"

ENTRY_ANCHOR = "        $entries[] = ScaffoldEntry::file(\"sites/{$app}/middle/contracts/README.md\", $this->middleContractsReadme());\n"
METHOD_ANCHOR = "    private function publicLayoutScore(): string\n"
APPLICATION_CONTRACT_ANCHOR = "            'backend_root' => 'backend',\n            'public_root' => 'public',\n"
VIEW_RETURN_OLD = "        return ['contract' => 'OPUS_FRONTEND_VIEW_V1', 'id' => $id, 'route' => $route, 'layout' => $layout, 'viewmodel' => $id, 'sections' => $sections];\n"
VIEW_RETURN_NEW = "        return ['contract' => 'OPUS_FRONTEND_VIEW_V1', 'id' => $id, 'route' => $route, 'layout' => $layout, 'fsm_state' => $id, 'viewmodel' => $id, 'sections' => $sections, 'state_owner' => 'frontend/fsm/states/views'];\n"
DIRECTORY_ANCHOR = "            'frontend/custom-components', 'frontend/navigation', 'frontend/api-clients', 'frontend/assets/css', 'frontend/assets/js', 'frontend/theme',\n"
DIRECTORY_REPLACEMENT = "            'frontend/ui', 'frontend/custom-components', 'frontend/navigation', 'frontend/api-clients', 'frontend/assets/css', 'frontend/assets/js', 'frontend/theme',\n            'frontend/fsm/states/views', 'frontend/fsm/transitions', 'frontend/backoffice/dashboard', 'frontend/backoffice/fsm/states', 'frontend/backoffice/fsm/transitions',\n            'common/fsm/engine', 'common/fsm/state', 'common/fsm/contract', 'common/fsm/result', 'common/fsm/trace',\n"
MIDDLE_DIRECTORY_ANCHOR = "            'middle/routes', 'middle/api', 'middle/security', 'middle/contracts', 'middle/fsm',\n"
MIDDLE_DIRECTORY_REPLACEMENT = "            'middle/routes', 'middle/api', 'middle/security', 'middle/contracts', 'middle/fsm', 'middle/fsm/transitions',\n"
BACK_DIRECTORY_ANCHOR = "            'backend/validators', 'backend/policies', 'backend/api-endpoints', 'backend/runners', 'backend/jobs', 'backend/dto', 'backend/viewmodels',\n"
BACK_DIRECTORY_REPLACEMENT = "            'backend/validators', 'backend/policies', 'backend/api-endpoints', 'backend/runners', 'backend/jobs', 'backend/dto', 'backend/viewmodels',\n            'backend/fsm/states', 'backend/fsm/transitions', 'backend/modules/catalog/fsm/states', 'backend/modules/catalog/fsm/transitions',\n"

ENTRY_BLOCK = """        $entries[] = ScaffoldEntry::file("sites/{$app}/common/fsm/engine/README.md", $this->commonFsmEngineReadme());
        $entries[] = ScaffoldEntry::file("sites/{$app}/common/fsm/state/blocked.states.json", $this->json($this->blockedStatesContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/ui/README.md", $this->frontUiReadme());
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/fsm/states/views/front.view.states.json", $this->json($this->frontViewStatesContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/fsm/transitions/front.ui.actions.transitions.json", $this->json($this->frontUiTransitionsContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/backoffice/dashboard/README.md", $this->backofficeDashboardReadme());
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/backoffice/fsm/states/backoffice.admin.view.states.json", $this->json($this->backofficeViewStatesContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/backoffice/fsm/transitions/backoffice.admin.transitions.json", $this->json($this->backofficeTransitionsContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/middle/fsm/transitions/middle.rest_acl_sso.transitions.json", $this->json($this->middleRestAclSsoTransitionsContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/fsm/transitions/back.execution.transitions.json", $this->json($this->backExecutionTransitionsContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/modules/catalog/fsm/transitions/catalog.transitions.json", $this->json($this->catalogModuleTransitionsContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/docs/fsm-pipeline.md", $this->fsmPipelineDoc());
"""

APPLICATION_CONTRACT_PATCH = """            'backend_root' => 'backend',
            'common_root' => 'common',
            'fsm_processor_contract' => 'OPUS_UI_VIEW_STATE_FSM_PIPELINE_V1',
            'front_view_is_fsm_state' => true,
            'front_action_is_fsm_signal' => true,
            'internal_link_is_fsm_action' => true,
            'external_link_is_explicit_boundary_exit' => true,
            'rest_acl_sso_fsm_chain_required' => true,
            'backoffice_is_front_admin_ui' => true,
            'blocked_state_requires_admin_review' => true,
            'public_root' => 'public',
"""

METHOD_BLOCK = r'''    /** @return array<string,mixed> */
    private function frontViewStatesContract(): array
    {
        return [
            'contract' => 'OPUS_FRONT_VIEW_STATES_V1',
            'pipeline_contract' => 'OPUS_UI_VIEW_STATE_FSM_PIPELINE_V1',
            'rule' => 'VIEW_IS_FSM_STATE',
            'owner' => 'frontend/fsm/states/views',
            'states' => [
                ['id' => 'home', 'view' => 'home', 'kind' => 'ui_view', 'blocked' => false],
                ['id' => 'architecture', 'view' => 'architecture', 'kind' => 'ui_view', 'blocked' => false],
                ['id' => 'catalog-index', 'view' => 'catalog-index', 'kind' => 'ui_view', 'blocked' => false],
                ['id' => 'catalog-detail', 'view' => 'catalog-detail', 'kind' => 'ui_view', 'blocked' => false],
                ['id' => 'components', 'view' => 'components', 'kind' => 'ui_view', 'blocked' => false],
                ['id' => 'security', 'view' => 'security', 'kind' => 'ui_view', 'blocked' => false],
                ['id' => 'backoffice', 'view' => 'backoffice', 'kind' => 'admin_ui_view', 'blocked' => false],
                ['id' => 'documentation', 'view' => 'documentation', 'kind' => 'ui_view', 'blocked' => false],
                ['id' => 'login', 'view' => 'login', 'kind' => 'ui_view', 'blocked' => false],
                ['id' => 'forbidden', 'view' => 'forbidden', 'kind' => 'ui_view', 'blocked' => false],
                ['id' => 'blocked', 'view' => 'blocked', 'kind' => 'ui_view', 'blocked' => true],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function frontUiTransitionsContract(): array
    {
        return [
            'contract' => 'OPUS_FRONT_UI_ACTION_TRANSITIONS_V1',
            'pipeline_contract' => 'OPUS_UI_VIEW_STATE_FSM_PIPELINE_V1',
            'layer' => 'FRONT',
            'owner' => 'frontend/fsm/transitions',
            'engine' => 'common/fsm/engine',
            'rules' => [
                'VIEW_IS_STATE',
                'ACTION_IS_SIGNAL',
                'COMPONENT_EMITS_ACTION_BUT_DOES_NOT_CHANGE_STATE_DIRECTLY',
                'INTERNAL_LINK_IS_ACTION',
                'EXTERNAL_LINK_IS_EXPLICIT_BOUNDARY_EXIT',
            ],
            'transitions' => [
                ['from' => 'home', 'action' => 'OPEN_CATALOG', 'signal' => 'front.open_catalog.requested', 'transport' => 'REST', 'requires' => ['MIDDLE_REST', 'MIDDLE_SSO', 'MIDDLE_ACL', 'MIDDLE_FSM_GATE', 'BACK_CATALOG_LIST'], 'on_success' => 'catalog-index', 'on_sso_required' => 'login', 'on_acl_denied' => 'forbidden', 'on_violation' => 'blocked'],
                ['from' => 'catalog-index', 'action' => 'SEARCH', 'signal' => 'front.catalog.search.requested', 'transport' => 'REST', 'requires' => ['MIDDLE_REST', 'MIDDLE_SSO', 'MIDDLE_ACL', 'MIDDLE_FSM_GATE', 'BACK_CATALOG_SEARCH'], 'on_success' => 'catalog-index', 'on_error' => 'blocked'],
                ['from' => 'catalog-index', 'action' => 'OPEN_PRODUCT', 'signal' => 'front.open_product.requested', 'transport' => 'REST', 'requires' => ['MIDDLE_REST', 'MIDDLE_SSO', 'MIDDLE_ACL', 'MIDDLE_FSM_GATE', 'BACK_CATALOG_DETAIL'], 'on_success' => 'catalog-detail', 'on_violation' => 'blocked'],
                ['from' => '*', 'action' => 'EXTERNAL_LINK', 'signal' => 'front.external_link.requested', 'transport' => 'BROWSER_EXTERNAL', 'bypass_opus_pipeline' => true, 'exception_reason' => 'External Link leaves the OPUS application boundary.'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function middleRestAclSsoTransitionsContract(): array
    {
        return [
            'contract' => 'OPUS_MIDDLE_REST_ACL_SSO_FSM_TRANSITIONS_V1',
            'layer' => 'MIDDLE',
            'owner' => 'middle/fsm/transitions',
            'engine' => 'common/fsm/engine',
            'mandatory_chain' => ['REST_ROUTE', 'REQUEST_CONTRACT', 'SSO', 'ACL', 'FSM_GATE', 'AUDIT'],
            'transitions' => [
                ['from' => 'REST_REQUEST_RECEIVED', 'signal' => 'middle.route.match', 'to' => 'ROUTE_MATCHED'],
                ['from' => 'ROUTE_MATCHED', 'signal' => 'middle.request.valid', 'to' => 'REQUEST_CONTRACT_ACCEPTED'],
                ['from' => 'REQUEST_CONTRACT_ACCEPTED', 'signal' => 'middle.sso.ok', 'to' => 'SSO_ACCEPTED'],
                ['from' => 'REQUEST_CONTRACT_ACCEPTED', 'signal' => 'middle.sso.required', 'to' => 'BLOCKED_BY_SSO_REQUIRED'],
                ['from' => 'SSO_ACCEPTED', 'signal' => 'middle.acl.ok', 'to' => 'ACL_ACCEPTED'],
                ['from' => 'SSO_ACCEPTED', 'signal' => 'middle.acl.denied', 'to' => 'BLOCKED_BY_ACL_VIOLATION'],
                ['from' => 'ACL_ACCEPTED', 'signal' => 'middle.fsm.gate.allowed', 'to' => 'BACK_DISPATCH_ALLOWED'],
                ['from' => 'ACL_ACCEPTED', 'signal' => 'middle.fsm.gate.denied', 'to' => 'BLOCKED_BY_INVALID_TRANSITION'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function backExecutionTransitionsContract(): array
    {
        return [
            'contract' => 'OPUS_BACK_EXECUTION_TRANSITIONS_V1',
            'layer' => 'BACK',
            'owner' => 'backend/fsm/transitions',
            'engine' => 'common/fsm/engine',
            'transitions' => [
                ['from' => 'BACK_DISPATCH_ALLOWED', 'signal' => 'back.action.requested', 'to' => 'BACK_ACTION_REQUESTED'],
                ['from' => 'BACK_ACTION_REQUESTED', 'signal' => 'back.service.executed', 'to' => 'BACK_SERVICE_EXECUTED'],
                ['from' => 'BACK_SERVICE_EXECUTED', 'signal' => 'back.result.built', 'to' => 'MIDDLE_RESPONSE_REQUIRED'],
                ['from' => 'BACK_ACTION_REQUESTED', 'signal' => 'back.contract.violation', 'to' => 'BLOCKED_BY_CONTRACT_VIOLATION'],
                ['from' => 'BACK_ACTION_REQUESTED', 'signal' => 'back.runner.failed', 'to' => 'BLOCKED_BY_RUNNER_FAILURE'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function catalogModuleTransitionsContract(): array
    {
        return [
            'contract' => 'OPUS_BACK_MODULE_TRANSITIONS_V1',
            'module' => 'catalog',
            'owner' => 'backend/modules/catalog/fsm/transitions',
            'engine' => 'common/fsm/engine',
            'transitions' => [
                ['from' => 'BACK_ACTION_REQUESTED', 'signal' => 'catalog.list.requested', 'to' => 'CATALOG_LIST_REQUESTED'],
                ['from' => 'CATALOG_LIST_REQUESTED', 'signal' => 'catalog.items.loaded', 'to' => 'CATALOG_ITEMS_LOADED'],
                ['from' => 'CATALOG_ITEMS_LOADED', 'signal' => 'catalog.response.ready', 'to' => 'MIDDLE_RESPONSE_REQUIRED'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function backofficeViewStatesContract(): array
    {
        return [
            'contract' => 'OPUS_BACKOFFICE_VIEW_STATES_V1',
            'layer' => 'FRONT',
            'area' => 'Backoffice',
            'rule' => 'DASHBOARD_IS_FRONT_ADMIN_UI_NOT_BACKEND',
            'states' => [
                ['id' => 'AdminDashboardView', 'purpose' => 'Admin overview'],
                ['id' => 'AdminBlockedStatesView', 'purpose' => 'Blocked state review'],
                ['id' => 'AdminTransitionInspectorView', 'purpose' => 'FSM transition inspection'],
                ['id' => 'AdminAuditTrailView', 'purpose' => 'Audit trail'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function backofficeTransitionsContract(): array
    {
        return [
            'contract' => 'OPUS_BACKOFFICE_ADMIN_TRANSITIONS_V1',
            'layer' => 'FRONT',
            'area' => 'Backoffice',
            'owner' => 'frontend/backoffice/fsm/transitions',
            'engine' => 'common/fsm/engine',
            'transitions' => [
                ['from' => 'blocked', 'action' => 'ADMIN_REVIEW_REQUIRED', 'to' => 'AdminBlockedStatesView'],
                ['from' => 'AdminBlockedStatesView', 'action' => 'ADMIN_UNBLOCK', 'to' => 'home'],
                ['from' => 'AdminBlockedStatesView', 'action' => 'ADMIN_REJECT', 'to' => 'forbidden'],
                ['from' => 'AdminBlockedStatesView', 'action' => 'ADMIN_REPAIR_PENDING', 'to' => 'blocked'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function blockedStatesContract(): array
    {
        return [
            'contract' => 'OPUS_BLOCKED_STATES_V1',
            'rule' => 'NO_SILENT_FALLBACK_ON_TRANSGRESSION',
            'backoffice' => 'frontend/backoffice/dashboard',
            'states' => [
                'BLOCKED_BY_INVALID_TRANSITION',
                'BLOCKED_BY_CONTRACT_VIOLATION',
                'BLOCKED_BY_ACL_VIOLATION',
                'BLOCKED_BY_SSO_REQUIRED',
                'BLOCKED_BY_CSRF_FAILURE',
                'BLOCKED_BY_BACK_EXCEPTION',
                'BLOCKED_BY_RUNNER_FAILURE',
                'BLOCKED_BY_DATA_VALIDATION_ERROR',
            ],
        ];
    }

    private function commonFsmEngineReadme(): string
    {
        return "# COMMON FSM Engine\n\nThe engine is shared. It is the processor. It owns no application-specific transition fuel.\n";
    }

    private function frontUiReadme(): string
    {
        return "# FRONT UI\n\nFRONT is the UI layer. A View is an FSM state and an Action is a signal. Components emit actions or display state; they do not mutate state directly.\n";
    }

    private function backofficeDashboardReadme(): string
    {
        return "# Backoffice Dashboard\n\nThe dashboard is FRONT admin UI. It exposes blocked FSM states and lets an administrator review, unblock, reject, repair or audit. It is not BACK.\n";
    }

    private function fsmPipelineDoc(): string
    {
        return <<<'MARKDOWN'
# OPUS generated application FSM pipeline

## Contract

`VIEW = FSM state`, `ACTION = FSM signal`, `COMMON/FSM/Engine = processor`.

Every internal action follows:

```text
FRONT -> MIDDLE -> BACK -> MIDDLE -> FRONT
```

External links are the only explicit boundary-exit exception.

## State diagram

```mermaid
stateDiagram-v2
    [*] --> home
    home --> catalog-index: OPEN_CATALOG / REST + SSO_OK + ACL_OK + BACK_OK
    home --> login: OPEN_CATALOG / SSO_REQUIRED
    home --> forbidden: OPEN_CATALOG / ACL_DENIED
    home --> blocked: OPEN_CATALOG / CONTRACT_VIOLATION
    catalog-index --> catalog-index: SEARCH / BACK_OK + SAME_VIEW
    catalog-index --> catalog-detail: OPEN_PRODUCT / BACK_OK
    catalog-index --> blocked: INVALID_TRANSITION
    catalog-detail --> catalog-index: BACK_TO_CATALOG
    blocked --> AdminBlockedStatesView: ADMIN_REVIEW_REQUIRED
    home --> ExternalBrowser: EXTERNAL_LINK
```

## Sequence diagram

```mermaid
sequenceDiagram
    actor User
    participant UI as FRONT UI View
    participant Engine as COMMON FSM Engine
    participant REST as MIDDLE REST
    participant SSO as MIDDLE SSO
    participant ACL as MIDDLE ACL
    participant BACK as BACK Action/Service
    participant ADMIN as FRONT Backoffice Dashboard

    User->>UI: action
    UI->>Engine: current view state + action signal
    Engine->>REST: internal request
    REST->>SSO: session transition
    SSO->>ACL: permission transition
    ACL->>BACK: backend transition
    BACK-->>ACL: business result
    ACL-->>REST: response contract
    REST-->>Engine: transition result
    alt normal result
        Engine-->>UI: next view state or same view state
    else transgression
        Engine-->>ADMIN: blocked state review required
        Engine-->>UI: blocked/login/forbidden/error view
    end
```
MARKDOWN;
    }

'''


def find_plan() -> Path:
    for candidate in PLAN_CANDIDATES:
        if candidate.exists():
            return candidate
    raise RuntimeError("FullstackApplicationScaffoldPlan.php not found in legacy or BACK/Scaffold path")


def replace_once(text: str, old: str, new: str) -> str:
    if new in text:
        return text
    if old not in text:
        raise RuntimeError(f"Patch anchor not found: {old[:90]!r}")
    return text.replace(old, new, 1)


def insert_after_once(text: str, anchor: str, block: str, marker: str) -> str:
    if marker in text:
        return text
    if anchor not in text:
        raise RuntimeError(f"Patch insertion anchor not found: {anchor[:90]!r}")
    return text.replace(anchor, anchor + block, 1)


def insert_before_once(text: str, anchor: str, block: str, marker: str) -> str:
    if marker in text:
        return text
    if anchor not in text:
        raise RuntimeError(f"Patch method anchor not found: {anchor[:90]!r}")
    return text.replace(anchor, block + anchor, 1)


def patch_plan(text: str) -> str:
    text = replace_once(text, DIRECTORY_ANCHOR, DIRECTORY_REPLACEMENT)
    text = replace_once(text, MIDDLE_DIRECTORY_ANCHOR, MIDDLE_DIRECTORY_REPLACEMENT)
    text = replace_once(text, BACK_DIRECTORY_ANCHOR, BACK_DIRECTORY_REPLACEMENT)
    text = insert_after_once(text, ENTRY_ANCHOR, ENTRY_BLOCK, "front.view.states.json")
    text = replace_once(text, APPLICATION_CONTRACT_ANCHOR, APPLICATION_CONTRACT_PATCH)
    text = replace_once(text, VIEW_RETURN_OLD, VIEW_RETURN_NEW)
    text = insert_before_once(text, METHOD_ANCHOR, METHOD_BLOCK, "private function frontViewStatesContract")
    return text


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--write", action="store_true")
    args = parser.parse_args()

    plan = find_plan()
    original = plan.read_text(encoding="utf-8")
    updated = patch_plan(original)

    print("P117SITE27_GENERATED_APPLICATION_UI_FSM_BACKOFFICE_PLAN")
    print("PLAN_FILE=" + str(plan.relative_to(ROOT)))
    print("PATCH_MARKER=" + MARKER)

    if args.write:
        plan.write_text(updated, encoding="utf-8", newline="\n")
        print("P117SITE27_GENERATED_APPLICATION_UI_FSM_BACKOFFICE_OK")
    else:
        print("DRY_RUN_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
