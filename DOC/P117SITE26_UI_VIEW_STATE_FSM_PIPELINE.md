# P117SITE26 — UI View State FSM Pipeline

## Status

DELIVERED.

## Purpose

This palier fixes the OPUS state-processing model.

The FSM is not a cosmetic guard around a request. The FSM is the processor. Every internal user action is a signal, every FRONT view is a UI state, and every internal transition must travel through the end-to-end pipeline:

```text
FRONT -> MIDDLE -> BACK -> MIDDLE -> FRONT
```

The mandatory transport/control chain is:

```text
REST + FSM + ACL + SSO
```

## Layer meaning

```text
FRONT = UI
VIEW = UI FSM state
ACTION = user signal / intent
COMPONENT = UI element that displays state or emits action
```

```text
MIDDLE = transport + rights + orchestration
REST = mandatory internal transport boundary
ACL = permission transition
SSO = identity/session transition
FSM gate = middle transition checkpoint
```

```text
BACK = business execution / data / resources
ACTION/SERVICE/REPOSITORY/RUNNER/JOB/WORKER = backend execution controlled by FSM
```

```text
COMMON = shared language + generic FSM engine
COMMON/FSM/Engine = reusable processor only
COMMON/FSM/Engine owns no application, layer or module transition fuel
```

## Visual target

```text
framework/Opus/
├── FRONT/
│   ├── UI/
│   ├── View/
│   ├── Component/
│   │   ├── Breadcrumb/
│   │   ├── Form/
│   │   ├── Link/
│   │   └── Menu/
│   ├── FSM/
│   │   ├── States/
│   │   │   └── Views/
│   │   └── Transitions/
│   └── Backoffice/
│       ├── Dashboard/
│       └── FSM/
│           ├── States/
│           └── Transitions/
├── MIDDLE/
│   ├── Rest/
│   ├── Security/
│   ├── Router/
│   ├── Request/
│   ├── Response/
│   └── FSM/
│       └── Transitions/
├── BACK/
│   ├── Action/
│   ├── Service/
│   ├── Repository/
│   ├── Runner/
│   ├── Job/
│   ├── Worker/
│   └── FSM/
│       └── Transitions/
└── COMMON/
    └── FSM/
        └── Engine/
```

## UML package diagram

```mermaid
classDiagram
    namespace FRONT_UI {
        class ViewState
        class UIAction
        class Component
        class Form
        class Menu
        class Link
        class Breadcrumb
        class BackofficeDashboard
        class BlockedStateReviewView
    }

    namespace MIDDLE_TRANSPORT_RIGHTS {
        class RestRequest
        class RestResponse
        class Router
        class SecurityPipeline
        class ACLTransition
        class SSOTransition
        class FsmGate
    }

    namespace BACK_EXECUTION {
        class BackAction
        class Service
        class Repository
        class Runner
        class Job
        class Worker
        class BusinessTransition
    }

    namespace COMMON_FSM_ENGINE {
        class FsmEngine
        class State
        class Signal
        class TransitionDefinition
        class TransitionResult
        class FsmTrace
        class TypedError
    }

    Component <|-- Form
    Component <|-- Menu
    Component <|-- Link
    Component <|-- Breadcrumb
    ViewState <|-- BackofficeDashboard
    ViewState <|-- BlockedStateReviewView

    UIAction --> FsmEngine : signal
    FsmEngine --> RestRequest : internal action requires transport
    RestRequest --> SecurityPipeline : REST boundary
    SecurityPipeline --> ACLTransition : rights transition
    SecurityPipeline --> SSOTransition : identity transition
    SecurityPipeline --> FsmGate : transition checkpoint
    FsmGate --> BackAction : approved backend command
    BackAction --> BusinessTransition : backend transition
    BusinessTransition --> Service : execute
    Service --> Repository : data access
    Service --> Runner : optional execution
    BackAction --> RestResponse : typed result
    RestResponse --> FsmEngine : resolve next UI state
    FsmEngine --> ViewState : same or next view
```

## End-to-end sequence

```mermaid
sequenceDiagram
    actor User

    box FRONT UI
        participant View as Current UI View State
        participant Component as Component/Form/Menu/Link
        participant FrontTransition as FRONT FSM Transition Fuel
        participant Backoffice as Backoffice Dashboard View
    end

    box MIDDLE Transport + Rights
        participant REST as REST Route/Gateway
        participant SSO as SSO Transition
        participant ACL as ACL Transition
        participant MiddleTransition as MIDDLE FSM Transition Fuel
    end

    box BACK Execution
        participant BackTransition as BACK FSM Transition Fuel
        participant Action as Backend Action
        participant Service as Service
        participant Repository as Repository/DB
        participant Runner as Runner/Job/Worker
    end

    box COMMON Processor
        participant Engine as FSM Engine
    end

    User->>Component: UI action
    Component->>Engine: current view state + action signal
    Engine->>FrontTransition: evaluate UI transition

    alt internal link or action
        Engine->>REST: build REST request
        REST->>Engine: route transition signal
        Engine->>MiddleTransition: route matched
        REST->>SSO: SSO/session transition
        REST->>ACL: ACL/permission transition
        ACL->>Engine: allowed or denied
        SSO->>Engine: authenticated or required

        alt SSO and ACL accepted
            Engine->>BackTransition: backend transition signal
            BackTransition->>Action: execute only if accepted
            Action->>Service: business processing
            Service->>Repository: optional data access
            Service->>Runner: optional job/runner/worker
            Runner-->>Service: execution result
            Repository-->>Service: data result
            Service-->>Action: typed business result
            Action-->>Engine: transition result
            Engine-->>REST: response contract
            REST-->>Engine: resolve next UI state
            Engine-->>View: next view or same view with updated data
        else denied or required
            Engine-->>REST: explicit refusal result
            REST-->>Engine: typed denial response
            Engine-->>View: LoginView / ForbiddenView / ErrorView / same view
        end
    else external link
        Component-->>User: browser external navigation
    end

    alt transgression in any layer
        Engine-->>Backoffice: blocked FSM state for admin review
        Engine-->>View: BlockedView or safe error state
    end
```

