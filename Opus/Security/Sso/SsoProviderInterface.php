<?php
declare(strict_types=1);

namespace Opus\Security\Sso;

interface SsoProviderInterface
{
    public function id(): string;

    /** @param array<string,mixed> $credentials */
    public function authenticate(array $credentials): ?SsoIdentity;
}
