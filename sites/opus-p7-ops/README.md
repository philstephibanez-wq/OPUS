# OPUS P7 OPS test site

Local test surface for P7 LSTSAR manager dashboard operations.

Routes:

- http://127.0.0.1:8078/opus-lstsar-manager
- http://127.0.0.1:8078/opus-lstsar-manager/operations

## P7_OPS_ACTIONS_SUITE_CORE

- `/opus-lstsar-manager/action` exposes controlled OPS actions.
- Supported actions: `preview`, `dry-run`, `audit`.
- All actions are read-only in this harness: `side_effects=false`.
- Unknown action returns HTTP 400.
- Unknown operation returns HTTP 404.

## P7_OPS_COMMAND_CENTER_CORE

- Adds `/opus-lstsar-manager/command` and `/opus-lstsar-manager/command-center`.
- Provides OPS summary, operations table, quick action links and diagnostics.
- Keeps command previews read-only with `side_effects=false`.

## P7_OPS_NAVIGATION_POLISH_CORE

- Adds shared OPS navigation styling through public/ops-ui.css.
- Adds /opus-lstsar-manager/navigation and /opus-lstsar-manager/navigation-polish.
- Keeps action links visible with wrapped quick-action clusters.
- Keeps action previews read-only with side_effects=false.

## P7_OPS_RUNTIME_DIAGNOSTICS_CORE

- Adds /opus-lstsar-manager/diagnostics and /opus-lstsar-manager/runtime-diagnostics.
- Reports PHP runtime, Composer autoload, public files, routes and operations view-model status.
- Keeps diagnostics read-only with side_effects=false.

## P7_OPS_SITE_HEALTH_HUB_CORE

- Adds `/opus-lstsar-manager/health` and `/opus-lstsar-manager/health-hub`.
- Summarizes Dashboard, Operations, Command Center, Navigation and Diagnostics readiness.
- Reports route matrix, public file matrix and regression smoke matrix.
- Keeps the health page read-only with `side_effects=false`.

## P7_OPS_UI_DISTINCTION_WRAP_CORE

- Splits Dashboard and Operations into visually distinct experiences.
- Dashboard now shows overview, quick access and compact digest.
- Operations now shows a detailed console with source/destination summaries.
- Global CSS wraps long technical values and prevents table overflow.

## P7_OPS_LANGUAGE_SELECTOR_CORE

- Adds a global FR / EN selector to the OPS pages.
- Preserves the current `site` value while switching `lang=fr` / `lang=en`.
- Propagates `site` and `lang` to OPS navigation links.
- Provides explicit translation helpers for navigation labels.
- Covered by `tools/smokes/smoke_p7_ops_language_selector_core.php`.

## P7_OPS_LANGUAGE_SELECTOR_EUROPEAN_CORE

- Replaces the two-button FR/EN language selector with a scalable select dropdown.
- The default site language registry is European languages + Ukrainian.
- Preserves the current `site` and other safe query parameters while switching `lang`.
- Keeps the existing FR/EN contract markers for backward compatibility.
- Covered by `tools/smokes/smoke_p7_ops_language_selector_european_core.php`.

## P7_OPS_LANGUAGE_SELECTOR_EU_UKRAINIAN_CORE

- Replaces the two-button FR/EN language selector with a scalable select dropdown.
- Scope is strictly the 24 official EU languages + Ukrainian.
- Preserves the current `site` and other safe query parameters while switching `lang`.
- Keeps the previous `P7_OPS_LANGUAGE_SELECTOR_CORE` contract marker for backward compatibility.
- Covered by `tools/smokes/smoke_p7_ops_language_selector_eu_ukrainian_core.php`.

## P7_OPS_I18N_NATIVE_URL_SLUGS_CORE

- Keeps readable localized URL slugs in native characters when the language uses accents or non-Latin scripts.
- Scope is the 24 official EU languages + Ukrainian.
- Examples: `/français/opérations`, `/español/panel`, `/português/operações`, `/čeština/přehled`, `/українська/операції`.
- Canonical technical query codes remain short ISO-like values such as `lang=fr`, `lang=es`, `lang=pt`, `lang=cs`, `lang=uk`.
- Router accepts both visible native Unicode paths and percent-encoded UTF-8 paths.
- Covered by `tools/smokes/smoke_p7_ops_i18n_native_url_slugs_core.php`.

## P7_OPS_I18N_PAGE_TRANSLATIONS_CORE

- Adds a real visible translation layer for OPS pages when `lang` is explicit or a native URL is used.
- Covers the 24 official EU languages + Ukrainian.
- Translates visible OPS labels across Dashboard, Operations, Command Center, Navigation, Diagnostics and Health Hub.
- Keeps technical values and operation identifiers unchanged.
- Preserves native URL slugs with accents/non-Latin characters.
- Covered by `tools/smokes/smoke_p7_ops_i18n_page_translations_core.php`.

## P7_OPS_I18N_VISIBLE_STRINGS_FIX_CORE

- Completes visible OPS page fragments that remained mixed after the first page-translation pass.
- Covers counters, statuses, table headers, dashboard overview/digest, summary cards and next-step instructions.
- Keeps technical operation identifiers, source paths and destination paths unchanged.
- Covered by `tools/smokes/smoke_p7_ops_i18n_visible_strings_fix_core.php`.

## P7_OPS_I18N_EN_FRENCH_LEAK_LOCK_CORE

- Adds an English anti-leak translation layer for French fragments that remain in OPS pages.
- Renders OPS public pages with `lang=en` and rejects visible French UI fragments.
- Language names in the selector and technical operation/path identifiers are intentionally allowed.
- Covered by `tools/smokes/smoke_p7_ops_i18n_en_french_leak_lock_core.php`.

