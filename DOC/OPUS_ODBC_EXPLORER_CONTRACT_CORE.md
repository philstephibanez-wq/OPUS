# OPUS ODBC Explorer Contract Core

Milestone: `P7_ODBC_EXPLORER_CONTRACT_CORE`

## Purpose

OPUS ODBC Explorer is the official OPUS database administration surface.

It targets Adminer/phpMyAdmin-style functionality while keeping the OPUS architecture strict:

- database access is ODBC-only;
- database rows are represented through OPUS `Model`;
- LSTSAR jobs are prepared from OPUS models and ODBC data sources;
- dangerous write or DDL features require dry-run and explicit confirmation;
- driver-specific features are exposed through capabilities instead of silent fallback.

## Scope of this milestone

This milestone defines the functional contract and the first service-level bridge:

- feature identifiers;
- capability metadata;
- Adminer/phpMyAdmin parity map;
- data-driven ODBC data-source registry;
- connection test through `Opus\Database\Odbc`;
- table to `TableModel` generation;
- preview rows through `ModelRecord`;
- LSTSAR draft generation from source/target models.

## Not yet implemented

The following are intentionally not implemented in this contract milestone:

- visual UI;
- routing/controller layer;
- interactive SQL console;
- CRUD row editor;
- DDL execution;
- schema builder;
- driver-specific dialect plugins.

They must be delivered in later milestones with smoke tests and explicit guards.

## Official rule

No OPUS class that treats databases may bypass `Opus\Database\Odbc`.

Model and LSTSAR must consume ODBC through OPUS contracts, never through scattered direct driver calls.
