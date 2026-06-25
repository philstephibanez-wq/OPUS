# OPUS Workspace Status

Status file maintained as the short handoff point for the OPUS workspace.

## Current branch

- Repository: `philstephibanez-wq/OPUS`
- Branch: `master`
- Latest validated milestone: `P7A0E_DEBUG_SHIM_TO_LOGGER_PROFILER`
- Latest functional commit: `33c4e6c`
- Latest workspace status commit: this document update

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

### Logger / Profiler / Debug

- `P7A0A_LOGGER_FOUNDATION`: OK in source and clean clone.
- `P7A0B_PROFILER_TRACE_FOUNDATION`: OK in source and clean clone.
- `P7A0C_PROFILER_IN_GENERATED_SITE_RUNTIME`: OK in source and clean clone.
- `P7A0D_PROFILER_ERROR_TRACE_COVERAGE`: OK in source and clean clone.
- `P7A0E_DEBUG_SHIM_TO_LOGGER_PROFILER`: OK in source; clean clone validation still recommended.

## Current architecture decisions

- `OPUS_Debug` remains legacy-compatible and must not be deleted while active calls exist.
- `OPUS_Debug` is now a bridge-capable shim.
- Existing `OPUS_Debug::setDebug`, `add`, `addDump`, `addClasses`, and `get` remain available.
- New optional bridge methods exist: `setLogger`, `setProfiler`, `clearBridge`.
- Official `Opus\Log\Logger` exists and is independent from `OPUS_Debug`.
- Official `Opus\Profiler\Profiler` and `Opus\Profiler\Trace` exist and are independent from `OPUS_Debug`.
- Generated `create:site` runtime can write profiler traces with `?profiler=1` or `OPUS_PROFILER=1`.

## Next recommended milestones

1. Validate `P7A0E_DEBUG_SHIM_TO_LOGGER_PROFILER` in clean clone.
2. Wire `Runtime/Application` to configure the Debug bridge when debug/profiler is enabled.
3. Add profiler viewer route later, after trace storage contract is stable.
4. Add DB connection configuration contract and SQLite PDO model smoke.
5. Prepare `pdo_mysql` enablement for MariaDB/MySQL support.

## Operational rule

After every validated milestone, update this file or its successor so a new chat can resume without losing the current OPUS state.
