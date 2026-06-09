# P114D3 — ASAP RefBook documentation I18N missing extractor and candidates

## Scope

Adds a semi-automatic extractor for ASAP RefBook source documentation I18N.

The extractor scans the official ASAP RefBook snapshot provider, compares every source documentation sentence with the ASAP documentation I18N catalog, then writes a reviewable report and candidate catalog.

## Contract

- No automatic write into the official catalog.
- Missing translations are detected and reported.
- Candidate translations are generated as review material only.
- Technical identifiers are ignored.
- The generated report is explicit and traceable.

## Commands

```cmd
tools\i18n\run_p114d3_refbook_doc_i18n_missing_extractor.cmd
tools\smoke\run_p114d3_refbook_doc_i18n_missing_extractor_smoke.cmd
```

## Generated outputs

Default output directory:

```text
DOC/reports/P114D3_REFBOOK_DOC_I18N
```

Generated files:

```text
refbook_doc_i18n_missing_source_texts.json
refbook_doc_i18n_candidate_catalog.php
refbook_doc_i18n_summary.txt
```