## P7_OPS_I18N_EN_VISIBLE_LEAK_LOCK_CORE

- Locks English OPS pages against remaining visible French fragments.
- Covers the operations-console sentence, counters, cards, statuses and table labels.
- Technical operation identifiers and source/destination path values remain untranslated.
- Covered by `tools/smokes/smoke_p7_ops_i18n_en_visible_leak_lock_core.php`.

## P7_OPS_ACCESS_LOG_MINIMUM_CORE

- Adds a minimum JSON-lines access log for URLs reaching the OPS router.
- Target file: `var/logs/opus_lstsar-manager/access.log`.
- Logged fields: timestamp, event, method, URI, decoded path, query string, remote address and user agent.
- `ERR_CONNECTION_REFUSED` cannot be logged by the app because no PHP process receives the request.
- Covered by `tools/smokes/smoke_p7_ops_access_log_minimum_core.php`.

## P7_OPS_EN_NAVIGATION_AND_PROFESSIONAL_TEXT_CORE

- Locks English navigation pages against remaining French UI fragments.
- Adds professional text-length handling for tables, key/value cards and technical identifiers.
- Keeps technical values such as operation names, DSNs, model names and table names unchanged.
- Covered by `tools/smokes/smoke_p7_ops_en_navigation_profiler_text_core.php`.

## P7_OPS_PROFILER_AND_ACCESS_LOG_CORE

- Adds `var/logs/opus_lstsar-manager/access.log` with one JSON line per routed request.
- Adds `var/logs/opus_lstsar-manager/profiler.log` with duration, status and peak memory per routed request.
- The app cannot log `ERR_CONNECTION_REFUSED` because no PHP process receives that request.

## P7_OPS_CHAIN_AUTH_ENV_CORE

- Adds controlled login/logout/sign-in for OPS pages.
- Adds minimum environment management with `config/environment.dev.php`, `config/environment.prod.example.php` and active `config/environment.php`.
- Adds the full dependency chain: SSO/AuthN, RBAC, FSM, CL, Models, Database/tables, ODBC Manager, LSTSAR, logs/profiler.
- Adds `access.log`, `auth.log` and `profiler.log` under `var/logs/opus_lstsar-manager`.
- Dev login default: `admin` / `admin`; production must replace the password hash explicitly.

## P7_OPS_CHAIN_AUTH_ENV_FIX_CORE

- Fixes the chain auth environment smoke marker for `environment.prod.example.php`.
- Replaces profiler clock math with `microtime(true)` to avoid float-to-int warnings on PHP/Windows.

## P7_OPS_CHAIN_AUTH_ENV_UI_FIX_CORE

- Fixes `environment.prod.example.php` smoke marker after the chain/auth/env delivery.
- Replaces profiler timing math with `microtime(true)` to avoid PHP/Windows float-to-int warnings.
- Prevents header navigation and language selector overlap.
- Keeps runtime logs under `var/logs/opus_lstsar-manager/` while excluding `.log` files from Git.

## P7_OPS_SYMFONY_STYLE_PROFILER_SESSION_CORE

- Replaces the mini profiler display with a Symfony-style toolbar and typed profiler page.
- `profiler=1` is stored in session as `p7ops_sf_profiler_enabled` and remains active until `/opus-lstsar-manager/profiler/exit` or `profiler=0`.
- Adds profiler panels: Request, Performance, Session, Auth/SSO, OPS Chain, Logs and Config.
- Clarifies the full chain: Auth/SSO, RBAC, FSM, CL, Models, Database/tables, ODBC Manager, LSTSAR, Actions, Logs/Profiler.
- Improves technical identifier rendering by avoiding ugly forced breaks in DSN/model/table values.

## P7_OPS_PROFILER_CHAIN_CLEANUP_CORE

- Replaces the confusing profiler attempts with one clean typed profiler implementation.
- `profiler=1` is stored in session as `p7ops_clean_profiler_enabled` until `/opus-lstsar-manager/profiler/exit` or `profiler=0`.
- Static assets such as `/favicon.ico` and `/ops-ui.css` are never captured as profiler pages.
- Clarifies the OPS chain: Auth/SSO, RBAC, FSM, CL, Models, Database/Tables, ODBC Manager, LSTSAR, Actions, Logs/Profiler.

## P7_OPS_UNIFIED_ERGONOMIC_NAVIGATION_CORE

- Adds one stable professional navigation for every OPS page.
- Groups routes into Pilotage, Chaîne and Observabilité instead of random back/other links.
- Preserves site, lang and session profiler context across navigation.
- Removes legacy floating language/navigation headers from rendered pages.

## P7_OPS_PROFILER_EXIT_FIX_CORE

- Fixes profiler exit by clearing all legacy and current profiler session flags.
- Sanitizes the redirect target by removing `profiler`, `profile` and `_profiler` query parameters.
- Prevents profiler/navigation output buffers from starting on the profiler exit route.

## P7_OPS_PROFILER_VISIBLE_MODE_CORE

- Makes profiler mode visually obvious: page outline, amber debug background and persistent bottom ribbon.
- The ribbon shows current path, request duration, Open profiler and Exit actions.
- Static assets are excluded from visible profiler decoration.

## P7_OPS_PROFILER_OPEN_CONTEXT_CORE

- Stores the last non-profiler application URL in session.
- On profiler pages, the visible profiler ribbon shows `Back to app` instead of a no-op `Open profiler` link.
- On application pages, the same ribbon still opens `/opus-lstsar-manager/profiler`.
