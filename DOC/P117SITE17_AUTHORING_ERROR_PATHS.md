# P117SITE17 - Authoring command explicit error paths

## Status

Delivered runner.

## Contract

Generated-site authoring commands must fail loudly and before partial writes whenever possible.

Covered paths:

- duplicate module;
- duplicate page template;
- duplicate route path;
- duplicate rubric route path without partial module creation;
- invalid module id;
- invalid page id;
- invalid route path;
- missing `--write`.

## Invariant

`sites/skeleton` remains a generated artifact and is deleted by the smoke.
