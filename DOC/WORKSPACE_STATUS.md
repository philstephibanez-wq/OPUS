# OPUS Workspace Status

Status file maintained as the short handoff point for the OPUS workspace.

## Current branch

- Repository: `philstephibanez-wq/OPUS`
- Branch: `master`
- Latest validated milestone: `P7A0H_RUNTIME_DIAGNOSTICS_PROFILER_WIRING`
- Latest functional commit: `b03ee80`
- Latest workspace-status commit: pending
- Previous cleanup commit: `7a9f863`

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
- `P7A0H_RUNTIME_DIAGNOSTICS_PROFILER_WIRING`: OK in source. Runtime/Application now configures Diagnostics and Profiler, starts/stops traces, records 404 and exception paths.

## Current architecture decisions

- Legacy debug class has been removed.
- Official `Opus\Diagnostics\Diagnostics` replaces active legacy debug runtime usage.
- Official `Opus\Log\Logger` exists.
- Official `Opus\Profiler\Profiler` and `Opus\Profiler\Trace` exist.
- Runtime/Application owns official diagnostics/profiler bootstrap when enabled.
- Profiler uses `microtime(true)` for trace start, event elapsed time, finish time, and total duration.
- Generated `create:site` runtime can write profiler traces with `?profiler=1` or `OPUS_PROFILER=1`.

## Next recommended milestones

1. Validate P7A0H in clean clone.
2. Add profiler viewer route later, after trace storage contract is stable.
3. Add DB connection configuration contract and SQLite PDO model smoke.
4. Prepare `pdo_mysql` enablement for MariaDB/MySQL support.

## Operational rule

After every validated milestone, update this file or its successor so a new chat can resume without losing the current OPUS state.
