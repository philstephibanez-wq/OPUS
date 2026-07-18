# OWASYS application workspace

## Binding architecture

`application/default` is the common presentation layer inherited by every OWASYS state.

It may contain shared ScoreTemplate templates, translations and real common ACL declarations. It must not contain HTTP entrypoints, state actions, controllers, runtime orchestration, a generic application kernel, duplicate security directories, or empty architectural placeholders.

`application/default/acl` contains only effective shared ACL declarations. It must never exist only through `.gitkeep`, and there must not be a competing `application/default/security` directory.

State-specific behavior belongs below `application/states/<state>/actions`. State-specific ViewModels and templates belong below the corresponding state directory when they are not common to every state.

The only public PHP entrypoint is `www/index.php`.

## Required request flow

REQUÊTE → AUTH → ACL → FSM → ACTIONS FSM → VIEWMODEL → SCORETEMPLATE → HTML FINAL → JS OPTIONNEL

FSM and ACL remain authoritative and fail closed. Navigation, routes and executable actions must be projections of authorized executable FSM transitions. No static menu may bypass FSM, ACL/RBAC or guards.

Frontend/backend and frontoffice/backoffice are independent axes. OWASYS must never infer one distinction from the other.

## ScoreTemplate rendering contract

ScoreTemplate is the OWASYS final rendering target.

The common layout is rendered from:

- `application/default/templates/layouts/main.score`
- `application/default/templates/partials/navigation.score`
- `application/default/templates/partials/locale-switcher.score`
- `application/default/templates/partials/state-content.score`

The active horizontal navigation is built from the FSM + ACL + guards through `application/default/navigation/view-model.php`, then rendered by `partials/navigation.score`.

The final rendered navigation must not contain the legacy structural markers:

- `ow-shell`
- `ow-sidebar`
- `class="ow-nav"`

No CSS override may be used to disguise the legacy sidebar. The structural HTML must originate from ScoreTemplate. JavaScript is optional enhancement only and must not build the header, menu, locale selector, sidebar or page structure.

Templates represent prepared ViewModel data only. They must not perform routing, authorization, service calls, database reads or business decisions. PHP prepares data and orchestrates the pipeline; it must not be the final layout renderer.

## Current routing boundary

GET page rendering is routed through:

- `application/score-page.php`

This path uses `SiteConfiguration`, `RequestContext`, `SessionContext`, `Translator`, FSM route authorization, ACL-filtered navigation projection, state ViewModels and `ScoreTemplateRenderer`.

The state-owned endpoints are:

- `application/states/build/actions/build-action.php`
- `application/states/source/actions/source-action.php`
- `application/states/structure/actions/structure-preview.php`

POST actions and logout that still pass through `application/application.php` remain transitional debt. That file must be dismantled by responsibility; it must not be moved back into `default`, renamed to `runtime.php`, `kernel.php`, `bootstrap.php`, or hidden behind another catch-all.

`structure-preview.php` still has a direct HTML rendering debt and must be converted to a state ViewModel plus ScoreTemplate rendering.

## Public boundary

`www/index.php` is the single public PHP entrypoint. Public routing must not reintroduce separate PHP endpoint files under `www`.

The front controller may dispatch only to validated application-relative handlers. Public assets remain under `www/asset`.

For PHP's built-in development server, the only supported launch command is:

```text
php -S 127.0.0.1:18080 -t sites/owasys/www sites/owasys/dev-router.php
```

`sites/owasys/dev-router.php` serves only non-PHP public assets directly and routes every application request, including `/`, through `www/index.php`. Starting the server with `application/application.php` as router, or using `sites/owasys` as the document root, is forbidden because it bypasses the front controller and reactivates the legacy sidebar and native locale selector.

## Validation gates

The following focused smokes protect current architectural boundaries:

- `tools/smoke_owasys_front_controller_boundary.php`
- `tools/smoke_owasys_request_context_front_controller.php`
- `tools/smoke_owasys_application_boundaries.php`
- `tools/smoke_owasys_default_state_layout.php`
- `tools/smoke_owasys_fsm_acl_score_navigation.php`
- `tools/smoke_owasys_structure_preview_boundaries.php`
- `tools/smoke_owasys_score_horizontal_navigation.php`

A green focused smoke validates only its declared boundary. It must never be presented as proof that the whole OWASYS architecture is complete.

## Completion status

Completed boundaries:

- one public PHP entrypoint;
- front-controller request normalization;
- canonical PHP development router through `www/index.php`;
- state ownership for build, source and structure-preview actions;
- shared ACL consolidated under `application/default/acl`;
- FSM/ACL/guard-derived navigation ViewModel;
- horizontal navigation rendered by ScoreTemplate for GET pages;
- shared locale selector rendered by ScoreTemplate with local SVG flags;
- shared configuration, session and translation boundaries wired into structure preview;
- explicit workspace architecture guards.

Remaining work:

- dismantle `application/application.php` completely;
- move all POST actions and logout into state-owned FSM actions;
- remove duplicated configuration, session, authentication, registry and request logic from the transitional path;
- convert state-specific direct HTML generation to ViewModels and `.score` templates;
- review `application/default` and retain only genuinely common resources;
- validate the complete HTTP path under Apache without relying only on source-level smokes;
- add the OPUS development profiler to generated applications in development mode only, never to OWASYS itself and never in production.
