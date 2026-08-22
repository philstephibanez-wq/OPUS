<?php
declare(strict_types=1);

use Opus\Fsm\FsmProcessor;

/**
 * Builds developer-programmed OWASYS EFSM guard handlers.
 *
 * Generic FsmProcessor owns no OWASYS guard vocabulary. Application guards
 * are loaded from FsmDeveloperHandlers.php; ACL guards remain dynamic because
 * their resource/action identity is defined by the canonical EFSM relation.
 */
final class OwasysFsmGuardHandlers
{
    public function __construct(
        private readonly OwasysRuntimeSecurity $security
    ) {
    }

    /**
     * @param array<string,mixed> $fsmConfig
     * @param array<string,mixed>|null $identity
     * @return list<string>
     */
    public function handlerNamesForConfig(
        array $fsmConfig,
        ?array $identity
    ): array {
        return array_keys(
            $this->forConfig($fsmConfig, $identity)
        );
    }

    /**
     * @param array<string,mixed> $fsmConfig
     * @param array<string,mixed>|null $identity
     * @return array<string,callable>
     */
    public function forConfig(
        array $fsmConfig,
        ?array $identity
    ): array {
        $managedHandlers = (
            new OwasysFsmDeveloperHandlers($this->security)
        )->guards();

        /*
         * acl:* is reserved to the runtime ACL adapter. This test concerns
         * developer-managed source only; dynamic handlers synthesized below
         * are deliberately excluded from the namespace-collision invariant.
         */
        foreach (array_keys($managedHandlers) as $managedGuard) {
            if (str_starts_with($managedGuard, 'acl:')) {
                throw new RuntimeException(
                    'OWASYS_EFSM_ACL_GUARD_NAMESPACE_RESERVED:'
                    . $managedGuard
                );
            }
        }

        $handlers = $managedHandlers;

        foreach ((array) ($fsmConfig['transitions'] ?? []) as $transition) {
            if (!is_array($transition)) {
                continue;
            }
            $guards = $transition['guards']
                ?? ($transition['guard'] ?? []);
            if (is_string($guards)) {
                $guards = [$guards];
            }
            if (!is_array($guards)) {
                continue;
            }
            foreach ($guards as $guard) {
                if (!is_string($guard)) {
                    continue;
                }
                $guard = trim($guard);
                if (!str_starts_with($guard, 'acl:')) {
                    continue;
                }

                /*
                 * Repeated transition references to the same dynamic ACL
                 * relation are normal. The first reference synthesizes the
                 * callable; every later reference reuses it idempotently.
                 */
                if (array_key_exists($guard, $handlers)) {
                    continue;
                }

                $parts = explode(':', $guard, 3);
                if (count($parts) !== 3
                    || preg_match(
                        '/^[a-z][a-z0-9._-]*$/D',
                        $parts[1]
                    ) !== 1
                    || preg_match(
                        '/^[a-z][a-z0-9._-]*$/D',
                        $parts[2]
                    ) !== 1) {
                    throw new RuntimeException(
                        'OWASYS_EFSM_ACL_GUARD_INVALID:'
                        . $guard
                    );
                }
                [, $resource, $action] = $parts;
                $handlers[$guard] = function (
                    string $currentState,
                    string $signal,
                    array $transition,
                    array $context,
                    FsmProcessor $processor
                ) use (
                    $identity,
                    $resource,
                    $action
                ): bool {
                    unset(
                        $currentState,
                        $signal,
                        $transition,
                        $context,
                        $processor
                    );
                    return $this->security->isAllowed(
                        $identity,
                        $resource,
                        $action
                    );
                };
            }
        }

        return $handlers;
    }
}
