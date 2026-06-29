<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer\Crud;

/**
 * Structured predicate for UPDATE/DELETE guards.
 */
final class OdbcCrudPredicate
{
    /** @var array<string,mixed> */
    private array $criteria;

    /** @param array<string,mixed> $criteria */
    public function __construct(array $criteria = [])
    {
        $normalized = [];
        foreach ($criteria as $field => $value) {
            $field = trim((string) $field);
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
                throw new \InvalidArgumentException('OPUS_ODBC_CRUD_PREDICATE_FIELD_INVALID: ' . $field);
            }
            if (!is_null($value) && !is_scalar($value)) {
                throw new \InvalidArgumentException('OPUS_ODBC_CRUD_PREDICATE_VALUE_INVALID: ' . $field);
            }
            $normalized[$field] = $value;
        }

        $this->criteria = $normalized;
    }

    /** @param array<string,mixed> $criteria */
    public static function fromCriteria(array $criteria): self
    {
        return new self($criteria);
    }

    public function isEmpty(): bool
    {
        return $this->criteria === [];
    }

    public function assertNotEmptyFor(string $action): void
    {
        if (OdbcCrudAction::isDestructive($action) && $this->isEmpty()) {
            throw new \RuntimeException('OPUS_ODBC_CRUD_PREDICATE_REQUIRED: ' . $action);
        }
    }

    /** @return array<string,mixed> */
    public function criteria(): array
    {
        return $this->criteria;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->criteria;
    }
}
