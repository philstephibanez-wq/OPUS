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
