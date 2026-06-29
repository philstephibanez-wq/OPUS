# OPUS P7 Model DataSource ODBC Core

## Decision

OPUS has one official database boundary: ODBC.

All database-facing classes must depend on `Opus\Database\Odbc` or on higher-level OPUS Model adapters backed by `Opus\Database\Odbc`.

Direct database stacks such as MySQL-specific, PostgreSQL-specific, SQLite-specific, native driver calls or scattered connection logic are not official OPUS boundaries for new code.

## Runtime requirement

The PHP runtime must expose the native ODBC extension.

Validated local runtime requirement:

```text
php -m must include odbc
```

`PDO_ODBC` may be present, but the OPUS core boundary for database classes is the native ODBC layer in `Opus\Database\Odbc`.

## Added contracts

- `Opus\Database\Odbc\OdbcDataSourceConfig`
- `Opus\Database\Odbc\OdbcConnectionInterface`
- `Opus\Database\Odbc\NativeOdbcConnection`
- `Opus\Database\Odbc\OdbcColumn`
- `Opus\Database\Odbc\OdbcTableInspector`
- `Opus\Model\ModelField`
- `Opus\Model\TableModel`
- `Opus\Model\ModelRecord`
- `Opus\Model\Adapter\OdbcModelAdapter`

## Architecture

```text
ODBC driver / DSN / DSN-less connection
  -> Opus\Database\Odbc
  -> Opus\Model\Adapter\OdbcModelAdapter
  -> Opus\Model\TableModel / ModelRecord
  -> LSTSAR model-driven jobs
```

## LSTSAR consequence

The existing LSTSAR array/schema core remains a validated temporary core, but the final LSTSAR target is now model-driven:

```text
ODBC -> Model -> LSTSAR -> ODBC
```

LSTSAR must not become a second database abstraction and must not access SQL drivers directly.

## Smoke

`tools/smokes/smoke_p7_model_datasource_odbc_core.php` validates:

- PHP ODBC extension is loaded.
- OPUS ODBC data-source config accepts DSN and DSN-less forms.
- ODBC table metadata converts to OPUS `TableModel`.
- ODBC rows convert to OPUS `ModelRecord` objects.
- Model-backed writes go back through `OdbcConnectionInterface`.
- New Model/Database code does not use forbidden direct database calls.
