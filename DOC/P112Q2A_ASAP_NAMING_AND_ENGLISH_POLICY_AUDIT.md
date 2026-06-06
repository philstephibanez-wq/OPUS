# P112Q2A — ASAP Naming and English Policy Audit

## Purpose

P112Q2A installs an audit-only step before any risky framework renaming.

It checks:

- framework directory naming consistency;
- legacy uppercase directory segments;
- French/franglais tokens in source and documentation files;
- proposed normalized English names;
- risk classification for future rename steps.

## Decision

ASAP uses the following target policy:

`framework/Asap/<NamespaceSegment>/<ClassName>.php`

Directory segments are PascalCase and aligned with PHP namespaces.

Examples:

- `Routing`
- `Template`
- `Theme`
- `I18n`
- `Link`
- `View`
- `Menu`
- `Mail`
- `Database`

## Language policy

Framework code, comments, diagnostics, docs, and Reference Book pages must use pure English.

French or franglais tokens are audited before correction.

## Non-goals

This step does not rename directories.

This step does not rename public classes or methods.

This step does not add silent fallbacks.

## Runner

`H:\ASAP\tools\automation\p112q2a_naming_english_policy_audit_runner.cmd`

## Reports

Generated under:

`H:\ASAP_REF_BOOK\var\reports\asap_naming_english_policy_<timestamp>\`

Files:

- `asap_directory_case_policy_audit.csv`
- `asap_english_policy_audit.csv`
- `asap_naming_english_policy_summary.json`
- `asap_naming_english_policy_audit.md`

## Next step

P112Q2B should use the report to normalize directory casing and safe English naming in controlled batches.
