<?php
declare(strict_types=1);

namespace Opus\Database\Odbc\Mutation;

interface OdbcMutationExecutorInterface
{
    public function execute(
        OdbcMutationSqlPlan $plan
    ): int;
}
