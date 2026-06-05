<?php

declare(strict_types=1);

namespace ASAP\BDD;

use PDO;

/**
 * PUBLIC LEGACY-ALIGNED MYSQL FACTORY
 *
 * Role:
 *   Preserve the original ASAP `BDD\Mysql` domain.
 *
 * Responsibility:
 *   Create explicit PDO MySQL connections from declared parameters.
 *
 * Contract:
 *   No default host/database/user/password. Caller provides everything.
 *
 * Since:
 *   P112D4C
 */
final class Mysql
{
    public function connect(string $host, string $database, string $user, string $password): Database
    {
        if ($host === '' || $database === '' || $user === '') {
            throw new \InvalidArgumentException('ASAP_MYSQL_CONFIGURATION_INVALID');
        }

        $pdo = new PDO(
            'mysql:host=' . $host . ';dbname=' . $database . ';charset=utf8mb4',
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        return new Database($pdo);
    }
}
