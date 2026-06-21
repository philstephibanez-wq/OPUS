# P117SITE25 — OPUS physical FRONT / MIDDLE / BACK / COMMON reorganization

## Status

DELIVERED — physical reorganization runner and smoke delivered.

## Intent

OPUS must visually and physically expose its architectural boundaries at framework level:

```text
framework/Opus/
├── FRONT/
├── MIDDLE/
├── BACK/
└── COMMON/
```

This is not only documentation. The source tree must make the architectural boundaries obvious.

## Non-negotiable rules

- FRONT owns representation only.
- MIDDLE owns routing, transport, security, contracts and FSM gates.
- BACK owns business processing, data access, jobs, runners, workers and external integrations.
- COMMON owns only minimal shared language: contracts, DTO, value objects, typed errors, results, enums, identifiers and pure technical primitives.
- COMMON must never become a catch-all.
- The FSM is the processor at every level. No processing path bypasses it.
- Mermaid UML and FSM transition diagrams are mandatory in architecture documentation.

## Physical boundary map

```mermaid
flowchart TB
    Root[framework/Opus]
    Root --> FRONT[FRONT]
    Root --> MIDDLE[MIDDLE]
    Root --> BACK[BACK]
    Root --> COMMON[COMMON]

    FRONT --> FView[View / Template / Theme / Renderer]
    FRONT --> FComponent[Form / Menu / Link / Javascript]

    MIDDLE --> MRoute[Router / Routing / Http / Rest]
    MIDDLE --> MTransport[Request / Response / Uri / Server / Session]
    MIDDLE --> MSec[Security]
    MIDDLE --> MFSM[FSM]

    BACK --> BBusiness[Module / Model / Database / Site]
    BACK --> BAuthoring[Scaffold / Package / RefBook / PublicSite]
    BACK --> BSystem[Runtime / Console / Mail / Smtp / Ftp]

    COMMON --> CShared[Contract / Dto / ValueObject / Error / Result]
    COMMON --> CInfra[Date / File / Directory / Json / Xml / Validation]
```

## End-to-end secure and clean path

```mermaid
sequenceDiagram
    actor User
    participant Front as OPUS FRONT\nView/Component/ApiClient
    participant Middle as OPUS MIDDLE\nRouter/Security/FSM
    participant Fsm as FSM Processor
    participant Back as OPUS BACK\nAction/Service/Repository
    participant Common as OPUS COMMON\nDTO/Result/TypedError

    User->>Front: intent
    Front->>Common: build typed Request DTO
    Front->>Middle: request
    Middle->>Middle: route + transport validation
    Middle->>Middle: ACL / SSO / CSRF / rate limit / audit
    Middle->>Fsm: signal + current state
    Fsm-->>Middle: transition allowed / denied
    alt allowed
        Middle->>Back: dispatch approved action
        Back->>Back: business processing
        Back->>Common: response DTO / result
        Common-->>Middle: typed response
        Middle-->>Front: response
        Front-->>User: render view
    else denied
        Fsm->>Common: typed error
        Common-->>Front: denied response
        Front-->>User: explicit error
    end
```

## FSM transition contract

```mermaid
stateDiagram-v2
    [*] --> FRONT_INTENT
    FRONT_INTENT --> MIDDLE_ROUTE_MATCHED: OPUS_FRONT_INTENT
    MIDDLE_ROUTE_MATCHED --> MIDDLE_SECURITY_CHECKED: OPUS_ROUTE_ACCEPTED
    MIDDLE_SECURITY_CHECKED --> MIDDLE_FSM_APPROVED: OPUS_SECURITY_OK
    MIDDLE_SECURITY_CHECKED --> MIDDLE_DENIED: OPUS_SECURITY_DENIED
    MIDDLE_FSM_APPROVED --> BACK_ACTION_DISPATCHED: OPUS_FSM_TRANSITION_ALLOWED
    MIDDLE_FSM_APPROVED --> MIDDLE_DENIED: OPUS_FSM_TRANSITION_DENIED
    BACK_ACTION_DISPATCHED --> BACK_ACTION_COMPLETED: OPUS_BACK_ACTION_OK
    BACK_ACTION_DISPATCHED --> BACK_ACTION_FAILED: OPUS_BACK_ACTION_FAIL
    BACK_ACTION_COMPLETED --> FRONT_RESPONSE_RENDERED: OPUS_RESPONSE_READY
    BACK_ACTION_FAILED --> FRONT_ERROR_RENDERED: OPUS_ERROR_READY
    MIDDLE_DENIED --> FRONT_ERROR_RENDERED: OPUS_DENIED_RESPONSE
    FRONT_RESPONSE_RENDERED --> [*]
    FRONT_ERROR_RENDERED --> [*]
```

## Runner

The migration is intentionally performed by a local runner because it physically moves directories in the working tree.

```cmd
python tools\refactor_p117site25_front_middle_back_common_tree.py --write
```

The runner:

- refuses to run if the git tree is dirty;
- creates FRONT / MIDDLE / BACK / COMMON;
- moves known legacy root folders into the correct layer;
- refuses unknown root framework directories instead of hiding them in COMMON;
- patches composer.json by adding a classmap for the moved source tree;
- runs composer dump-autoload;
- writes framework/Opus/BOUNDARY_MAP.json.

## Smoke

```cmd
python tools\smoke_p117site25_front_middle_back_common_tree.py
```

Expected markers:

```text
CHECK_ONLY_BOUNDARY_ROOTS=OK
CHECK_FRONT_MAPPED=OK
CHECK_MIDDLE_MAPPED=OK
CHECK_BACK_MAPPED=OK
CHECK_COMMON_MAPPED=OK
CHECK_COMMON_NOT_CATCH_ALL=OK
CHECK_COMPOSER_CLASSMAP=OK
CHECK_MERMAID_UML_DOC=OK
CHECK_FSM_TRANSITION_DOC=OK
P117SITE25_FRONT_MIDDLE_BACK_COMMON_TREE_SMOKE_OK
```
