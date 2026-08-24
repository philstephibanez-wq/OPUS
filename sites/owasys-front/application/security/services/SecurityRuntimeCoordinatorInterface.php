<?php
declare(strict_types=1);

/** Owns the autonomous OWASYS-front Security EFSM runtime handshake. */
interface OwasysSecurityRuntimeCoordinatorInterface
{
    /**
     * @param array<string,mixed> $identity
     * @return array{navigation_state:string,security_state:string,correlation_id:string,command_message_id:string,event_message_id:string}
     */
    public function enter(array $identity, string $applicationId): array;

    /**
     * Executes one real fresh-auth operation while Security owns the
     * reauthentication lifecycle.
     *
     * @param array<string,mixed> $identity
     * @param callable():mixed $operation
     */
    public function reauthenticate(
        array $identity,
        string $applicationId,
        callable $operation
    ): mixed;
}
