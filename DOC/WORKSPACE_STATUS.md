# OPUS Workspace Status

Status file maintained as the short handoff point for the OPUS workspace.

## Current branch

- Repository: `philstephibanez-wq/OPUS`
- Branch: `master`
- Latest validated milestone: `P7_OPUS_PACKAGES_DIRECTORY_CONTRACT_CORE`
- Latest functional commit: `8b25ba2`
- Previous validated milestone: `P7_ODBC_EXPLORER_CONTRACT_CORE`
- Previous cleanup commit: `e12b1dd`

## Validated milestones

### Site scaffold / deployment smoke

- `P6F0B5_SITE_SCAFFOLD_CONTRACT_AUDIT`: OK with one review item for starter direct rendering.
- `P6F0B6_HTTP_MANUAL_RENDER`: OK.
- `P6F0B7_HTML_RENDER_CONTENT`: OK with review for Windows console UTF-8 mojibake only.

### Database environment

- PHP ODBC runtime: OK. `odbc` and `PDO_ODBC` are enabled in the active UwAmp PHP runtime.
- Local PHP runtime: `H:\UwAmp\bin\php\php-8.5.6\php.exe`, x86 / 32-bit, thread-safe.
- ODBC driver rule: OPUS local runtime must use 32-bit ODBC drivers/DSN while this PHP runtime remains x86.
- OPUS database strategy: ODBC-only. Direct MySQL, PostgreSQL, SQLite, PDO-specific or mysqli-facing OPUS database classes are not official targets.
- PDO core: present but not the OPUS database abstraction boundary.
- `pdo_sqlite` and PHP `sqlite3`: present historically, but superseded for OPUS database-facing architecture by the ODBC-only rule.
- `pdo_mysql` and `mysqli`: not required for the OPUS ODBC-only target.
- `sqlite3.exe`: optional tooling, not required for OPUS ODBC runtime.

### Logger / Profiler / Diagnostics

- `P7A0A_LOGGER_FOUNDATION`: OK in source and clean clone.
- `P7A0B_PROFILER_TRACE_FOUNDATION`: OK in source and clean clone.
- `P7A0C_PROFILER_IN_GENERATED_SITE_RUNTIME`: OK in source and clean clone.
- `P7A0D_PROFILER_ERROR_TRACE_COVERAGE`: OK in source and clean clone.
- `P7A0E_DEBUG_SHIM_TO_LOGGER_PROFILER`: superseded by integrated diagnostics migration.
- `P7A0FG_MIGRATE_DEBUG_AND_DELETE_LEGACY_CLASS`: OK in source. Legacy debug class removed, active calls migrated, diagnostics smoke OK, deletion gate OK.
- `P7A0H_RUNTIME_DIAGNOSTICS_PROFILER_WIRING`: OK in source and clean clone. Runtime/Application configures Diagnostics and Profiler, starts/stops traces, records 404 and exception paths.
- `P7A0I_I18N_SMTP_CONTRACT`: OK in source. I18N is mandatory for user-visible text, official SMTP is mandatory for mail-sending workflows, and direct mail delivery is forbidden outside official infrastructure.
- `P7A0J_CLEAN_CLONE_I18N_SMTP_GATES`: OK in source and clean clone. P7A0I contract markers and direct-mail guards are validated from a clean checkout of HEAD.
- `P7_SCORETEMPLATE_CONTRACT_FINAL`: OK in source. Native ScoreTemplate interpolation, include, conditional, loop, ignored blocks and explicit ignore diagnostics are validated.
- `P7_API_REST_SSO_SECURITY_CORE`: OK in source. Existing `Opus\Api` dispatcher stack validates data-driven routes, SSO identity resolution, ACL delegation, FSM guard decisions and JSON responses.
- `P7_LSTSAR_CONTRACT_CORE`: OK in source. Load, Secure, Transform, Store, Audit and Restore are validated with separate source/target type, length, byte-size and numeric constraints.
- `P7_LSTSAR_API_INTEGRATION_CORE`: OK in source. LSTSAR process/restore endpoints are integrated with OPUS API dispatcher, SSO identity, ACL decision, FSM guard and JSON-file storage.
- `P7_MODEL_DATASOURCE_ODBC_CORE`: OK in source. OPUS Model is ODBC-backed: ODBC data sources, native ODBC connection boundary, table inspection, TableModel, ModelField, ModelRecord and OdbcModelAdapter are validated.
- `P7_ODBC_EXPLORER_CONTRACT_CORE`: OK in source. OPUS ODBC Explorer contract is validated as the Adminer/phpMyAdmin-like OPUS database administration surface for ODBC + Model + LSTSAR, with destructive operations guarded for later milestones.

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
- OPUS Model is the official representation layer for ODBC tables, rows, fields, types, lengths, nullability and metadata.
- LSTSAR final target is Model-driven + ODBC-driven. The existing array/schema LSTSAR core is not the final BDD heterogeneous LSTSAR architecture until it is aligned with Model + ODBC.
- OPUS ODBC Explorer must be a standalone OPUS site/application, not only a utility class.
- OPUS ODBC Explorer site must use normal OPUS routes, controllers, ScoreTemplate templates, I18N, SSO/ACL, diagnostics, profiler and logs.
- OPUS ODBC Explorer is an admin/dev surface, not a public anonymous site.
- OPUS ODBC Explorer must target Adminer/phpMyAdmin-style parity through ODBC capabilities: drivers/DSN, connection tests, catalogs/schemas/tables, columns, preview, SQL console, import/export, guarded CRUD, guarded DDL, Model generation and LSTSAR draft generation.
- Destructive CRUD and DDL operations require explicit guards, dry-run where applicable, non-empty predicates, confirmation and audit-oriented design.

## Next recommended milestones

1. `P7_OPUS_APP_PACKAGE_CONTRACT_CORE`: implement real read-only ODBC explorer capabilities: drivers/DSN inventory, connection test, list tables, inspect columns, preview rows, generate TableModel and LSTSAR draft.
2. `P7_ODBC_EXPLORER_READONLY_CORE`: create the OPUS ODBC Explorer as a true OPUS site/application with routes, controllers, ScoreTemplate views, I18N, SSO/ACL and navigation.
3. `P7_ODBC_EXPLORER_SITE_APP_CORE`: add guarded insert/update/delete through Model validation and explicit confirmation.
4. `P7_ODBC_SCHEMA_BUILDER_CORE`: add Model-to-DDL dry-run, guarded DDL execution and driver capability checks.
5. `P7_LSTSAR_MODEL_DRIVEN_ODBC_CORE`: align LSTSAR with OPUS Model + ODBC for heterogeneous database table ingestion and storage.

## Operational rule

After every validated milestone, update this file or its successor so a new chat can resume without losing the current OPUS state.
