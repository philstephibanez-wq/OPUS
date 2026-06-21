# P117SITE25C — OPUS physical FRONT / MIDDLE / BACK / COMMON tree

## Status

DELIVERED.

This milestone fixes the previous partial state where boundary folders existed but the legacy root directories still remained directly under `framework/Opus`.

The target is a physically visible framework tree:

```text
framework/Opus/
├── FRONT/
├── MIDDLE/
├── BACK/
└── COMMON/
```

No other root directory is allowed under `framework/Opus`, except limited root files such as `README.md`, `BOUNDARY_MAP.json`, and boundary documentation files.

## Non-negotiable rules

- `FRONT` is representation only.
- `MIDDLE` is routing, transport, security, request/response contracts, FSM gates and orchestration.
- `BACK` is business processing, modules, data access, runners, jobs, workers and external adapters.
- `COMMON` is strict shared language only.
- `COMMON` is not a catch-all.
- Every end-to-end operation must pass through the FSM.
- Unknown root directories are refused. They are not silently moved to `COMMON`.

## Mermaid package diagram

```mermaid
classDiagram
    namespace FRONT {
      class View
      class Layout
      class Section
      class Component
      class FormComponent
      class MenuComponent
      class ApiClient
    }

    namespace MIDDLE {
      class Router
      class Request
      class Response
      class ApiGateway
      class MiddlewarePipeline
      class FsmGate
      class AccessControl
      class AuditTrail
    }

    namespace BACK {
      class Module
      class Action
      class Service
      class Repository
      class Runner
      class Job
      class Worker
      class ExternalAdapter
    }

    namespace COMMON {
      class Contract
      class Dto
      class ValueObject
      class TypedError
      class Result
      class Identifier
    }

    View --> ApiClient
    ApiClient --> Router
    Router --> FsmGate
    FsmGate --> Action
    Action --> Service
    Service --> Repository
    Service --> Runner
    Action --> Response
```

## Mermaid end-to-end sequence

```mermaid
sequenceDiagram
    actor User
    participant View as FRONT View
    participant ApiClient as FRONT ApiClient
    participant Router as MIDDLE Router
    participant Security as MIDDLE SecurityPipeline
    participant FSM as MIDDLE FSM Gate
    participant Action as BACK Action
    participant Service as BACK Service
    participant Repository as BACK Repository

    User->>View: interaction
    View->>ApiClient: typed intent
    ApiClient->>Router: request
    Router->>Security: route match
    Security->>FSM: transition request
    FSM->>FSM: validate signal/state/action
    alt allowed
      FSM->>Action: dispatch command
      Action->>Service: execute business rule
      Service->>Repository: access data
      Repository-->>Service: data
      Service-->>Action: result
      Action-->>Router: response DTO
      Router-->>ApiClient: response
      ApiClient-->>View: view model
    else denied
      FSM-->>Router: typed error
      Router-->>ApiClient: denied response
      ApiClient-->>View: display explicit error
    end
```

## Mermaid FSM transition contract

```mermaid
stateDiagram-v2
    [*] --> IntentReceived
    IntentReceived --> RouteResolved: ROUTE_MATCHED
    RouteResolved --> SecurityChecked: SECURITY_CHECK
    SecurityChecked --> FsmApproved: FSM_ALLOW
    SecurityChecked --> FsmDenied: FSM_DENY
    FsmApproved --> BackActionDispatched: DISPATCH_BACK_ACTION
    BackActionDispatched --> ResponseBuilt: BUILD_RESPONSE
    ResponseBuilt --> Rendered: RETURN_VIEWMODEL
    FsmDenied --> ErrorResponseBuilt: BUILD_TYPED_ERROR
    ErrorResponseBuilt --> Rendered
```

## Validation

Run:

```cmd
python tools\refactor_p117site25c_front_middle_back_common_physical_tree.py --write
python tools\smoke_p117site25c_front_middle_back_common_physical_tree.py
```

Expected final marker:

```text
P117SITE25C_FRONT_MIDDLE_BACK_COMMON_PHYSICAL_TREE_SMOKE_OK
```
