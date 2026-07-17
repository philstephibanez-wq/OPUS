# OWASYS application workspace

## Binding architecture

`application/default` is the common presentation layer inherited by every OWASYS state.

It may contain shared ScoreTemplate templates, translations and real common ACL declarations. It must not contain HTTP entrypoints, state actions, controllers, runtime orchestration, a generic application kernel, or empty architectural placeholders.

State-specific behavior belongs below `application/states/<state>/actions` and state-specific ViewModels below the corresponding state directory.

The only public PHP entrypoint is `www/index.php`.

## Current migration boundary

The build, source and structure-preview endpoints are owned by their states:

- `application/states/build/actions/build-action.php`
- `application/states/source/actions/source-action.php`
- `application/states/structure/actions/structure-preview.php`

The remaining root `application/application.php` is transitional debt. It must be dismantled by responsibility; it must not be moved back into `default`, renamed to `runtime.php` or hidden behind another catch-all.

## Required request flow

REQUÊTE → AUTH → ACL → FSM → ACTIONS FSM → VIEWMODEL → SCORETEMPLATE → HTML FINAL → JS OPTIONNEL

FSM and ACL remain authoritative and fail closed. Navigation and executable actions must be derived from them.
