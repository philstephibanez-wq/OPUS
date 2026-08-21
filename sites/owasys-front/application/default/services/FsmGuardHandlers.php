<?php
declare(strict_types=1);

use Opus\Fsm\FsmProcessor;

/**
 * Builds developer-programmed OWASYS EFSM guard handlers.
 *
 * Generic FsmProcessor owns no OWASYS guard vocabulary. Every named guard
 * referenced by the application FSM is backed here by real application PHP.
 */
final class OwasysFsmGuardHandlers
{
    public function __construct(private readonly OwasysRuntimeSecurity $security)
    {
    }

    /**
     * @param array<string,mixed> $fsmConfig
     * @param array<string,mixed>|null $identity
     * @return array<string,callable>
     */
    public function forConfig(array $fsmConfig, ?array $identity): array
    {
        $handlers = $this->applicationHandlers();

        foreach ((array) ($fsmConfig['transitions'] ?? []) as $transition) {
            if (!is_array($transition)) {
                continue;
            }
            $guards = $transition['guards'] ?? ($transition['guard'] ?? []);
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
                if (isset($handlers[$guard])) {
                    continue;
                }
                $parts = explode(':', $guard, 3);
                if (count($parts) !== 3
                    || preg_match('/^[a-z][a-z0-9._-]*$/D', $parts[1]) !== 1
                    || preg_match('/^[a-z][a-z0-9._-]*$/D', $parts[2]) !== 1) {
                    throw new RuntimeException(
                        'OWASYS_EFSM_ACL_GUARD_INVALID:' . $guard
                    );
                }
                [, $resource, $action] = $parts;
                $handlers[$guard] = function (
                    string $currentState,
                    string $signal,
                    array $transition,
                    array $context,
                    FsmProcessor $processor
                ) use ($identity, $resource, $action): bool {
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

    /** @return array<string,callable> */
    private function applicationHandlers(): array
    {
        return [
            'always' => static function (
                string $currentState,
                string $signal,
                array $transition,
                array $context,
                FsmProcessor $processor
            ): bool {
                unset($currentState, $signal, $transition, $context, $processor);
                return true;
            },
            'route_exists' => static function (
                string $currentState,
                string $signal,
                array $transition,
                array $context,
                FsmProcessor $processor
            ): bool {
                unset($currentState, $signal, $context);
                $target = (string) ($transition['next_state'] ?? '');
                if ($target === '' || !$processor->hasState($target)) {
                    return false;
                }
                return (string) ($processor->state($target)['route'] ?? '') !== '';
            },
            'app_exists' => static function (
                string $currentState,
                string $signal,
                array $transition,
                array $context,
                FsmProcessor $processor
            ): bool {
                unset($currentState, $signal, $transition, $processor);
                return ($context['app_exists'] ?? null) === true
                    || is_array($context['registry_entry'] ?? null)
                    || is_array($context['selected_app'] ?? null)
                    || (string) ($context['selected_app'] ?? '') !== '';
            },
            'current_app_required' => static function (
                string $currentState,
                string $signal,
                array $transition,
                array $context,
                FsmProcessor $processor
            ): bool {
                unset($currentState, $signal, $transition, $processor);
                $currentApp = $context['current_app'] ?? null;
                return ($context['has_current_app'] ?? null) === true
                    || (is_array($currentApp) && $currentApp !== [])
                    || (is_string($currentApp) && $currentApp !== '');
            },
            'current_app_or_creation_request' => static function (
                string $currentState,
                string $signal,
                array $transition,
                array $context,
                FsmProcessor $processor
            ): bool {
                unset($currentState, $signal, $transition, $processor);
                $currentApp = $context['current_app'] ?? null;
                $hasCurrentApp = ($context['has_current_app'] ?? null) === true
                    || (is_array($currentApp) && $currentApp !== [])
                    || (is_string($currentApp) && $currentApp !== '');
                return $hasCurrentApp
                    || is_array($context['creation_request'] ?? null)
                    || ($context['creation_request_started'] ?? null) === true;
            },
            'must_change_password' => static function (
                string $currentState,
                string $signal,
                array $transition,
                array $context,
                FsmProcessor $processor
            ): bool {
                unset($currentState, $signal, $transition, $processor);
                return ($context['must_change_password'] ?? null) === true;
            },
        ];
    }
}