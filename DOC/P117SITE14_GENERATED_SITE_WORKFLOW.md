# OPUS P117SITE14 generated-site workflow contract

Status: IMPLEMENTED_BY_RUNNER_PENDING_LOCAL_VALIDATION

## User workflow

Route -> module -> template -> i18n -> assets.

A generated OPUS site must be understandable without opening framework internals.
For a visible page, the user should follow this path:

1. Public URL is declared in `application/config/routes.json`.
2. The route points to a declared module and template.
3. The module lives under `application/modules/<Module>`.
4. The page markup lives in `templates/pages/*.score`.
5. The visible text lives in `resources/i18n/<locale>.json`.
6. Shared layout/components live in `application/common/templates`.
7. Visual styling lives in public or module-owned assets.

## Contracts

- No wild page creation.
- No page outside a module.
- No home card without a declared route/module.
- No JSON page layout as source of truth.
- No HTML concatenation in controllers or services.
- `.score` is the rendering layer.
- `ScoreTemplateRenderer` is the only generated-site template renderer.

## Score syntax

- `{{ value }}`: escaped text.
- `{{{ value }}}`: explicit raw HTML slot.
- `[[ ignore ]] ... [[ endignore ]]`: source-only block, not rendered.

## Regression gate

The P117SITE14 smoke generates `sites/skeleton`, validates it, checks generated docs, then removes `sites/skeleton`.
