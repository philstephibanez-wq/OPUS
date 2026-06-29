# P7 ODBC Explorer Site App Profiler Core

This overlay completes `P7_ODBC_EXPLORER_SITE_APP_CORE` by making the OPUS ODBC Manager package profiler-aware, not only profiler-declared.

## Contract

The package must:

- keep `integrations.profiler=true` in `opus.application.json`;
- expose `config/profiler.php`;
- attach profiler metadata to every ODBC Manager route;
- emit `opus.odbc_manager` events from every controller action when a profiler adapter is injected;
- remain disabled by default so package smokes can run without a global runtime trace;
- redact sensitive profiler context keys;
- keep CRUD, DDL and SQL console disabled for this milestone.

## Events

- `action.started`
- `action.finished`
- `action.failed`

## Actions

- `dashboard`
- `datasources`
- `tables`
- `table_detail`
- `preview`
- `lstsar_draft`
