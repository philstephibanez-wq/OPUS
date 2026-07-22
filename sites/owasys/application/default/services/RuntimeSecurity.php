<?php
declare(strict_types=1);

use Opus\File\StructuredFileLoader;
use Opus\Security\Acl\AclDecision;
use Opus\Security\Acl\AclPolicy;
use Opus\Security\Sso\LocalPasswordSsoProvider;
use Opus\Security\Sso\SsoIdentity;
use Opus\Security\Sso\SsoManager;

final class OwasysRuntimeSecurity
{
    private readonly AclPolicy $acl;
    private readonly SsoManager $sso;
    private readonly string $defaultProvider;

    /** @param array<string,mixed> $siteConfig */
    public function __construct(
        private readonly string $siteRoot,
        array $siteConfig
    ) {
        $this->acl = new AclPolicy($siteRoot . '/config/acl.json');

        $ssoConfig = StructuredFileLoader::instance()->read(
            $siteRoot . '/config/sso.json'
        );
        $this->defaultProvider = trim((string) ($ssoConfig['default_provider'] ?? ''));
        if ($this->defaultProvider === '') {
            throw new RuntimeException('OWASYS_SSO_DEFAULT_PROVIDER_MISSING');
        }

        $providerConfig = $ssoConfig['providers'][$this->defaultProvider] ?? null;
        if (!is_array($providerConfig) || ($providerConfig['enabled'] ?? false) !== true) {
            throw new RuntimeException('OWASYS_SSO_DEFAULT_PROVIDER_DISABLED');
        }

        if ($this->defaultProvider !== 'local-password') {
            throw new RuntimeException('OWASYS_SSO_PROVIDER_NOT_IMPLEMENTED:' . $this->defaultProvider);
        }

        $storeRelative = $this->safeRelativePath(
            (string) ($providerConfig['store'] ?? ''),
            'OWASYS_SSO_LOCAL_STORE_PATH_INVALID'
        );
        $minimum = max(8, (int) ($siteConfig['auth']['minimum_password_length'] ?? 10));

        $this->sso = new SsoManager([
            new LocalPasswordSsoProvider(
                $this->siteRoot . '/' . $storeRelative,
                $minimum
            ),
        ]);
    }

    public function defaultProvider(): string
    {
        return $this->defaultProvider;
    }

    /** @param array<string,mixed> $post */
    public function authenticate(array $post): SsoIdentity
    {
        return $this->sso->authenticate(
            (string) ($post['owasys_sso_provider'] ?? $this->defaultProvider),
            [
                'username' => trim((string) ($post['owasys_username'] ?? '')),
                'password' => (string) ($post['owasys_password'] ?? ''),
            ]
        );
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $post
     */
    public function changePassword(array $identity, array $post): SsoIdentity
    {
        $newPassword = (string) ($post['owasys_new_password'] ?? '');
        $confirmation = (string) ($post['owasys_confirm_password'] ?? '');

        if ($newPassword !== $confirmation) {
            throw new RuntimeException('OWASYS_PASSWORD_CONFIRMATION_MISMATCH');
        }

        return $this->sso->changePassword(
            (string) ($identity['provider'] ?? $this->defaultProvider),
            (string) ($identity['subject'] ?? $identity['id'] ?? ''),
            (string) ($post['owasys_current_password'] ?? ''),
            $newPassword
        );
    }

    /** @param array<string,mixed>|null $identity */
    public function assertAllowed(?array $identity, string $resource, string $action = 'open'): void
    {
        $decision = $this->decision($identity, $resource, $action);
        if (!$decision->allowed) {
            throw new RuntimeException(
                $decision->code . ':' . $decision->resource . ':' . $decision->action
            );
        }
    }

    /** @param array<string,mixed>|null $identity */
    public function isAllowed(?array $identity, string $resource, string $action = 'open'): bool
    {
        return $this->decision($identity, $resource, $action)->allowed;
    }

    /** @param array<string,mixed>|null $identity */
    private function decision(?array $identity, string $resource, string $action): AclDecision
    {
        $roles = is_array($identity['roles'] ?? null)
            ? array_values(array_filter($identity['roles'], 'is_string'))
            : [];

        return $this->acl->decide($roles, $resource, $action);
    }


    private function safeRelativePath(string $path, string $error): string
    {
        $normalized = trim(str_replace('\\', '/', $path), '/');
        if (
            $normalized === ''
            || str_contains($normalized, '..')
            || preg_match('/^[A-Za-z]:\//', $normalized) === 1
        ) {
            throw new RuntimeException($error);
        }

        return $normalized;
    }
}
