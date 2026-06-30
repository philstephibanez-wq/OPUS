# OPUS — LSTSAR script necessity audit

## Scope

This audit answers the question: are all current LSTSAR scripts necessary?

## Summary

No runtime LSTSAR class is removed in this milestone.

The current number of files is justified by the separation of responsibilities:

- six explicit stage files required by the user-facing LSTSAR architecture;
- immutable config/context/result objects;
- source/destination ODBC boundaries;
- deterministic in-memory adapters for smokes and demos;
- native ODBC/CRUD adapters for real execution boundaries;
- Manager package separated from the core engine;
- smokes and patch tools outside runtime.

## Required runtime core

These files are part of the runtime contract and should stay:

- `01_Load.php`
- `02_Secure.php`
- `03_Transform.php`
- `04_Store.php`
- `05_Archive.php`
- `06_Report.php`
- `LstsarStageName.php`
- `LstsarStageInterface.php`
- `LstsarStageResult.php`
- `LstsarViolation.php`
- `LstsarConfig.php`
- `LstsarContext.php`
- `LstsarModelDrivenOdbcEngine.php`
- `LstsarModelDrivenOdbcRunResult.php`

Reason: they represent the explicit `Load / Securize / Transform / Store / Archive / Report` architecture and should not be collapsed into an opaque pipeline.

## Required runtime boundaries

These files should stay:

- `LstsarOdbcSourceReaderInterface.php`
- `LstsarOdbcDestinationWriterInterface.php`
- `LstsarNativeOdbcSourceReader.php`
- `LstsarOdbcCrudDestinationWriter.php`

Reason: they keep LSTSAR generic and ODBC-only without coupling the engine to one database or one storage strategy.

## Required deterministic adapters

These files should stay for smokes, demos and dry-run previews:

- `LstsarInMemoryOdbcSourceReader.php`
- `LstsarInMemoryOdbcDestinationWriter.php`
- `InMemoryLstsarStore.php`

Reason: they allow deterministic validation without needing a real DSN, and they are used by the Manager dry-run integration.

## New assignment hook files

These files are required by destination-field assignment support:

- `LstsarTransformHookInterface.php`
- `LstsarTransformHookContext.php`
- `LstsarTransformHookRegistry.php`

Reason: they allow computed destination fields without putting unsafe executable code in config.

## Non-runtime files

Files under these folders are not runtime engine files:

- `tools/smokes/`
- `tools/patches/`
- `DOC/`
- `packages/opus-lstsar-manager/`

They are validation, migration, documentation or UI/application files.

## Future cleanup recommendation

Later, after P7 stabilizes, old `tools/patches/update_p7_*` scripts can be moved to an archive folder or removed if the project decides that Git history is sufficient.

Do not remove them during the current engine/dashboard work because they document milestone reproducibility.

## Conclusion

The file count is high but currently justified.

Immediate cleanup is not recommended. The next sensible improvement is documentation and dashboard grouping, not file deletion.
