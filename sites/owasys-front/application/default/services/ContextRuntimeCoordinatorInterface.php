<?php
declare(strict_types=1);

/** Owns runtime state and Navigation handshakes for OWASYS host context EFSMs. */
interface OwasysContextRuntimeCoordinatorInterface
{
    /** @param array<string,mixed> $identity @return array<string,mixed> */
    public function enter(
        array $identity,
        string $contextId,
        string $applicationId = ''
    ): array;

    /** @param array<string,mixed> $context */
    public function transition(
        string $contextId,
        string $signal,
        array $context = []
    ): string;
}
