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
