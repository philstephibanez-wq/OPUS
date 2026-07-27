<?php
declare(strict_types=1);

use Opus\File\StructuredFileLoader;
use Opus\Rcp\Rest\RcpRestClient;
use Opus\Rcp\Rest\RcpRestClientInterface;
use Opus\Security\Acl\AclDecision;
use Opus\Security\Acl\AclPolicy;
use Opus\Security\Sso\Auth0ProxySsoProvider;
use Opus\Security\Sso\LocalPasswordSsoProvider;
use Opus\Security\Sso\SsoIdentity;
use Opus\Security\Sso\SsoManager;

final class OwasysRuntimeSecurity
{
    private readonly AclPolicy $acl;
    private readonly SsoManager $sso;
    private readonly RcpRestClientInterface $rcp;
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

        $providersConfig = is_array($ssoConfig['providers'] ?? null)
            ? $ssoConfig['providers']
            : [];
        $providers = [];

        foreach ($providersConfig as $providerId => $providerConfig) {
            if (!is_string($providerId)
                || !is_array($providerConfig)
                || ($providerConfig['enabled'] ?? false) !== true) {
                continue;
            }

            if ($providerId === 'local-password') {
                $storeRelative = $this->safeRelativePath(
                    (string) ($providerConfig['store'] ?? ''),
                    'OWASYS_SSO_LOCAL_STORE_PATH_INVALID'
                );
                $minimum = max(
                    8,
                    (int) ($siteConfig['auth']['minimum_password_length'] ?? 10)
                );
                $providers[] = new LocalPasswordSsoProvider(
                    $this->siteRoot . '/' . $storeRelative,
                    $minimum,
                    (string) ($providerConfig['store_contract'] ?? '')
                );
                continue;
            }

            if ($providerId === 'auth0-proxy') {
                $trusted = is_array(
                    $providerConfig['trusted_proxy_addresses'] ?? null
                )
                    ? array_values(array_filter(
                        $providerConfig['trusted_proxy_addresses'],
                        'is_string'
                    ))
                    : [];
                $providers[] = new Auth0ProxySsoProvider(
                    $trusted,
                    (string) ($providerConfig['proxy_secret_env'] ?? ''),
                    (string) ($providerConfig['subject_header']
                        ?? 'HTTP_X_OPUS_AUTH0_SUBJECT'),
                    (string) ($providerConfig['roles_header']
                        ?? 'HTTP_X_OPUS_AUTH0_ROLES'),
                    (string) ($providerConfig['label_header']
                        ?? 'HTTP_X_OPUS_AUTH0_LABEL'),
                    (string) ($providerConfig['secret_header']
                        ?? 'HTTP_X_OPUS_PROXY_SECRET')
                );
                continue;
            }

            throw new RuntimeException(
                'OWASYS_SSO_PROVIDER_NOT_IMPLEMENTED:' . $providerId
            );
        }

        if ($providers === []) {
            throw new RuntimeException('OWASYS_SSO_PROVIDER_LIST_EMPTY');
        }
        if (!isset($providersConfig[$this->defaultProvider])
            || ($providersConfig[$this->defaultProvider]['enabled'] ?? false)
                !== true) {
            throw new RuntimeException('OWASYS_SSO_DEFAULT_PROVIDER_DISABLED');
        }

        $this->sso = new SsoManager($providers);
        $this->rcp = RcpRestClient::fromConfig(
            $this->siteRoot . '/config/rcp.json'
        );
    }

    public function defaultProvider(): string
    {
        return $this->defaultProvider;
    }

    /** @param array<string,mixed> $post */
    public function authenticate(array $post): SsoIdentity
    {
        $provider = (string) (
            $post['owasys_sso_provider'] ?? $this->defaultProvider
        );
        $credentials = $provider === 'auth0-proxy'
            ? ['server' => $_SERVER]
            : [
                'username' => trim((string) (
                    $post['owasys_username'] ?? ''
                )),
                'password' => (string) ($post['owasys_password'] ?? ''),
            ];

        return $this->sso->authenticate($provider, $credentials);
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

        $result = $this->rcp->execute(
            'security.admin-password.change',
            [
                'current_password' => (string) (
                    $post['owasys_current_password'] ?? ''
                ),
                'new_password' => $newPassword,
            ],
            [
                'subject' => (string) (
                    $identity['subject'] ?? $identity['id'] ?? ''
                ),
                'roles' => is_array($identity['roles'] ?? null)
                    ? $identity['roles']
                    : [],
                'provider' => (string) (
                    $identity['provider'] ?? $this->defaultProvider
                ),
            ]
        );
        unset($newPassword, $post);

        $returned = $result['identity'] ?? null;
        if (!is_array($returned)) {
            throw new RuntimeException('OPUS_RCP_PASSWORD_IDENTITY_MISSING');
        }

        return new SsoIdentity(
            (string) ($returned['subject'] ?? $returned['id'] ?? ''),
            (string) ($returned['label'] ?? ''),
            is_array($returned['roles'] ?? null)
                ? array_values(array_filter($returned['roles'], 'is_string'))
                : [],
            (string) ($returned['provider'] ?? $this->defaultProvider),
            ($returned['must_change_password'] ?? false) === true
        );
    }

    /** @param array<string,mixed>|null $identity */
    public function assertAllowed(
        ?array $identity,
        string $resource,
        string $action = 'open'
    ): void {
        $decision = $this->decision($identity, $resource, $action);
        if (!$decision->allowed) {
            throw new RuntimeException(
                $decision->code . ':' . $decision->resource . ':' . $decision->action
            );
        }
    }

    /** @param array<string,mixed>|null $identity */
    public function isAllowed(
        ?array $identity,
        string $resource,
        string $action = 'open'
    ): bool {
        return $this->decision($identity, $resource, $action)->allowed;
    }

    /** @param array<string,mixed>|null $identity */
    private function decision(
        ?array $identity,
        string $resource,
        string $action
    ): AclDecision {
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
