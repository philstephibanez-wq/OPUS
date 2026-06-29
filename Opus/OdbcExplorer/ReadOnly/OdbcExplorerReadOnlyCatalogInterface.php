<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer\ReadOnly;

/**
 * Read-only catalog provider for OPUS ODBC Explorer.
 */
interface OdbcExplorerReadOnlyCatalogInterface
{
    public function dataSourceId(): string;

    /** @return list<OdbcExplorerTableReference> */
    public function listTables(): array;
}
