# OPUS ODBC Explorer CRUD UI Core

Milestone: `P7_ODBC_EXPLORER_CRUD_UI_CORE`

## Scope

This milestone exposes guarded CRUD UI routes inside the Composer-installable `logandplay/opus-odbc-manager` package.

It adds OPUS routes, controller actions, ScoreTemplate templates, I18N keys, ACL permissions, navigation and profiler actions for guarded CRUD.

## Guarantees

- CRUD UI is protected by OPUS permissions.
- CRUD UI uses structured actions: insert, update and delete.
- CRUD UI exposes dry-run previews before execution.
- Raw SQL remains forbidden.
- DDL remains forbidden.
- UPDATE and DELETE remain predicate-guarded by the CRUD core.
- This milestone does not restart LSTSAR.

## Added routes

- `opus_odbc_manager_crud`
- `opus_odbc_manager_crud_insert`
- `opus_odbc_manager_crud_update`
- `opus_odbc_manager_crud_delete`
- `opus_odbc_manager_crud_dry_run`

## Validation

Smoke:

```text
P7_ODBC_EXPLORER_CRUD_UI_CORE_SMOKE_OK
```
