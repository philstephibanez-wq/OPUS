# P117SITE15 - OPUS route/module inspection commands

## Purpose

This milestone adds two read-only Composer commands for generated OPUS sites:

```bash
composer opus:list-routes -- <site-id>
composer opus:list-modules -- <site-id>
```

The commands make the generated site workflow explicit without requiring users to inspect the framework internals.

## Contracts

- `opus:list-routes` reads `application/config/routes.json`.
- `opus:list-modules` reads `application/config/modules.json` and each module `module.json`.
- Both commands are read-only.
- Both commands fail loudly when the site, route, module, template, or JSON contract is invalid.
- Neither command creates, repairs, or rewrites a generated site.

## Expected workflow

A generated site page is understood as:

```text
route -> module -> template .score -> i18n -> assets
```

Example:

```text
/ -> Home -> application/modules/Home/templates/pages/index.score -> resources/i18n/fr.json -> public/assets/css/starter.css
```

## Smoke

The smoke test generates a temporary `sites/skeleton`, validates it, runs both inspection commands, checks expected route/module output, and deletes `sites/skeleton` at the end.
