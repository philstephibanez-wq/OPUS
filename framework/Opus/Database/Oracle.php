<?php

declare(strict_types=1);

namespace Opus\Database;

/*
 * OPUS_REFBOOK:
 *   domain: DATABASE
 *   role: Class Oracle belongs to the DATABASE Opus framework domain.
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
 * PUBLIC ORACLE FACTORY
 */
final class Oracle
{
    public function connect(string $host, string $service, string $user, string $password, ?string $port = null): Database
    {
        $parameters = [
            'host' => $host,
            'service' => $service,
        ];

        if ($port !== null) {
            $parameters['port'] = $port;
        }

        return (new PdoDatabaseConnector())->connect(
            new DatabaseConnectionConfig(DatabaseProvider::ORACLE, null, $user, $password, $parameters)
        );
    }
}
