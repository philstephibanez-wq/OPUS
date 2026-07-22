<?php
declare(strict_types=1);

namespace Opus\Database\Odbc\Mutation;

use Opus\Database\Odbc\OdbcPreparedConnectionInterface;

final class OdbcNativeMutationExecutor
    implements OdbcNativeMutationExecutorInterface
{
    public function __construct(
        private OdbcPreparedConnectionInterface $connection
    ) {
    }

    public function execute(
        OdbcMutationSqlPlan $plan
    ): int {
        return $this->connection->executePrepared(
            $plan->sql(),
            $plan->parameters()
        );
    }
}
