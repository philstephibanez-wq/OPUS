# P117SITE25E — Boundary classification tuning

## Status

DELIVERED.

## Goal

Refine the physical OPUS boundary tree after P117SITE25D.

The target tree remains visually strict:

```text
framework/Opus/
├── FRONT/
├── MIDDLE/
├── BACK/
└── COMMON/
```

But the inner classification is corrected:

- `Breadcrumb`, `Form`, and `Menu` are FRONT components and must live under `FRONT/Component/*`.
- `Fsm` is a shared mandatory processor and must live under `COMMON/FSM/Engine`.
- `MIDDLE` owns the FSM gates and transport orchestration, not the common FSM engine.
- `Lstsa` is a processing / transform engine and must live under `BACK/Lstsa`.
- `COMMON` remains strict shared language and shared processors only; it must never become a catch-all.

## UML package diagram

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

## FSM transition diagram

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

## End-to-end rule

```text
FRONT intent
  -> MIDDLE route + security + FSM gate
    -> COMMON FSM engine evaluates signal/state/action/transition
      -> MIDDLE dispatch allowed or denied
        -> BACK action only if transition is allowed
```

No operation path may bypass the FSM.

## Applied physical moves

```text
FRONT/Breadcrumb      -> FRONT/Component/Breadcrumb
FRONT/Form            -> FRONT/Component/Form
FRONT/Menu            -> FRONT/Component/Menu
COMMON/Lstsa          -> BACK/Lstsa
MIDDLE/FSM/Engine     -> COMMON/FSM/Engine
```

## Validation

Run:

```cmd
python tools\refactor_p117site25e_boundary_classification_tuning.py --write
python tools\smoke_p117site25e_boundary_classification_tuning.py
```

Expected final marker:

```text
P117SITE25E_BOUNDARY_CLASSIFICATION_TUNING_SMOKE_OK
```
