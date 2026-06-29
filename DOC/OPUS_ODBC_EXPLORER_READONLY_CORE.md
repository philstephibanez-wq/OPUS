# OPUS ODBC Explorer Read-only Core

## Milestone

`P7_ODBC_EXPLORER_READONLY_CORE`

## Purpose

This milestone adds the read-only runtime layer for the OPUS ODBC Explorer.

The goal is to browse ODBC data sources safely before any CRUD, SQL console or DDL milestone exists.

## Added contract

- `OdbcExplorerTableReference`: immutable table/view metadata object.
- `OdbcExplorerReadOnlyCatalogInterface`: table catalog provider contract.
- `OdbcExplorerNativeCatalogReader`: native ODBC metadata reader using `odbc_tables`.
- `OdbcExplorerReadOnlyService`: read-only orchestration over catalog + existing `OdbcExplorerService`.

## Validated behavior

- data source overview;
- list tables/views;
- inspect columns through OPUS Model;
- preview rows through OPUS Model records;
- generate LSTSAR draft from a table model;
- reject unknown tables explicitly;
- preserve the ODBC-only database boundary.

## Explicit non-goals

This milestone does not add:

- CRUD;
- SQL console;
- DDL/schema builder;
- UI routes/controllers;
- Composer package installation for the manager site beyond the already validated package skeleton.

## Next milestones

1. `P7_ODBC_EXPLORER_SITE_APP_CORE`
2. `P7_ODBC_EXPLORER_CRUD_CORE`
3. `P7_ODBC_SCHEMA_BUILDER_CORE`
4. `P7_LSTSAR_MODEL_DRIVEN_ODBC_CORE`
