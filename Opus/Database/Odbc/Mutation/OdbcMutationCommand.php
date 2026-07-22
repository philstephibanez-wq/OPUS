<?php
declare(strict_types=1);

namespace Opus\Database\Odbc\Mutation;

final class OdbcMutationCommand
    implements OdbcMutationCommandInterface
{
    private string $action;
    private string $table;

    /** @var array<string,mixed> */
    private array $values;

    private ?OdbcMutationPredicate $predicate;

    public function __construct(
        string $action,
        string $table,
        array $values = [],
        ?OdbcMutationPredicate $predicate = null,
        private string $actorId = '',
        private string $confirmationToken = ''
    ) {
        $this->action = OdbcMutationAction::assert($action);
        $this->table = $this->assertTable($table);
        $this->values = $this->assertValues($values);
        $this->predicate = $predicate;
        $this->assertShape();
    }

    public function action(): string
    {
        return $this->action;
    }

    public function table(): string
    {
        return $this->table;
    }

    /** @return array<string,mixed> */
    public function values(): array
    {
        return $this->values;
    }

    public function predicate(): ?OdbcMutationPredicate
    {
        return $this->predicate;
    }

    public function actorId(): string
    {
        return trim($this->actorId);
    }

    public function confirmationToken(): string
    {
        return $this->confirmationToken;
    }

    private function assertShape(): void
    {
        if (
            in_array(
                $this->action,
                [
                    OdbcMutationAction::INSERT,
                    OdbcMutationAction::UPDATE,
                ],
                true
            )
            && $this->values === []
        ) {
            throw new \InvalidArgumentException(
                'OPUS_ODBC_MUTATION_VALUES_REQUIRED: '
                . $this->action
            );
        }

        if (
            in_array(
                $this->action,
                [
                    OdbcMutationAction::UPDATE,
                    OdbcMutationAction::DELETE,
                ],
                true
            )
            && !$this->predicate instanceof OdbcMutationPredicate
        ) {
            throw new \InvalidArgumentException(
                'OPUS_ODBC_MUTATION_PREDICATE_REQUIRED: '
                . $this->action
            );
        }

        if (
            $this->action === OdbcMutationAction::INSERT
            && $this->predicate !== null
        ) {
            throw new \InvalidArgumentException(
                'OPUS_ODBC_MUTATION_INSERT_PREDICATE_FORBIDDEN'
            );
        }

        if (
            $this->action === OdbcMutationAction::DELETE
            && $this->values !== []
        ) {
            throw new \InvalidArgumentException(
                'OPUS_ODBC_MUTATION_DELETE_VALUES_FORBIDDEN'
            );
        }
    }

    private function assertTable(string $table): string
    {
        $parts = explode('.', trim($table));

        if (
            $parts === []
            || count($parts) > 3
        ) {
            throw new \InvalidArgumentException(
                'OPUS_ODBC_TABLE_IDENTIFIER_INVALID: ' . $table
            );
        }

        foreach ($parts as $part) {
            OdbcMutationPredicate::assertIdentifier($part);
        }

        return implode('.', $parts);
    }

    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    private function assertValues(array $values): array
    {
        foreach ($values as $column => $_value) {
            OdbcMutationPredicate::assertIdentifier(
                (string) $column
            );
        }

        return $values;
    }
}
