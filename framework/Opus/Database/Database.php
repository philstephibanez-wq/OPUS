<?php

declare(strict_types=1);

namespace Opus\Database;

use PDO;

/*
 * OPUS_REFBOOK:
 *   domain: DATABASE
 *   role: Class Database belongs to the DATABASE Opus framework domain.
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
 * PUBLIC LEGACY-ALIGNED DATABASE WRAPPER
 *
 * Role:
 *   Preserve the original Opus `BDD\Database` domain.
 *
 * Responsibility:
 *   Carry an explicit PDO connection.
 *
 * Contract:
 *   No implicit connection, no global singleton and no silent reconnection.
 *
 * Since:
 *   P112D4C
 */
final class Database
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
