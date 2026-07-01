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
