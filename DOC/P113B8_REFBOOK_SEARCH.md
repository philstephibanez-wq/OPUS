# P113B8_REFBOOK_SEARCH

## Scope

ASAP_REF_BOOK only.

This milestone adds a server-side RefBook search page and a compact global search field in the persistent header.

## What changed

- Added `ReferenceSearchService`.
- Added `pages/search.twig`.
- Added `page=search` handling in `HomeController` and `/search` handling in `PageController`.
- Added a compact top search form in `layout.twig`.
- Added a direct Search entry in the sidebar.
- Added FR/EN/ES I18N labels for search.
- Added search CSS hooks and card presentation.
- Added `p113b8_refbook_search_smoke.php`.
- Updated previous smoke tests so the CSS cache buster can advance to P113B8 without false failures.

## Search coverage

The search currently scans:

- guide slugs, titles, summaries, reading text and section items;
- domain names and localized domain descriptions;
- source symbols from `var/data/api_reference.generated.json`;
- namespaces, source files, roles, contracts, examples, diagrams and public methods.

## Contract

- No ASAP framework change.
- No Apache/UwAmp change.
- No `.htaccess` change.
- No database change.
- No external search engine.
- No generated index file.
- The generated ASAP source manifest remains the source of truth for symbols.

## Test

```bat
cd /d H:\ASAP_REF_BOOK
php tools\smoke\p113b8_refbook_search_smoke.php
php tools\smoke\p113b7_theme_selector_smoke.php
php tools\smoke\p113b6_sidebar_content_polish_smoke.php
php tools\smoke\p113b5_header_language_breadcrumb_smoke.php
```

Expected:

```text
P113B8_REFBOOK_SEARCH_SMOKE_OK
P113B7_THEME_SELECTOR_SMOKE_OK
P113B6_SIDEBAR_CONTENT_POLISH_SMOKE_OK
P113B5_HEADER_LANGUAGE_BREADCRUMB_SMOKE_OK
```
