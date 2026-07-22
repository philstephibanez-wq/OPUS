<?php
declare(strict_types=1);

namespace Opus\Database\Odbc\Mutation;

final class OdbcMutationPredicate
    implements OdbcMutationPredicateInterface
{
    /** @var array<string,mixed> */
    private array $conditions;

    /**
     * @param array<string,mixed> $conditions
     */
    public function __construct(array $conditions)
    {
        if ($conditions === []) {
            throw new \InvalidArgumentException(
                'OPUS_ODBC_MUTATION_PREDICATE_EMPTY'
            );
        }

        foreach ($conditions as $column => $_value) {
            self::assertIdentifier((string) $column);
        }

        $this->conditions = $conditions;
    }

    /**
     * @return array<string,mixed>
     */
    public function conditions(): array
    {
        return $this->conditions;
    }

    public static function assertIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);

        if (
            preg_match(
                '/^[A-Za-z_][A-Za-z0-9_]*$/',
                $identifier
            ) !== 1
        ) {
            throw new \InvalidArgumentException(
                'OPUS_ODBC_IDENTIFIER_INVALID: ' . $identifier
            );
        }

        return $identifier;
    }
}
