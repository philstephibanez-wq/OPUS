# P117SITE16 - Generated-site authoring Composer commands

## Purpose

This milestone adds three explicit write commands for generated OPUS sites:

```bash
composer opus:create-module -- <site-id> <ModuleId> --title "Title" --write
composer opus:create-page -- <site-id> <ModuleId> <page-id> <path> --title "Title" --write
composer opus:create-rubric -- <site-id> <ModuleId> <path> --title "Title" --write
```

## Contracts

- All mutations require `--write`.
- The commands are limited to `sites/<site-id>`.
- No command repairs broken JSON or guesses missing contracts.
- Templates remain `.score` views.
- The generated workflow remains: route -> module -> `.score` template -> i18n/resources -> assets.
- `sites/skeleton` is a generated artifact and must be cleaned after smoke tests.

## Command roles

### `opus:create-module`

Creates a module scaffold and registers it in `application/config/modules.json`.
It does not add a route.

### `opus:create-page`

Creates a page template inside an existing module and adds a route in `application/config/routes.json`.

### `opus:create-rubric`

Creates a module and its default index route in one explicit operation.

## Smoke

The smoke test generates a temporary `sites/skeleton`, applies all three commands, validates the site, lists routes/modules, checks the new entries, and removes `sites/skeleton`.
