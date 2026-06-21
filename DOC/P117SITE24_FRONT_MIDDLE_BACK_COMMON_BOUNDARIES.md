# P117SITE24 — FRONT / MIDDLE / BACK / COMMON boundaries

## Goal

Make OPUS visibly secure and clean by design at framework-tree level. The framework must show clear architectural boundaries instead of hiding them in generic folders.

## Mandatory framework tree

```text
framework/Opus/
├── FRONT/
├── MIDDLE/
│   └── FSM/
├── BACK/
└── COMMON/
```

## Boundary rules

- `FRONT` owns representation only.
- `MIDDLE` owns route transport, request/response contracts, API boundary, checks, audit and FSM gates.
- `BACK` owns business processing, data, runners, jobs, workers and external adapters.
- `COMMON` owns strict shared language only.
- `COMMON` is never a catch-all folder.
- The FSM is the mandatory processor for every operation path.

## UML package diagram

```mermaid
classDiagram
    namespace FRONT {
      class View
      class Layout
      class Section
      class Component
      class ApiClient
    }

    namespace MIDDLE {
      class Router
      class ApiGateway
      class MiddlewarePipeline
      class FsmGate
      class RequestContract
      class ResponseContract
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
      class BoundaryContractInterface
      class RequestEnvelope
      class ResponseEnvelope
      class OperationResult
      class TypedError
      class LayerName
    }

    View --> ApiClient
    ApiClient --> Router
    Router --> MiddlewarePipeline
    MiddlewarePipeline --> FsmGate
    FsmGate --> ApiGateway
    ApiGateway --> Action
    Action --> Service
    Service --> Repository
    Service --> Runner
    Action --> ResponseContract

    FRONT ..> COMMON
    MIDDLE ..> COMMON
    BACK ..> COMMON
```

## End-to-end sequence

```mermaid
sequenceDiagram
    actor User
    participant V as FRONT View
    participant C as FRONT Component
    participant A as FRONT ApiClient
    participant R as MIDDLE Router
    participant P as MIDDLE Pipeline
    participant F as MIDDLE FSM Gate
    participant G as MIDDLE ApiGateway
    participant X as BACK Action
    participant S as BACK Service
    participant D as BACK Repository

    User->>V: interaction
    V->>C: collect input
    C->>A: submit intention
    A->>R: request envelope
    R->>P: matched route
    P->>F: checked request
    F->>F: FSM transition decision
    alt transition allowed
        F->>G: dispatch allowed operation
        G->>X: execute backend action
        X->>S: process business case
        S->>D: access data
        D-->>S: data
        S-->>X: result
        X-->>G: response contract
        G-->>A: response envelope
        A-->>V: update view model
    else transition denied
        F-->>A: typed error
        A-->>V: render explicit denial
    end
```

## FSM transition graph

```mermaid
stateDiagram-v2
    [*] --> IDLE
    IDLE --> INTENT_RECEIVED: FRONT_INTENT
    INTENT_RECEIVED --> ROUTE_MATCHED: MATCH_ROUTE
    ROUTE_MATCHED --> CHECKED: CHECK_PIPELINE
    CHECKED --> ALLOWED: FSM_ALLOW
    CHECKED --> DENIED: FSM_DENY
    ALLOWED --> BACK_RUNNING: DISPATCH_BACK
    BACK_RUNNING --> RESPONSE_READY: BUILD_RESPONSE
    RESPONSE_READY --> IDLE: RESPONSE_SENT
    DENIED --> IDLE: ERROR_RETURNED
```

## COMMON anti catch-all rule

A file may enter `COMMON` only if all statements are true:

1. It is shared language used across at least two layers.
2. It contains no rendering logic.
3. It contains no route dispatch logic.
4. It contains no access-control decision.
5. It contains no business workflow.
6. It contains no repository, database, runner, job or worker logic.
7. It is stable and reusable.

If one statement is false, the file belongs in `FRONT`, `MIDDLE` or `BACK`, not in `COMMON`.

## Autodocumentation contract

Every architecture-bearing feature must provide:

- a human documentation page under `DOC/`,
- a Mermaid package or class diagram,
- a Mermaid sequence diagram for the request path when relevant,
- a Mermaid FSM diagram when a transition path exists,
- a machine-readable FSM transition contract when transitions are part of runtime behavior,
- a smoke test proving the docs and machine-readable contracts are present.
