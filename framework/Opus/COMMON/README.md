# OPUS COMMON boundary

`OPUS\\COMMON` is not a shared junk drawer. It is the strict shared language used by `FRONT`, `MIDDLE` and `BACK`.

Allowed families:

- `Contract`
- `Dto`
- `ValueObject`
- `Error`
- `Result`
- `Enum`
- `Identifier`
- `Assertion`
- `Clock`

Forbidden in COMMON:

- views, layouts, sections, components, renderers
- routers, API gateways, security policies, FSM processor implementations
- business services, repositories, actions, runners, jobs, workers
- database access, external process calls, system commands

A file belongs in COMMON only when it is stable shared language and contains no rendering, routing, security decision, business decision or data access.
