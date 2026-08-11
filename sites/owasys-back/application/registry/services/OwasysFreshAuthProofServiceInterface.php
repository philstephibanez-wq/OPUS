<?php
declare(strict_types=1);

interface OwasysFreshAuthProofServiceInterface
{
    /** @param array<string,mixed> $actor */
    public function issue(
        array $actor,
        string $siteId,
        string $mutationJson,
        string $phase
    ): array;

    /** @param array<string,mixed> $actor */
    public function assertValid(
        string $proof,
        array $actor,
        string $siteId,
        string $mutationJson,
        string $phase
    ): void;
}
