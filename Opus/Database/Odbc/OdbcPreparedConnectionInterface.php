<?php
declare(strict_types=1);

namespace Opus\Database\Odbc;

interface OdbcPreparedConnectionInterface
{
    /**
     * @param list<mixed> $parameters
     */
    public function executePrepared(
        string $sql,
        array $parameters
    ): int;
}
