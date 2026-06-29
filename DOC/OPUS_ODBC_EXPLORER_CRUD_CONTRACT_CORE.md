# OPUS P7 — ODBC Explorer CRUD Contract Core

## Contract

`P7_ODBC_EXPLORER_CRUD_CONTRACT_CORE` defines the guarded CRUD contract before any ODBC write execution is exposed.

CRUD is not raw SQL. CRUD must flow through:

```text
ODBC catalog -> TableModel -> ModelRecord validation -> CRUD guard -> prepared ODBC command -> audit result
```

## Scope

This milestone adds contract/value objects only:

- `OdbcCrudAction`
- `OdbcCrudCapabilities`
- `OdbcCrudPredicate`
- `OdbcCrudModelValidator`
- `OdbcCrudCommand`
- `OdbcCrudCommandResult`
- `OdbcCrudGuard`

## Mandatory rules

- INSERT/UPDATE/DELETE are the only CRUD actions.
- UPDATE and DELETE require a non-empty structured predicate.
- INSERT and UPDATE values must validate against `TableModel` / `ModelRecord`.
- DELETE cannot carry value payload.
- Unknown fields are rejected by the Model layer.
- Length/type/nullability validation is delegated to `ModelField` / `ModelRecord`.
- ACL grant is mandatory.
- Explicit confirmation token is mandatory.
- Driver capabilities are mandatory.
- Audit context is mandatory.
- No CRUD route is exposed by this milestone.
- No SQL string builder is exposed by this milestone.
- No DDL is introduced by this milestone.

## Next

`P7_ODBC_EXPLORER_CRUD_CORE` may execute prepared ODBC INSERT/UPDATE/DELETE only after this guard contract is respected.

Before returning to LSTSAR, ODBC CRUD and Model work must be explicitly reported to the user and approved.
