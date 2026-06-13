<?php

declare(strict_types=1);

namespace Opus\Database;

/*
 * OPUS_REFBOOK:
 *   domain: DATABASE
 *   role: Class Sqlite belongs to the DATABASE Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the DATABASE domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - database-overview
 *   diagrams:
 *     - database-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC SQLITE FACTORY
 */
final class Sqlite
{
    public function connect(string $path): Database
    {
        return (new PdoDatabaseConnector())->connect(
            new DatabaseConnectionConfig(DatabaseProvider::SQLITE, null, null, null, ['path' => $path])
        );
    }
}
