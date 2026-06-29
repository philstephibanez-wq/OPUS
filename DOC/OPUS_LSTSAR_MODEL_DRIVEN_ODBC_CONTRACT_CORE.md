# OPUS — P7 LSTSAR model-driven ODBC contract core

## Canonical meaning

LSTSAR means:

1. Load
2. Securize
3. Transform
4. Store
5. Archive
6. Report

The historical preparation files are now real contract stage files:

- `Opus/Lstsar/01_Load.php`
- `Opus/Lstsar/02_Secure.php`
- `Opus/Lstsar/03_Transform.php`
- `Opus/Lstsar/04_Store.php`
- `Opus/Lstsar/05_Archive.php`
- `Opus/Lstsar/06_Report.php`

`02_Secure.php` keeps the historical filename, while the canonical stage name is `securize`.

## Architecture

This milestone preserves the legacy `LstsarEngine::process()` API and adds the explicit six-stage model-driven contract.

New contract objects:

- `LstsarStageName`
- `LstsarStageInterface`
- `LstsarStageResult`
- `LstsarConfig`
- `LstsarContext`
- `LstsarBackofficeDeclaration`

The engine exposes the canonical stage catalog through:

- `LstsarEngine::defaultStageClasses()`
- `LstsarEngine::stageClasses()`

## Heterogeneous database rule

LSTSAR is generic and ODBC-first.

The configuration declares:

- source ODBC datasource id;
- source OPUS TableModel id;
- destination ODBC datasource id;
- destination OPUS TableModel id;
- field mapping;
- security policy;
- transform rules;
- archive policy;
- report policy.

No concrete database engine is allowed to leak into LSTSAR. MySQL, SQL Server, Access, Oracle, PostgreSQL or SQLite are all seen through ODBC datasource declarations and OPUS Model contracts.

## Backoffice target

The future `P7_LSTSAR_MANAGER_PACKAGE_CORE` should provide an OPUS application package to declare and maintain:

- source datasources;
- destination datasources;
- source models;
- destination models;
- mappings;
- securize policies;
- transform rules;
- store constraints;
- archive policies;
- report outputs;
- dry-run tests;
- run/audit/profiler views.

## Non-goals of this milestone

This milestone does not implement heavy ODBC read/write execution.

It defines the stable contract so the next implementation can connect the six stages to the ODBC Explorer, Model refinement and guarded CRUD layers.
