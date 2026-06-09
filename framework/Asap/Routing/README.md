# ASAP\Routing

`ASAP\Routing` is the canonical runtime routing domain.

## Role

The ROUTING domain resolves explicit route definitions into immutable route matches.

It owns:

- XML route loading through `Router::fromXml()`;
- route definitions through `RouteDefinition`;
- route matching through `Router::match()`;
- matched route data through `RouteMatch`;
- attribute route compilation support through `Route`, `ClassIndex`, `AttributeRouteProvider` and `RouteManifestCompiler`.

## Contract

ROUTING resolves routes only.

It must not:

- decide FSM state transitions;
- decide ACL permissions;
- dispatch controllers;
- render output;
- provide fallback routes when no explicit route matches.

A non-matching route must fail explicitly.

## Runtime boundary

The application runtime must depend on `ASAP\Routing`, not on legacy `ASAP\Router`.

Expected runtime chain:

```text
Application
  -> ASAP\Routing\Router
  -> ASAP\Routing\RouteMatch
  -> SecureDispatchGate
  -> ControllerDispatcher
```

## Legacy distinction

`ASAP\Router` is a legacy/public lightweight route registry domain.

It is not the canonical HTTP/FSM/ACL routing runtime.
