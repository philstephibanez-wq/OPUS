# OPUS MIDDLE boundary

`OPUS\\MIDDLE` owns routing, transport, request/response contracts, security pipeline, API boundary, audit, rate limiting and FSM gates.

Allowed:

- routers and routes
- API gateway
- request contracts and response contracts
- FSM processor and transition gates
- ACL, SSO, CSRF, rate limit and audit
- middleware pipeline

Forbidden:

- visual rendering
- frontend components
- business services and repositories
- direct UI decisions
- database domain persistence

MIDDLE receives FRONT intentions, converts them into FSM signals, enforces security, then dispatches only approved operations to BACK.
