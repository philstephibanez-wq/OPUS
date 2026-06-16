# P117A3 — FSM Blocked-State Event Model

## Status

Delivered for runtime smoke validation.

## Purpose

P117A3 introduces a first explicit blocked-state event model for OPUS public routing.

The model keeps OPUS security opaque for public users while preserving the data required by administrators, logs and future dashboard screens.

## Public rule

Public responses must remain non-exploitable.

The only public blocked response is:

```text
Site temporairement bloqué.
Contactez le support.
```

No FSM, ACL, route, token, class, file path, stack trace, configuration, database or tool diagnostic may be exposed publicly.

## Internal rule

The blocked-state event carries administrator diagnostics separately:

```text
- event_id
- site
- route_key
- blocked_state
- reason
- admin_action
- severity
```

These fields are for protected administrator dashboard, logs, reports and notification bridges only.

## Layer separation

```text
FSM / ACL / SSO-like control plane
-> produces decision and blocked-state event

Public renderer
-> consumes only the event public body
-> does not expose diagnostics

Admin dashboard / logs
-> consume event diagnostics
-> protected by OPUS control plane
```

## Delivered runtime classes

```text
framework/Opus/Security/BlockedStateEvent.php
framework/Opus/Security/PublicControlDecision.php
framework/Opus/Security/PublicRouteControlPlane.php
framework/Opus/Security/PublicBlockedResponseRenderer.php
framework/Opus/Runtime/BlockedStateEventSmoke.php
framework/Opus/Runtime/PublicRouteMvcSmoke.php
```

## Smoke gate

```text
P117A3_FSM_BLOCKED_STATE_EVENT_MODEL
```

Expected runtime smoke output:

```text
ok=true
gate=P117A3_FSM_BLOCKED_STATE_EVENT_MODEL
public_status=503
public_body=Site temporairement bloqué.
Contactez le support.
blocked_state=PUBLIC_REQUEST_BLOCKED
reason=UNKNOWN_PUBLIC_ROUTE
admin_action=ADMIN_VIEW_BLOCKED_STATES
```
