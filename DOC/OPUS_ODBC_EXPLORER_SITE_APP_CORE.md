# OPUS — P7_ODBC_EXPLORER_SITE_APP_CORE

## Contract

`P7_ODBC_EXPLORER_SITE_APP_CORE` turns the Composer package `logandplay/opus-odbc-manager` into the official OPUS ODBC Manager site application shell.

The package remains installable by Composer and keeps OPUS ODBC access behind the already validated read-only ODBC Explorer core.

## Scope

Validated scope:

- protected OPUS application package;
- normal OPUS route declarations;
- controller classes;
- ScoreTemplate `.score` templates;
- I18N files;
- ACL policy declaration;
- navigation declaration;
- read-only page view-models;
- dashboard, datasources, tables, table detail, preview and LSTSAR draft pages.

## Out of scope

This milestone intentionally does not add CRUD, DDL or SQL console execution.

Those capabilities require separate guarded milestones:

- `P7_ODBC_EXPLORER_CRUD_CONTRACT_CORE`;
- `P7_ODBC_EXPLORER_CRUD_CORE`;
- `P7_ODBC_SCHEMA_BUILDER_CORE`.

## Security

The site is protected and denied by default. Anonymous access is forbidden.

Disabled permissions are declared for insert, update, delete, DDL and SQL console until their guarded contracts exist.

## Composer package

The package root is:

```text
packages/opus-odbc-manager/
```

The package manifest is:

```text
packages/opus-odbc-manager/opus.application.json
```

The package Composer definition is:

```text
packages/opus-odbc-manager/composer.json
```

## Smoke

Validation command:

```cmd
php tools\smokes\smoke_p7_odbc_explorer_site_app_core.php
```

Expected marker:

```text
P7_ODBC_EXPLORER_SITE_APP_CORE_SMOKE_OK
```
