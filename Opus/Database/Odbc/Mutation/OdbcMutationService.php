<?php
declare(strict_types=1);

namespace Opus\Database\Odbc\Mutation;

final class OdbcMutationService
{
    private OdbcMutationSqlBuilder $builder;

    public function __construct(
        private OdbcMutationExecutorInterface $executor,
        private OdbcMutationCapabilities $capabilities,
        private string $expectedConfirmationToken,
        ?OdbcMutationSqlBuilder $builder = null
    ) {
        if (trim($expectedConfirmationToken) === '') {
            throw new \InvalidArgumentException(
                'OPUS_ODBC_MUTATION_CONFIRMATION_TOKEN_EMPTY'
            );
        }

        $this->builder = $builder ?? new OdbcMutationSqlBuilder();
    }

    public function dryRun(
        OdbcMutationCommand $command,
        bool $aclGranted
    ): OdbcMutationResult {
        $this->guard($command, $aclGranted, true);
        $plan = $this->builder->build($command);

        return new OdbcMutationResult(
            $plan,
            false,
            0,
            $command->actorId()
        );
    }

    public function execute(
        OdbcMutationCommand $command,
        bool $aclGranted
    ): OdbcMutationResult {
        $this->guard($command, $aclGranted, false);
        $plan = $this->builder->build($command);
        $affectedRows = $this->executor->execute($plan);

        return new OdbcMutationResult(
            $plan,
            true,
            $affectedRows,
            $command->actorId()
        );
    }

    private function guard(
        OdbcMutationCommand $command,
        bool $aclGranted,
        bool $dryRun
    ): void {
        if (!$aclGranted) {
            throw new \RuntimeException(
                'OPUS_ODBC_MUTATION_ACL_DENIED'
            );
        }

        if (!$this->capabilities->allows($command->action())) {
            throw new \RuntimeException(
                'OPUS_ODBC_MUTATION_CAPABILITY_DENIED: '
                . $command->action()
            );
        }

        if (
            !$dryRun
            && !hash_equals(
                $this->expectedConfirmationToken,
                $command->confirmationToken()
            )
        ) {
            throw new \RuntimeException(
                'OPUS_ODBC_MUTATION_CONFIRMATION_REQUIRED'
            );
        }

        if (!$dryRun && $command->actorId() === '') {
            throw new \RuntimeException(
                'OPUS_ODBC_MUTATION_ACTOR_REQUIRED'
            );
        }
    }
}
