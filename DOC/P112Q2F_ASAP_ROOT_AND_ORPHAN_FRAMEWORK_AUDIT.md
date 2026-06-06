# P112Q2F — ASAP Root and Orphan Framework Audit

## Purpose

P112Q2F audits the remaining framework cleanliness issues after directory naming normalization.

It checks:

- PHP files directly under `framework/Asap`;
- empty or quasi-empty framework directories;
- directories with no PHP classes;
- semantic duplicate candidates such as `Render` vs `Renderer`;
- candidate cleanup targets for P112Q2G.

## Decision

The target framework layout is:

`framework/Asap/<Domain>/<ClassName>.php`

Direct PHP files under `framework/Asap` are not accepted as the final layout.

Empty decorative directories are not accepted.

Semantic duplicates are not accepted.

## Important

This step is audit-only.

It does not move, rename, delete, or edit framework runtime files.

## Runner

`H:\ASAP\tools\automation\p112q2f_root_orphan_framework_audit_runner.cmd`

## Reports

Generated under:

`H:\ASAP_REF_BOOK\var\reports\asap_root_orphan_framework_audit_<timestamp>\`

Files:

- `asap_root_namespace_files.csv`
- `asap_framework_directories.csv`
- `asap_root_orphan_usages.csv`
- `asap_root_orphan_framework_audit_summary.json`
- `asap_root_orphan_framework_audit.md`

## Next step

P112Q2G should use this audit to move/remove root namespace files and orphan directories in a controlled migration.
