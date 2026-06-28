<?php
declare(strict_types=1);

namespace Opus\Security\Fsm;

use Opus\Api\ApiRoute;
use Opus\Fsm\Runtime\FsmRuntimeConfigLoader;
use Opus\Security\Access\AccessDecision;
use Opus\Security\Access\AccessDecisionInterface;

/**
 * Configuration-backed FSM guard.
 *
 * The first implementation validates that a declared API route references an
 * existing FSM flow and signal. Runtime state mutation remains a later engine
 * responsibility; this guard already prevents undeclared security claims.
 */
final class ConfigFsmGuard implements FsmGuardInterface
{
    private FsmRuntimeConfigLoader $loader;

    public function __construct(FsmRuntimeConfigLoader $loader)
    {
        $this->loader = $loader;
    }

    public function decide(ApiRoute $route): AccessDecisionInterface
    {
        if ($route->fsmFlow === null && $route->fsmSignal === null) {
            return AccessDecision::granted('OPUS_FSM_GUARD_NOT_DECLARED', ['route_id' => $route->id]);
        }
        if ($route->fsmFlow === null || $route->fsmSignal === null) {
            return AccessDecision::denied('OPUS_FSM_GUARD_INCOMPLETE_DECLARATION', ['route_id' => $route->id]);
        }

        $flow = $this->loader->load($route->fsmFlow);
        foreach ((array) ($flow['transitions'] ?? []) as $transition) {
            if (is_array($transition) && (string) ($transition['signal'] ?? '') === $route->fsmSignal) {
                return AccessDecision::granted('OPUS_FSM_SIGNAL_DECLARED', [
                    'route_id' => $route->id,
                    'fsm_flow' => $route->fsmFlow,
                    'fsm_signal' => $route->fsmSignal,
                ]);
            }
        }

        return AccessDecision::denied('OPUS_FSM_SIGNAL_NOT_DECLARED', [
            'route_id' => $route->id,
            'fsm_flow' => $route->fsmFlow,
            'fsm_signal' => $route->fsmSignal,
        ]);
    }
}
