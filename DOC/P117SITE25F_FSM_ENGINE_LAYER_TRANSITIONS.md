# P117SITE25F — FSM engine, layer transitions and REST security chain

## Status

DELIVERED.

## Goal

Clarify and enforce the OPUS architecture where the FSM engine is shared but the transition fuel is dedicated to each boundary and each generated application.

This corrects the previous ambiguity:

- `COMMON/FSM/Engine` owns the reusable FSM processor.
- `COMMON/FSM/Engine` must not contain layer-specific or application-specific transitions.
- `FRONT/FSM/Transitions` owns UI and representation transitions.
- `MIDDLE/FSM/Transitions` owns routing, REST transport and security transitions.
- `BACK/FSM/Transitions` owns business, data, jobs, workers and external-adapter transitions.
- A generated application may declare its own `frontend/fsm/transitions`, `middle/fsm/transitions` and `backend/fsm/transitions`.
- `Link` is a `FRONT/Component`, like `Breadcrumb`, `Form` and `Menu`.

## Physical target

```text
framework/Opus/
├── FRONT/
│   ├── Component/
│   │   ├── Breadcrumb/
│   │   ├── Form/
│   │   ├── Link/
│   │   └── Menu/
│   └── FSM/
│       └── Transitions/
├── MIDDLE/
│   └── FSM/
│       └── Transitions/
├── BACK/
│   └── FSM/
│       └── Transitions/
└── COMMON/
    └── FSM/
        └── Engine/
```

## Package UML

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
    RestTransport --> SecurityPipeline : validate
    SecurityPipeline --> FsmGate : ACL + SSO + FSM
    FsmGate --> ApiGateway : allow only if FSM allows
    ApiGateway --> Action : dispatch backend command
```

## End-to-end sequence

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

## FSM transition diagram

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

    MIDDLE_ROUTE_MATCHED --> MIDDLE_DENIED: ACL_DENIED
    MIDDLE_ACL_CHECKED --> MIDDLE_DENIED: SSO_DENIED
    MIDDLE_SSO_CHECKED --> MIDDLE_DENIED: MIDDLE_TRANSITION_DENIED
    BACK_ACTION_REQUESTED --> BACK_DENIED: BACK_TRANSITION_DENIED
    MIDDLE_DENIED --> FRONT_VIEW_UPDATED: SAFE_ERROR_RESPONSE
    BACK_DENIED --> MIDDLE_RESPONSE_BUILT: SAFE_ERROR_RESPONSE
```

## Non-negotiable documentation rule

Mermaid diagrams are not optional.

Each architecture feature must include:

1. a package/class diagram when boundaries or class responsibilities change;
2. a sequence diagram when a flow crosses boundaries;
3. a `stateDiagram-v2` when FSM transitions are involved;
4. a machine-readable transition definition whenever FSM behavior is introduced or modified;
5. a smoke that verifies the expected documentation markers.

## REST + FSM + ACL + SSO rule

The mandatory end-to-end chain is:

```text
FRONT -> MIDDLE -> BACK -> MIDDLE -> FRONT
```

The transport between `FRONT` and `MIDDLE` is REST. The `MIDDLE` layer owns the REST route, request contract, response contract, ACL check, SSO check, security pipeline and FSM gate.

`BACK` never receives a direct call from `FRONT`. It receives only a command already authorized by `MIDDLE` and accepted by the FSM engine using BACK-specific transition definitions.

## COMMON rule

`COMMON/FSM/Engine` is the shared processor. It may contain FSM engine contracts, states, signals, transition definitions, traces and typed result primitives.

`COMMON/FSM/Engine` must not contain:

- frontend transition files;
- middle transition files;
- backend transition files;
- generated application transitions;
- module-specific transitions.

Layer and application transition files are fuel. The engine consumes them but does not own them.
