<?php
declare(strict_types=1);

namespace Opus\Security\Fsm;

use Opus\Api\ApiRoute;
use Opus\Security\Access\AccessDecisionInterface;

/**
 * Contract for FSM-based runtime guards.
 */
interface FsmGuardInterface
{
    public function decide(ApiRoute $route): AccessDecisionInterface;
}
