# OPUS — P7 LSTSAR Manager dry-run integration core

## Scope

`P7_LSTSAR_MANAGER_DRY_RUN_INTEGRATION_CORE` connects the protected
`packages/opus-lstsar-manager` dry-run screen to the real OPUS
`LstsarModelDrivenOdbcEngine`.

The milestone is still deliberately non-executing against live databases:

- no direct execution route;
- no raw SQL console;
- no DDL;
- dry-run uses in-memory ODBC source/destination/archive boundaries;
- the real six-stage LSTSAR engine is exercised.

## Preserved LSTSAR stages

```text
01_Load
02_Secure / Securize
03_Transform
04_Store
05_Archive
06_Report
```

## Added / updated components

- `OpusLstsarManager\DryRun\LstsarManagerDryRunService`
- updated `LstsarManagerDeclarationRepository`
- updated `LstsarManagerViewModelFactory`
- updated `DryRunController`
- updated manager manifest / ACL / profiler / i18n / dry-run template
- `tools/smokes/smoke_p7_lstsar_manager_dry_run_integration_core.php`

## Guarantees

- source and destination models are declared by the manager repository;
- source and destination ODBC endpoints remain explicit;
- mapping is declared source field -> destination field;
- dry-run calls `LstsarModelDrivenOdbcEngine::run()`;
- source, destination and archive adapters are in-memory simulation adapters;
- transformed records and run reports are exposed in the dry-run view-model;
- direct execution remains forbidden.
