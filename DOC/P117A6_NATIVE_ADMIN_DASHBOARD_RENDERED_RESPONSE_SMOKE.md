# P117A6 - Native Admin Dashboard Rendered Response Smoke

## Status

PENDING RUNTIME VALIDATION.

## Goal

P117A6 proves that the native OPUS administrator dashboard route can produce a rendered administrator response after the route has passed the OPUS admin control plane.

This is still a smoke gate, not the final dashboard UI.

## Contract

The administrator dashboard is part of OPUS. It is not an external tool and not an alternate channel.

The route must keep the separation between:

- public opaque blocked response;
- protected administrator diagnostics;
- internal audit/report data.

## Added runtime elements

```text
framework/Opus/Admin/AdminDashboardResponse.php
framework/Opus/Admin/AdminBlockedStatesDashboardResponseRenderer.php
framework/Opus/Runtime/NativeAdminDashboardRenderedResponseSmoke.php
```

## Expected smoke command

```cmd
php -r "$boot=require 'index.php'; $r=\Opus\Runtime\NativeAdminDashboardRenderedResponseSmoke::run(); foreach (['ok','gate','admin_status','admin_content_type','admin_surface_header','admin_body_contains_dashboard','admin_body_contains_blocked_state','denied_status','denied_is_public_response'] as $k) { echo $k.'='.(is_bool($r[$k]) ? ($r[$k] ? 'true' : 'false') : $r[$k]).PHP_EOL; } echo 'denied_public_body='.str_replace(\"\n\", ' | ', $r['denied_public_body']).PHP_EOL;"
```

## Expected result

```text
ok=true
gate=P117A6_NATIVE_ADMIN_DASHBOARD_RENDERED_RESPONSE_SMOKE
admin_status=200
admin_content_type=text/html; charset=utf-8
admin_surface_header=admin_dashboard
admin_body_contains_dashboard=true
admin_body_contains_blocked_state=true
denied_status=503
denied_is_public_response=true
denied_public_body=Site temporairement bloqué. | Contactez le support.
```

## Public opacity

When access is not authorized, OPUS must render only the neutral public support response. Administrator fields remain available only inside protected OPUS surfaces.
