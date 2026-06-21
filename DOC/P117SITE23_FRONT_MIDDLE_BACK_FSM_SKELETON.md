# P117SITE23 — Front / Middle / Back / FSM skeleton

## Goal

Make the OPUS framework tree reflect the contract used by generated applications:

- `Opus\\FRONT` owns representation.
- `Opus\\MIDDLE` owns routing, transport, request and response boundaries, and FSM gates.
- `Opus\\BACK` owns business domains, data access, services, runners, jobs, and integrations.
- The FSM is the mandatory processor for every operation path.

## P117SITE24 continuation

P117SITE24 extends this first skeleton with a visible `COMMON` boundary and mandatory Mermaid + FSM documentation.

See:

- `DOC/P117SITE24_FRONT_MIDDLE_BACK_COMMON_BOUNDARIES.md`
- `framework/Opus/COMMON/README.md`
- `framework/Opus/MIDDLE/FSM/fsm.transitions.json`

## Framework structure

```text
framework/Opus/FRONT
framework/Opus/MIDDLE
framework/Opus/MIDDLE/FSM
framework/Opus/BACK
framework/Opus/COMMON
```

## End-to-end rule

```text
FRONT View / Component / API client
  -> MIDDLE route / request contract / FSM gate
    -> BACK action / service / repository / runner / job
      -> MIDDLE response contract
        -> FRONT rendering
```

No FRONT artifact owns business logic.
No BACK artifact renders HTML directly.
Every operation path is represented by the FSM contract.
