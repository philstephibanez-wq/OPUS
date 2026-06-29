# OPUS Workspace Status

Status file maintained as the short handoff point for the OPUS workspace.

## Current branch

- Repository: `philstephibanez-wq/OPUS`
- Branch: `master`
- Latest validated milestone: `P7_ODBC_EXPLORER_CONTRACT_CORE`
- Latest functional commit: `cfa9c64`
- Previous validated milestone: `P7_MODEL_DATASOURCE_ODBC_CORE`
- Previous cleanup commit: `a976847`

## Validated milestones

### Site scaffold / deployment smoke

- `P6F0B5_SITE_SCAFFOLD_CONTRACT_AUDIT`: OK with one review item for starter direct rendering.
- `P6F0B6_HTTP_MANUAL_RENDER`: OK.
- `P6F0B7_HTML_RENDER_CONTENT`: OK with review for Windows console UTF-8 mojibake only.

### Database environment

- PDO core: OK.
- `pdo_sqlite`: OK.
- PHP `sqlite3` extension: OK.
- SQLite PDO smoke: OK.
- `mysqlnd`: present.
- `pdo_mysql`: missing, required later for MariaDB/MySQL PDO models.
- `mysqli`: missing.
- `sqlite3.exe`: optional tooling, not required for PHP PDO runtime.

### Logger / Profiler / Diagnostics

- `P7A0A_LOGGER_FOUNDATION`: OK in source and clean clone.
- `P7A0B_PROFILER_TRACE_FOUNDATION`: OK in source and clean clone.
- `P7A0C_PROFILER_IN_GENERATED_SITE_RUNTIME`: OK in source and clean clone.
- `P7A0D_PROFILER_ERROR_TRACE_COVERAGE`: OK in source and clean clone.
- `P7A0E_DEBUG_SHIM_TO_LOGGER_PROFILER`: superseded by integrated diagnostics migration.
- `P7A0FG_MIGRATE_DEBUG_AND_DELETE_LEGACY_CLASS`: OK in source. Legacy debug class removed, active calls migrated, diagnostics smoke OK, deletion gate OK.
- `P7A0H_RUNTIME_DIAGNOSTICS_PROFILER_WIRING`: OK in source and clean clone. Runtime/Application configures Diagnostics and Profiler, starts/stops traces, records 404 and exception paths.
- `P7A0I_I18N_SMTP_CONTRACT`: OK in source. I18N is mandatory for user-visible text, official SMTP is mandatory for mail-sending workflows, and direct mail delivery outside official infrastructure is forbidden.
- `P7A0J_CLEAN_CLONE_I18N_SMTP_GATES`: OK in source and clean clone. P7A0I contract markers and direct-mail guards are validated from a clean checkout of HEAD.
- `P7_SCORETEMPLATE_CONTRACT_FINAL`: OK in source. Native ScoreTemplate interpolation, include, conditional, loop, ignored blocks and explicit ignore diagnostics are validated.
- `P7_API_REST_SSO_SECURITY_CORE`: OK in source. Existing `Opus\Api` dispatcher stack validates data-driven routes, SSO identity resolution, ACL delegation, FSM guard decisions and JSON responses.
- `P7_LSTSAR_CONTRACT_CORE`: OK in source. Load, Secure, Transform, Store, Audit and Restore are validated with separate source/target type, length, byte-size and numeric constraints.
- `P7_LSTSAR_API_INTEGRATION_CORE`: OK in source. LSTSAR process/restore endpoints are integrated with OPUS API dispatcher, SSO identity, ACL decision, FSM guard and JSON-file storage.
- `P7_MODEL_DATASOURCE_ODBC_CORE`: OK in source. OPUS Model is ODBC-backed: ODBC data sources, native ODBC connection boundary, table inspection, TableModel, ModelField, ModelRecord and OdbcModelAdapter are validated.

## Current architecture decisions

- Legacy debug class has been removed.
- Official `Opus\Diagnostics\Diagnostics` replaces active legacy debug runtime usage.
- Official `Opus\Log\Logger` exists.
- Official `Opus\Profiler\Profiler` and `Opus\Profiler\Trace` exist.
- Runtime/Application owns official diagnostics/profiler bootstrap when enabled.
- Profiler uses `microtime(true)` for trace start, event elapsed time, finish time, and total duration.
- Generated `create:site` runtime can write profiler traces with `?profiler=1` or `OPUS_PROFILER=1`.
- I18N is mandatory for every user-visible public text, even for one-language sites.
- Official OPUS SMTP/mailer service is mandatory for every mail-sending workflow; direct mail delivery is forbidden outside official infrastructure.
- OPUS database access is ODBC-only; Model and database-facing classes must use `Opus\Database\Odbc` as the official boundary.
- OPUS ODBC Explorer is the official future database administration surface; destructive CRUD and DDL operations require explicit guards and dry-run milestones.

## Next recommended milestones

1. Add OPUS ODBC Explorer read-only runtime/UI.
2. Add OPUS ODBC Explorer guarded CRUD.
3. Add OPUS ODBC Schema Builder with dry-run-first DDL.

## Operational rule

After every validated milestone, update this file or its successor so a new chat can resume without losing the current OPUS state.
