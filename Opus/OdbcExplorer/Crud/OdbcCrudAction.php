<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer\Crud;

/**
 * Supported guarded CRUD actions for OPUS ODBC Explorer.
 */
final class OdbcCrudAction
{
    public const INSERT = 'insert';
    public const UPDATE = 'update';
    public const DELETE = 'delete';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::INSERT, self::UPDATE, self::DELETE];
    }

    public static function assertSupported(string $action): string
    {
        $action = strtolower(trim($action));
        if (!in_array($action, self::all(), true)) {
            throw new \InvalidArgumentException('OPUS_ODBC_CRUD_ACTION_UNSUPPORTED: ' . $action);
        }

        return $action;
    }

    public static function isDestructive(string $action): bool
    {
        $action = self::assertSupported($action);
        return $action === self::UPDATE || $action === self::DELETE;
    }
}
