# P117A4 — Admin Blocked State Dashboard ViewModel

Status: runtime smoke pending
Scope: OPUS, FSM bastion, admin dashboard, public error opacity

## Purpose

P117A4 introduces the first administrator-only dashboard view model for OPUS blocked-state events.

The objective is not to build the full dashboard yet. The objective is to prove the separation:

```text
Public user surface = opaque support-only message.
Admin dashboard surface = structured operational diagnostics.
Internal framework = auditable blocked-state event.
```

## Security rule

The public user must never receive technical details, route details, FSM state names, ACL data, class names, configuration names, filesystem paths, stack traces or tool internals.

The public blocked response remains:

```text
Site temporairement bloqué.
Contactez le support.
```

## Admin dashboard rule

The administrator dashboard may receive protected diagnostics only after its own OPUS FSM/ACL/SSO-like control plane authorizes the operator.

The dashboard is not a bypass.

It is an OPUS application protected by OPUS.

## Added component

```text
framework/Opus/Admin/AdminBlockedStateViewModel.php
```

Role:

```text
Represent administrator-only diagnostics for a blocked FSM state event.
```

Responsibilities:

```text
- consume BlockedStateEvent
- produce dashboard-ready structured data
- preserve public error opacity
- keep admin diagnostics outside public responses
```

## Smoke

```text
framework/Opus/Runtime/AdminBlockedStateDashboardViewModelSmoke.php
```

The smoke proves:

```text
- a blocked-state event exists
- the public response stays opaque
- admin diagnostics are structured
- public response does not contain blocked_state, reason, admin_action, site or route details
- the admin ViewModel exposes the required dashboard fields
```

## Expected smoke

```cmd
php -r "$boot=require 'index.php'; $r=\Opus\Runtime\AdminBlockedStateDashboardViewModelSmoke::run(); foreach (['ok','gate','public_status','admin_surface','admin_blocked_state','admin_reason','admin_action','admin_public_user_message_policy'] as $k) { echo $k.'='.(is_bool($r[$k]) ? ($r[$k] ? 'true' : 'false') : $r[$k]).PHP_EOL; } echo 'public_body='.str_replace(\"\n\", ' | ', $r['public_body']).PHP_EOL;"
```

Expected result:

```text
ok=true
gate=P117A4_ADMIN_BLOCKED_STATE_DASHBOARD_VIEWMODEL
public_status=503
admin_surface=admin_dashboard
admin_blocked_state=PUBLIC_REQUEST_BLOCKED
admin_reason=UNKNOWN_PUBLIC_ROUTE
admin_action=ADMIN_VIEW_BLOCKED_STATES
admin_public_user_message_policy=opaque_support_only
public_body=Site temporairement bloqué. | Contactez le support.
```

## Next step

P117A5 should start the protected admin dashboard route/model layer or the site declaration/config route profile, depending on the chosen execution order.
