# P117A5 - Native Admin Dashboard Route Smoke

## Status

PENDING_RUNTIME_VALIDATION

## Purpose

P117A5 promotes the administrator blocked-state surface from an isolated ViewModel smoke to a native OPUS dashboard route smoke.

The dashboard route is a native OPUS surface. It is not an external tool or optional add-on. It is protected by the same FSM / ACL / SSO-like control plane as the rest of the framework.

## Native route

GET /admin/blocked-states

The route is represented by Opus\Admin\AdminBlockedStatesDashboardRoute.

## Control plane

The route is guarded by Opus\Admin\AdminDashboardRouteControlPlane.

The control plane validates method, exact route path, admin role, required admin scope and dashboard profile before returning any dashboard payload.

## Authorized path

An authorized administrator request returns an administrator ViewModel produced by Opus\Admin\AdminBlockedStateViewModel.

The ViewModel contains event id, route key, blocked state, reason, severity, admin action and recommended actions.

## Refused path

A refused or anonymous request never receives administrator diagnostics.

The public response remains exactly:

Site temporairement bloqué.
Contactez le support.

Protected diagnostics remain available only through admin, log or report surfaces.

## Smoke

Runtime smoke: Opus\Runtime\NativeAdminDashboardRouteSmoke::run()

Expected gate: P117A5_NATIVE_ADMIN_DASHBOARD_ROUTE_SMOKE

The smoke proves that the native admin route exists, uses a control plane, returns dashboard data only to an authorized administrator, refuses anonymous access with opaque public output, and prevents admin diagnostics from leaking to public output.
