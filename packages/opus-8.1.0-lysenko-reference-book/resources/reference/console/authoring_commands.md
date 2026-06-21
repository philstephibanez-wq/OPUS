# Console authoring commands

Status: integrated Reference Book topic.

This page documents the OPUS Composer console authoring commands used to generate and inspect OPUS sites.

## Contract

- Write commands require `--write`.
- Missing `--write` is a dry-run/plan mode and must not write files.
- Duplicate identifiers or duplicate route paths fail explicitly.
- Invalid module ids, page ids, and route paths fail explicitly.
- Errors must not leave partial templates, partial routes, partial module directories, or registry entries.
- Inspection commands are read-only.
- No fallback is allowed.

## Commands

### `opus:create-site`

Syntax: `composer opus:create-site -- <site-id> --write`

Role: Create a generated OPUS site skeleton.

Write mode: `requires --write`

Error contract:
- invalid site id must fail
- missing --write must not write files

### `opus:validate-site`

Syntax: `composer opus:validate-site -- <site-id>`

Role: Validate a generated OPUS site contract.

Write mode: `read-only`

Error contract:
- missing site must fail explicitly
- malformed JSON must fail explicitly

### `opus:serve-site`

Syntax: `composer opus:serve-site -- <site-id> --port <port>`

Role: Serve a generated site through PHP built-in server for local development.

Write mode: `read-only runtime`

Error contract:
- invalid site must fail explicitly
- invalid port must fail explicitly

### `opus:list-routes`

Syntax: `composer opus:list-routes -- <site-id>`

Role: List routes declared by a generated site.

Write mode: `read-only`

Error contract:
- broken module reference must fail explicitly
- broken template reference must fail explicitly

### `opus:list-modules`

Syntax: `composer opus:list-modules -- <site-id>`

Role: List modules declared by a generated site.

Write mode: `read-only`

Error contract:
- missing module root must fail explicitly
- missing default template must fail explicitly

### `opus:create-module`

Syntax: `composer opus:create-module -- <site-id> <ModuleId> --title <title> --write`

Role: Create a module directory, module manifest, default Score templates, and module registry entry.

Write mode: `requires --write`

Error contract:
- duplicate module must fail
- invalid module id must fail
- missing --write must not write files
- failure must not leave partial module registration

### `opus:create-page`

Syntax: `composer opus:create-page -- <site-id> <ModuleId> <page-id> <route-path> --title <title> --write`

Role: Create a module page Score template and route entry.

Write mode: `requires --write`

Error contract:
- duplicate page template must fail
- duplicate route path must fail
- invalid page id must fail
- invalid route path must fail
- failure must not leave partial template or route

### `opus:create-rubric`

Syntax: `composer opus:create-rubric -- <site-id> <RubricId> <route-path> --title <title> --write`

Role: Create a rubric module and its index route in one authoring operation.

Write mode: `requires --write`

Error contract:
- duplicate rubric route must fail
- invalid rubric id must fail
- invalid route path must fail
- failure must not leave module directory or registry entry
