<?php
declare(strict_types=1);

namespace Opus\Security\Sso;

use RuntimeException;

final class SsoManager
{
    /** @var array<string,SsoProviderInterface> */
    private array $providers = [];

    /** @param iterable<SsoProviderInterface> $providers */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->id()] = $provider;
        }
    }

    /** @param array<string,mixed> $credentials */
    public function authenticate(string $providerId, array $credentials): SsoIdentity
    {
        $provider = $this->provider($providerId);
        $identity = $provider->authenticate($credentials);

        if (!$identity instanceof SsoIdentity) {
            throw new RuntimeException('OPUS_SSO_AUTHENTICATION_FAILED');
        }

        return $identity;
    }

    public function changePassword(
        string $providerId,
        string $subject,
        string $currentPassword,
        string $newPassword
    ): SsoIdentity {
        $provider = $this->provider($providerId);
        if (!$provider instanceof PasswordChangeProviderInterface) {
            throw new RuntimeException('OPUS_SSO_PASSWORD_CHANGE_UNSUPPORTED:' . $providerId);
        }

        return $provider->changePassword($subject, $currentPassword, $newPassword);
    }

    private function provider(string $providerId): SsoProviderInterface
    {
        $provider = $this->providers[$providerId] ?? null;
        if (!$provider instanceof SsoProviderInterface) {
            throw new RuntimeException('OPUS_SSO_PROVIDER_UNKNOWN:' . $providerId);
        }

        return $provider;
    }
}
