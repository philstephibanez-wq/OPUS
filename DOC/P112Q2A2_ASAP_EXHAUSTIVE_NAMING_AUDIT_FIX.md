# P112Q2A2 — Exhaustive ASAP Naming Audit Fix

## Cause

P112Q2A intentionally started small, but its directory audit was not exhaustive enough for the user requirement:

> all framework directories must follow one coherent naming policy.

The first audit only flagged the initially listed legacy folders and accepted some uppercase acronym folders too easily.

## Fix

P112Q2A2 adds an exhaustive audit.

It scans every directory under:

`H:\ASAP\framework/Asap`

It reports:

- current directory segment;
- proposed target segment;
- PHP namespaces found under the directory;
- namespace segment currently used;
- risk classification.

## Classifications

- `SAFE_DIRECTORY_CASE_ONLY`
- `SAFE_EMPTY_OR_DOC_DIRECTORY_CASE_RENAME`
- `RISKY_NAMESPACE_AND_DIRECTORY_RENAME`
- `RISKY_MIXED_NAMESPACE_SEGMENTS`
- `RISKY_ENGLISH_DOMAIN_RENAME`
- `ALREADY_TARGET`

## Non-goals

This step does not rename anything.

This step does not change namespaces.

This step does not add compatibility fallbacks.

## Runner

`H:\ASAP\tools\automation\p112q2a2_exhaustive_naming_audit_runner.cmd`

## Reports

Generated under:

`H:\ASAP_REF_BOOK\var\reports\asap_exhaustive_naming_audit_<timestamp>\`
