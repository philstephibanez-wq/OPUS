# OPUS — P7 ODBC Explorer CRUD Core

## Milestone

`P7_ODBC_EXPLORER_CRUD_CORE`

## Purpose

This milestone adds real guarded CRUD execution primitives for OPUS ODBC Explorer.

It follows the established rule:

```text
ODBC -> TableModel -> validation Model -> guard CRUD -> prepared ODBC statement -> audit result
```

## Scope

Implemented:

- parameterized INSERT SQL plan generation;
- parameterized UPDATE SQL plan generation;
- parameterized DELETE SQL plan generation;
- NULL-safe predicate SQL for update/delete;
- native PHP ODBC prepared statement executor;
- dry-run path that produces an audit plan without connecting/executing;
- service wrapper for guarded execution;
- smoke validation for SQL plan safety, guards, dry-run and regression smokes.

Not implemented in this milestone:

- CRUD UI routes/forms;
- SQL console;
- DDL/schema builder;
- LSTSAR Model-driven storage.

## Safety rules

- Raw SQL is not accepted by the CRUD layer.
- Values are never interpolated into SQL strings.
- Values are passed through positional parameters intended for `odbc_prepare` and `odbc_execute`.
- UPDATE requires a non-empty structured predicate.
- DELETE requires a non-empty structured predicate.
- ACL must be granted before execution.
- Confirmation token must be present before execution.
- Driver capabilities must explicitly allow the requested action.
- Dry-run is explicit and returns an audit plan.

## Next

After this milestone, continue with:

1. `P7_ODBC_EXPLORER_CRUD_UI_CORE`
2. `P7_ODBC_MODEL_REFINEMENT_CORE`

Then pause before returning to LSTSAR.
