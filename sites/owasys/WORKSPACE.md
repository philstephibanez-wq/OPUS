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

## Legacy-reference migration contract

Legacy sources may be read only from Git history to inventory useful behavior, displayed data, actions and contracts. They must never be restored, copied as runtime code, used as a compatibility layer, or reintroduced through CSS, JavaScript, routing or a catch-all renderer.

Each useful capability must be rebuilt as new state-owned sources:

- `application/states/<state>/views` for prepared ViewModels;
- `application/states/<state>/templates` for `.score` rendering;
- `application/states/<state>/actions` for authorized FSM actions;
- state-specific services only when the responsibility is not common.

No source may be deleted merely because it contains legacy structure. Before deletion, its useful behavior must be inventoried and either proven unused or replaced by a verified current implementation.

## ScoreTemplate rendering contract

ScoreTemplate is the OWASYS final rendering target.

The common layout is rendered from:

- `application/default/templates/layouts/main.score`
- `application/default/templates/partials/navigation.score`
- `application/default/templates/partials/locale-switcher.score`
- `application/default/templates/partials/state-content.score`

A state may declare its own template from `application/states/<state>/templates`. `application/score-page.php` performs orchestration only and renders that state template with the prepared state ViewModel.

The active horizontal navigation is built from the FSM + ACL + guards through `application/default/navigation/view-model.php`, then rendered by `partials/navigation.score`.

The final rendered navigation must not contain the legacy structural markers:

- `ow-shell`
- `ow-sidebar`
- `class="ow-nav"`

No CSS override may be used to disguise legacy structure. Structural HTML must originate from ScoreTemplate. JavaScript is optional enhancement only and must not build the header, menu, locale selector, sidebar or page structure.

Templates represent prepared ViewModel data only. They must not perform routing, authorization, service calls, database reads or business decisions. PHP prepares data and orchestrates the pipeline; it must not be the final layout renderer.

## Routing boundary

All normal GET page rendering is routed through:

- `application/score-page.php`

This path uses `SiteConfiguration`, `RequestContext`, `SessionContext`, `Translator`, FSM route authorization, ACL-filtered navigation projection, state ViewModels and `ScoreTemplateRenderer`.

The state-owned explicit endpoints are:

- `application/states/build/actions/build-action.php`
- `application/states/source/actions/source-action.php`
- `application/states/structure/actions/structure-preview.php`

The deleted `application/application.php` must never be recreated. There is no legacy fallback renderer, sentinel renderer, runtime catch-all or compatibility shell.

Non-GET requests that do not target an explicit state-owned endpoint fail closed with HTTP 405 and `OWASYS_METHOD_NOT_SUPPORTED` until their state-owned actions are implemented.

`structure-preview.php` still has a direct HTML rendering debt and must be converted to a state ViewModel plus ScoreTemplate rendering.

## Public boundary

`www/index.php` is the single public PHP entrypoint. Public routing must not reintroduce separate PHP endpoint files under `www`.

The front controller dispatches to `score-page.php` by default and may dispatch only to validated application-relative handlers. Public assets remain under `www/asset`.

For PHP's built-in development server, the only supported launch command is:

```text
php -S 127.0.0.1:18080 -t sites/owasys/www sites/owasys/dev-router.php
```

`sites/owasys/dev-router.php` serves only non-PHP public assets directly and routes every application request through `www/index.php`.

## Tools contract

The `tools` directory is part of the architecture and must be cleaned with the application itself.

Every OWASYS tool must target an existing canonical path and current contract. A smoke that validates a deleted bootstrap, a removed public endpoint, a PHP layout, a legacy renderer, or any obsolete architecture must be deleted rather than adapted to preserve historical structure.

`tools/smoke_all_opus.php` must list only files that physically exist and must register all current blocking OWASYS architecture smokes. Missing files, stale paths and duplicate obsolete smokes are blocking defects.

The workspace must be updated in the same change set whenever an architectural boundary, canonical path, validation gate, completion statement or known debt changes.

## Forbidden legacy paths and markers

The following must remain absent from OWASYS and its tools:

- `application/application.php`
- `application/default/http`
- `application/default/security`
- `application/default/bootstrap.php`
- `application/default/layouts/main.php`
- `application/default/css/default.css`
- `tools/migrate_owasys_entrypoint_to_backend.php`
- public endpoint PHP files other than `www/index.php`
- `ow-shell`
- `ow-sidebar`
- `class="ow-nav"`
- `OWASYS_LEGACY_APPLICATION_REMOVED`
- legacy `application.php` handler references
- legacy Mermaid runtime inclusion

## Validation gates

The following focused smokes protect current architectural boundaries:

- `tools/smoke_owasys_front_controller_boundary.php`
- `tools/smoke_owasys_request_context_front_controller.php`
- `tools/smoke_owasys_application_boundaries.php`
- `tools/smoke_owasys_default_state_layout.php`
- `tools/smoke_owasys_fsm_acl_score_navigation.php`
- `tools/smoke_owasys_structure_preview_boundaries.php`
- `tools/smoke_owasys_score_horizontal_navigation.php`
- `tools/smoke_owasys_dev_router.php`
- `tools/smoke_owasys_no_legacy.php`
- `tools/smoke_owasys_tools_cleanup.php`
- `tools/smoke_owasys_structure_score_content.php`

A green focused smoke validates only its declared boundary. It must never be presented as proof that the whole OWASYS architecture is complete.

## Completion status

Completed boundaries:

- one public PHP entrypoint;
- front-controller request normalization;
- Score page as the only default renderer;
- physical deletion of the legacy application renderer;
- canonical PHP development router through `www/index.php`;
- shared ACL consolidated under `application/default/acl`;
- FSM/ACL/guard-derived navigation ViewModel;
- horizontal navigation rendered by ScoreTemplate for GET pages;
- shared locale selector rendered by ScoreTemplate with local SVG flags;
- state-owned ScoreTemplate dispatch supported by `score-page.php`;
- Structure rebuilt with a new read-only ViewModel, application inspection, states table, routes table and Mermaid FSM source;
- `score.css` is self-contained and no longer imports the deleted legacy stylesheet;
- build UI smoke targets the canonical state action while the public front controller remains the only entrypoint;
- explicit no-legacy and tools cleanup gates.

Remaining work:

- add a local, versioned Mermaid renderer asset so the restored Mermaid source becomes a graphical diagram without CDN dependency;
- restore Structure validation, draft preparation and write-plan actions as state-owned AUTH/ACL/FSM actions and Score templates;
- rebuild Registry content and actions with state-owned ViewModels/templates/actions;
- rebuild Source, Build, Security, Account, Data, Workflows and Home content without structural JavaScript;
- remove structural responsibilities still present in `www/asset/js/owasys.js` only after their replacements are verified;
- convert `structure-preview.php` direct HTML to ViewModel plus ScoreTemplate;
- validate the complete HTTP path under Apache;
- add the OPUS development profiler to generated applications in development mode only, never to OWASYS itself and never in production.
