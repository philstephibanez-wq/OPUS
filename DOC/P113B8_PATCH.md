# P113B8 Patch

## Full files delivered

- `application/reference/Controller/AbstractRefBookController.php`
- `application/reference/Controller/HomeController.php`
- `application/reference/Controller/PageController.php`
- `application/reference/Service/ReferenceSearchService.php`
- `application/reference/templates/layout.twig`
- `application/reference/templates/pages/search.twig`
- `content/refbook/i18n/fr.json`
- `content/refbook/i18n/en.json`
- `content/refbook/i18n/es.json`
- `public/assets/css/refbook.css`
- `tools/smoke/p113b8_refbook_search_smoke.php`
- `tools/smoke/run_p113b8_refbook_search_smoke.cmd`
- `tools/smoke/p113b7_theme_selector_smoke.php`
- `tools/smoke/p113b6_sidebar_content_polish_smoke.php`
- `tools/smoke/p113b5_header_language_breadcrumb_smoke.php`
- `DOC/P113B8_REFBOOK_SEARCH.md`
- `DOC/P113B8_PATCH.md`
- `DOC/P113B8_CHANGELOG.md`
- `DOC/P113B8_TODO.md`

## Notes

The previous smoke tests were updated only to accept the newer CSS cache buster `P113B8`; their original functional checks remain intact.
