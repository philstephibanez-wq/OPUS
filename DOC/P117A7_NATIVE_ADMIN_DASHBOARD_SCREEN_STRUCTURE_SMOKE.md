# P117A7 - Native Admin Dashboard Screen Structure Smoke

## Status

PENDING RUNTIME VALIDATION.

## Goal

P117A7 proves that the rendered native OPUS administrator dashboard response exposes a stable dashboard screen structure around protected administrator data.

This gate keeps P117A6 rendering, but adds explicit screen regions that future UI/CSS/ScoreTemplate work can target without guessing.

## Contract

The administrator dashboard is native OPUS.

The dashboard screen structure is administrator-only and must only exist after the admin route has passed the OPUS admin control plane.

Denied or public contexts must still receive only the opaque public support message.

## Required screen regions

```text
admin_header
blocked_state_summary
blocked_state_detail
recommended_actions
admin_audit_footer
```

## Added runtime elements

```text
framework/Opus/Admin/AdminDashboardScreenStructure.php
framework/Opus/Runtime/NativeAdminDashboardScreenStructureSmoke.php
```

## Expected smoke command

```cmd
php -r "$boot=require 'index.php'; $r=\Opus\Runtime\NativeAdminDashboardScreenStructureSmoke::run(); foreach (['ok','gate','admin_status','admin_screen_header','screen_has_header_region','screen_has_summary_region','screen_has_detail_region','screen_has_actions_region','screen_has_footer_region','denied_status','denied_is_public_response'] as $k) { echo $k.'='.(is_bool($r[$k]) ? ($r[$k] ? 'true' : 'false') : $r[$k]).PHP_EOL; } echo 'denied_public_body='.str_replace(\"\n\", ' | ', $r['denied_public_body']).PHP_EOL;"
```

## Expected result

```text
ok=true
gate=P117A7_NATIVE_ADMIN_DASHBOARD_SCREEN_STRUCTURE_SMOKE
admin_status=200
admin_screen_header=blocked-states
screen_has_header_region=true
screen_has_summary_region=true
screen_has_detail_region=true
screen_has_actions_region=true
screen_has_footer_region=true
denied_status=503
denied_is_public_response=true
denied_public_body=Site temporairement bloqué. | Contactez le support.
```

## Public opacity

The public denial path must not contain dashboard region names, blocked state diagnostics, route diagnostics, ACL diagnostics, FSM diagnostics, or supportable admin data.
