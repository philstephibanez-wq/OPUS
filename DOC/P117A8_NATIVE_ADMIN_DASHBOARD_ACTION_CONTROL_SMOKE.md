# P117A8 — Native Admin Dashboard Action Control Smoke

Status: PENDING RUNTIME VALIDATION

## Goal

Validate the first native OPUS administrator dashboard action control smoke.

Action under test:

```text
ADMIN_ACKNOWLEDGE_BLOCKED_STATE
```

## Expected runtime smoke

```text
ok=true
gate=P117A8_NATIVE_ADMIN_DASHBOARD_ACTION_CONTROL_SMOKE
allowed_action=ADMIN_ACKNOWLEDGE_BLOCKED_STATE
allowed_granted=true
allowed_effect=blocked_state_acknowledged
denied_granted=false
denied_reason=ADMIN_DASHBOARD_ACTION_SCOPE_DENIED
denied_public_status=503
denied_is_public_response=true
denied_public_body=Site temporairement bloqué. | Contactez le support.
```

## Runtime files

```text
framework/Opus/Admin/AdminDashboardActionRequest.php
framework/Opus/Admin/AdminDashboardActionDecision.php
framework/Opus/Admin/AdminDashboardActionControlPlane.php
framework/Opus/Runtime/NativeAdminDashboardActionControlSmoke.php
```