## UI state diagram

```mermaid
stateDiagram-v2
    [*] --> HomeView

    HomeView --> CatalogView: OPEN_CATALOG / REST + SSO_OK + ACL_OK + BACK_OK
    HomeView --> LoginView: OPEN_CATALOG / SSO_REQUIRED
    HomeView --> ForbiddenView: OPEN_CATALOG / ACL_DENIED
    HomeView --> BlockedView: OPEN_CATALOG / CONTRACT_VIOLATION

    CatalogView --> CatalogView: SEARCH / REST + BACK_OK + SAME_VIEW
    CatalogView --> ProductView: OPEN_PRODUCT / REST + SSO_OK + ACL_OK + BACK_OK
    CatalogView --> ErrorView: SEARCH / BACK_ERROR
    CatalogView --> BlockedView: INVALID_TRANSITION

    ProductView --> CatalogView: BACK_TO_CATALOG
    LoginView --> HomeView: LOGIN_OK
    ForbiddenView --> HomeView: ACK_FORBIDDEN
    ErrorView --> CatalogView: ACK_ERROR

    BlockedView --> AdminDashboardView: ADMIN_REVIEW_REQUIRED
    AdminDashboardView --> BlockedView: ADMIN_REPAIR_PENDING
    AdminDashboardView --> HomeView: ADMIN_UNBLOCKED
    AdminDashboardView --> ForbiddenView: ADMIN_REJECTED

    HomeView --> ExternalBrowser: EXTERNAL_LINK
    CatalogView --> ExternalBrowser: EXTERNAL_LINK
```

## FSM transition ownership

`COMMON/FSM/Engine` is the processor. It consumes transition definitions but never owns the layer-specific fuel.

```text
COMMON/FSM/Engine
= reusable processor
= state/signal/transition/result/trace primitives
= no UI view transitions
= no REST ACL SSO transitions
= no business transitions
= no application transitions
```

```text
FRONT/FSM/States/Views
= UI view states
= HomeView, CatalogView, ProductView, LoginView, ForbiddenView, BlockedView
```

```text
FRONT/FSM/Transitions
= UI action transitions
= OPEN_CATALOG, OPEN_PRODUCT, SEARCH, BACK_TO_CATALOG, ACK_ERROR
```

```text
MIDDLE/FSM/Transitions
= transport and rights transitions
= REST_REQUEST_RECEIVED, ROUTE_MATCHED, SSO_OK, SSO_REQUIRED, ACL_OK, ACL_DENIED, CSRF_DENIED
```

```text
BACK/FSM/Transitions
= business and execution transitions
= BACK_ACTION_REQUESTED, SERVICE_EXECUTED, REPOSITORY_UPDATED, JOB_QUEUED, RUNNER_FAILED
```

```text
FRONT/Backoffice/FSM/States
= admin UI states
= AdminDashboardView, AdminBlockedStatesView, AdminTransitionInspectorView
```

```text
FRONT/Backoffice/FSM/Transitions
= admin actions
= ADMIN_REVIEW_REQUIRED, ADMIN_UNBLOCKED, ADMIN_REJECTED, ADMIN_REPAIR_PENDING
```

## Blocked state rule

Any transgression in any layer creates a blocked FSM state. It is never silently fixed.

Examples:

```text
BLOCKED_BY_INVALID_TRANSITION
BLOCKED_BY_CONTRACT_VIOLATION
BLOCKED_BY_ACL_VIOLATION
BLOCKED_BY_SSO_REQUIRED
BLOCKED_BY_CSRF_FAILURE
BLOCKED_BY_BACK_EXCEPTION
BLOCKED_BY_RUNNER_FAILURE
BLOCKED_BY_DATA_VALIDATION_ERROR
```

The blocked state is visible in the application's backoffice dashboard.

The dashboard is not BACK. The dashboard is FRONT admin UI.

## Link rule

`Link` is a FRONT component.

Internal link:

```text
FRONT/Component/Link -> FRONT action signal -> FSM -> MIDDLE REST + ACL + SSO -> BACK -> MIDDLE -> FRONT
```

External link:

```text
FRONT/Component/Link -> browser external navigation
```

External link is the only explicit exception allowed to bypass the OPUS internal transition pipeline.

## Non-negotiable documentation rule

Mermaid diagrams are mandatory for this model.

Every architecture feature that touches FSM state processing must include:

1. a Mermaid package/class diagram;
2. a Mermaid sequence diagram;
3. a Mermaid `stateDiagram-v2`;
4. machine-readable FSM transition fixtures;
5. a smoke that verifies the documentation and fixture markers.
