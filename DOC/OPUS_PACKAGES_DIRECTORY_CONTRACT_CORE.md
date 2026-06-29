# OPUS Packages Directory Contract Core

Milestone: `P7_OPUS_PACKAGES_DIRECTORY_CONTRACT_CORE`.

## Contract

Official OPUS applications live as source packages under:

```text
packages/
```

This directory is a monorepo development convention. The official installation contract remains Composer.

## Rules

- `packages/` contains OPUS application package sources.
- Each package has its own `composer.json`.
- Each package has its own `opus.application.json`.
- Root `composer.json` declares a path repository for `packages/*`.
- A package source directory without `composer.json` is invalid.
- Manual folder copy is not the official application installation path.

## Initial official package shells

```text
packages/opus-ref-book
packages/opus-demo
packages/opus-odbc-manager
```

## Next impact

`P7_ODBC_EXPLORER_READONLY_CORE` may implement services, but `P7_ODBC_EXPLORER_SITE_APP_CORE` must build the site inside the Composer-installable `packages/opus-odbc-manager` application package.
