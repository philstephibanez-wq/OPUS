<?php
declare(strict_types=1);

namespace Opus\Lstsar\Stage;

/**
 * Secure stage contract.
 *
 * Secures loaded data through OPUS Identity, SSO, ACL policy and optional FSM guard.
 */
interface SecureStageInterface extends LstsarStageInterface
{
}
