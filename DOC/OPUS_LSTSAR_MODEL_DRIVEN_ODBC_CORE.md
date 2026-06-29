# P7 — LSTSAR model-driven ODBC core

## Contract

`P7_LSTSAR_MODEL_DRIVEN_ODBC_CORE` implements the real six-stage model-driven LSTSAR execution core.

LSTSAR means:

```text
Load / Securize / Transform / Store / Archive / Report
```

## Scope

This milestone keeps the explicit user-prepared stage files:

```text
Opus/Lstsar/01_Load.php
Opus/Lstsar/02_Secure.php
Opus/Lstsar/03_Transform.php
Opus/Lstsar/04_Store.php
Opus/Lstsar/05_Archive.php
Opus/Lstsar/06_Report.php
```

It adds:

- `LstsarModelDrivenOdbcEngine`
- `LstsarModelDrivenOdbcRunResult`
- `LstsarOdbcSourceReaderInterface`
- `LstsarOdbcDestinationWriterInterface`
- `LstsarNativeOdbcSourceReader`
- `LstsarOdbcCrudDestinationWriter`
- in-memory readers/writers for deterministic tests and demos

## Guarantees

- ODBC source and destination are still configured separately.
- Source and destination models are explicit `TableModel` objects.
- Mapping is source-field to destination-field.
- Values are transformed before Store.
- Store validates through the destination model.
- Destination writing can go through the guarded ODBC CRUD service.
- Archive and Report remain first-class stages.
- No DDL or raw SQL console is introduced.
- Legacy LSTSAR contract remains green.
