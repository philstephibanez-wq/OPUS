# P112Q2R — ASAP RefBook Source Tag Contract

## Decision

The ASAP Reference Book must be built from ASAP itself:

```text
ASAP source code
  docblocks / ASAP_REFBOOK source tags
        ↓
official extractor
        ↓
structured manifest
        ↓
ASAP_REF_BOOK controllers/routes
        ↓
ASAP Twig templates and partials
        ↓
HTML Reference Book
```

The Reference Book must not be a set of generated Markdown pages pretending to be a framework reference.

## Source tag block

Every public framework class that must appear in the Reference Book may expose one explicit source block:

```php
/**
 * ASAP_REFBOOK:
 *   domain: Template
 *   role: Render a view-model through Twig.
 *   contract:
 *     - no business logic in templates
 *     - explicit template missing error
 *     - explicit variables
 *   examples:
 *     - template-basic
 *   diagrams:
 *     - template-runtime
 * END_ASAP_REFBOOK
 */
final class TwigTemplateRenderer
{
}
```

## Rules

- Tags live in ASAP source docblocks, not in the RefBook app.
- Extraction must be read-only over ASAP source files.
- The extractor must fail explicitly on malformed blocks.
- The RefBook app renders data with ASAP controllers and Twig templates.
- Markdown is allowed only for editorial/manual pages, not for API class/method rendering.
- Tables, method signatures, diagrams and examples are rendered by Twig partials.
- No JavaScript hack may decode escaped HTML to simulate rendering.
- No generated HTML stored inside Markdown.

## Required fields

- `domain`
- `role`
- `contract`

## Optional fields

- `examples`
- `diagrams`
- `related`
- `since`
- `visibility`

## Next palier

`P112Q2S_ASAP_REFBOOK_TWIG_REFERENCE_ENGINE` must introduce the ASAP_REF_BOOK Twig rendering engine consuming the extracted manifest.