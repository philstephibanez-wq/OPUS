<?php
declare(strict_types=1);

namespace Opus\Database\Odbc\Mutation;

final class OdbcMutationCapabilities
    implements OdbcMutationCapabilitiesInterface
{
    public function __construct(
        private bool $insert,
        private bool $update,
        private bool $delete
    ) {
    }

    public static function all(): self
    {
        return new self(true, true, true);
    }

    public static function readOnly(): self
    {
        return new self(false, false, false);
    }

    public function allows(string $action): bool
    {
        return match (OdbcMutationAction::assert($action)) {
            OdbcMutationAction::INSERT => $this->insert,
            OdbcMutationAction::UPDATE => $this->update,
            OdbcMutationAction::DELETE => $this->delete,
        };
    }

    /**
     * @return array<string,bool>
     */
    public function toArray(): array
    {
        return [
            OdbcMutationAction::INSERT => $this->insert,
            OdbcMutationAction::UPDATE => $this->update,
            OdbcMutationAction::DELETE => $this->delete,
        ];
    }
}
