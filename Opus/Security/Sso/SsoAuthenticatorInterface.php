<?php
declare(strict_types=1);

namespace Opus\Security\Sso;

use Opus\Http\Request;
use Opus\Security\Identity\IdentityContextInterface;

/**
 * Contract implemented by OPUS SSO adapters.
 */
interface SsoAuthenticatorInterface
{
    public function authenticate(Request $request): IdentityContextInterface;
}
