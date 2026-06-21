# P117SITE18_AUTHORING_COMMANDS_DOCBOOK_INTEGRATION

Status: delivered.

## Goal

Integrate OPUS authoring command documentation into the official versioned Reference Book package.

## Contract

- Reference content is stored under `packages/opus-8.1.0-lysenko-reference-book/resources/reference/`.
- The topic has Markdown, Score, and JSON representations.
- The package manifest exposes a `reference_book_content` section.
- README links the integrated Reference Book content.
- No generated site is created by this integration.
- No framework code is copied into the package.
- No Twig template is introduced.
- No fallback is allowed.

## Smoke

Run:

```text
python tools/smoke_p117site18_authoring_commands_docbook_integration.py
```
