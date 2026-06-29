<?php
declare(strict_types=1);

namespace Opus\Database\Odbc;

/**
 * Optional read-only metadata/query boundary for OPUS ODBC Explorer.
 */
interface OdbcReadOnlyConnectionInterface extends OdbcConnectionInterface
{
    /** @return list<OdbcTable> */
    public function listTables(?string $catalog = null, ?string $schema = null): array;

    public function executeReadOnlyQuery(string $sql, int $limit = 100): OdbcQueryResult;
}
