<?php

declare(strict_types=1);

namespace Opus\Database;

use PDO;
use PDOException;

/*
 * OPUS_REFBOOK:
 *   domain: DATABASE
 *   role: Class PdoDatabaseConnector belongs to the DATABASE Opus framework domain.
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
 * PUBLIC PDO DATABASE CONNECTOR
 *
 * Role:
 *   Create explicit PDO-backed Opus Database objects.
 *
 * Contract:
 *   Provider driver must exist. No fallback to another database.
 */
final class PdoDatabaseConnector
{
    public function __construct(private readonly DatabaseDsnFactory $dsnFactory = new DatabaseDsnFactory())
    {
    }

    public function connect(DatabaseConnectionConfig $config): Database
    {
        $driver = DatabaseProvider::pdoDriver($config->provider);
        $available = PDO::getAvailableDrivers();

        if (!in_array($driver, $available, true)) {
            throw DatabaseException::because('OPUS_DATABASE_PDO_DRIVER_UNAVAILABLE', $driver);
        }

        try {
            $pdo = new PDO(
                $this->dsnFactory->build($config),
                $config->user,
                $config->password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION] + $config->pdoOptions
            );
        } catch (PDOException $exception) {
            throw DatabaseException::because('OPUS_DATABASE_CONNECTION_FAILED', $exception->getMessage());
        }

        return new Database($pdo);
    }
}
