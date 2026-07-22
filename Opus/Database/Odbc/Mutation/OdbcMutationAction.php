<?php
declare(strict_types=1);

namespace Opus\Database\Odbc\Mutation;

final class OdbcMutationAction
    implements OdbcMutationActionInterface
{
    public const INSERT = 'insert';
    public const UPDATE = 'update';
    public const DELETE = 'delete';

    public static function assert(string $action): string
    {
        $action = strtolower(trim($action));

        if (!in_array(
            $action,
            [self::INSERT, self::UPDATE, self::DELETE],
            true
        )) {
            throw new \InvalidArgumentException(
                'OPUS_ODBC_MUTATION_ACTION_INVALID: ' . $action
            );
        }

        return $action;
    }

    private function __construct()
    {
    }
}
