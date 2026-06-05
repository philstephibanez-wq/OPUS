<?php

declare(strict_types=1);

namespace ASAP\BDD;

use PDO;

/**
 * PUBLIC LEGACY-ALIGNED DATABASE WRAPPER
 *
 * Role:
 *   Preserve the original ASAP `BDD\Database` domain.
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
