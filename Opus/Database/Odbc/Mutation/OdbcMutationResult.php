<?php
declare(strict_types=1);

namespace Opus\Database\Odbc\Mutation;

final class OdbcMutationResult
    implements OdbcMutationResultInterface
{
    public function __construct(
        private OdbcMutationSqlPlan $plan,
        private bool $executed,
        private int $affectedRows,
        private string $actorId
    ) {
        if ($affectedRows < 0) {
            throw new \InvalidArgumentException(
                'OPUS_ODBC_MUTATION_AFFECTED_ROWS_INVALID'
            );
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'contract' => 'OPUS_ODBC_MUTATION_RESULT_V1',
            'plan' => $this->plan->toArray(),
            'executed' => $this->executed,
            'affected_rows' => $this->affectedRows,
            'actor_id' => $this->actorId,
        ];
    }
}
