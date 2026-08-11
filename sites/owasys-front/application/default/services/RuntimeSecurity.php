<?php
declare(strict_types=1);

use Opus\File\Json;
use Opus\File\StructuredFileLoader;
use Opus\Api\Rest\RestClient;
use Opus\Api\Rest\RestClientInterface;
use Opus\Security\Acl\AclDecision;
use Opus\Security\Acl\AclPolicy;
use Opus\Security\Sso\Auth0ProxySsoProvider;
use Opus\Security\Sso\LocalPasswordSsoProvider;
use Opus\Security\Sso\SsoIdentity;
use Opus\Security\Sso\SsoManager;
use Opus\Profiler\ProfilerInterface;

final class OwasysRuntimeSecurity
{
    private readonly AclPolicy $acl;
    private readonly SsoManager $sso;
    private readonly RestClientInterface $rest;
    private readonly string $defaultProvider;

    /** @param array<string,mixed> $siteConfig */
    public function __construct(
        private readonly string $siteRoot,
        array $siteConfig,
        ?ProfilerInterface $profiler = null,
        ?string $parentSpanId = null
    ) {
        $this->acl = new AclPolicy(
            $siteRoot . '/config/acl.json',
            null,
            $profiler,
            $parentSpanId
        );

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
        $this->rest = RestClient::fromConfig(
            $this->siteRoot . '/config/rest-api.json'
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
     * Development-only local-password credential ownership remains on the
     * frontend bastion that owns the non-versioned runtime credential store.
     *
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $post
     */
    public function changePassword(array $identity, array $post): SsoIdentity
    {
        $provider = trim((string) (
            $identity['provider'] ?? $this->defaultProvider
        ));
        $subject = trim((string) (
            $identity['subject'] ?? $identity['id'] ?? ''
        ));
        $currentPassword = (string) (
            $post['owasys_current_password'] ?? ''
        );
        $newPassword = (string) ($post['owasys_new_password'] ?? '');
        $confirmation = (string) ($post['owasys_confirm_password'] ?? '');

        if ($provider !== 'local-password') {
            throw new RuntimeException('OWASYS_PASSWORD_CHANGE_PROVIDER_UNSUPPORTED');
        }
        if ($subject === '' || $currentPassword === '' || $newPassword === '') {
            throw new RuntimeException('OWASYS_PASSWORD_CHANGE_CREDENTIALS_REQUIRED');
        }
        if ($newPassword !== $confirmation) {
            throw new RuntimeException('OWASYS_PASSWORD_CONFIRMATION_MISMATCH');
        }

        try {
            return $this->sso->changePassword(
                'local-password',
                $subject,
                $currentPassword,
                $newPassword
            );
        } finally {
            $currentPassword = '';
            $newPassword = '';
            $confirmation = '';
            unset($post);
        }
    }

    /**
     * @param array<string,mixed> $identity
     * @return array<string,mixed>
     */
    public function securitySnapshot(array $identity, string $siteId): array
    {
        $siteId = strtolower(trim($siteId));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $siteId) !== 1) {
            throw new RuntimeException('OWASYS_SECURITY_SITE_ID_INVALID');
        }

        $result = $this->rest->request(
            'GET',
            '/api/v1/applications/'
                . rawurlencode($siteId)
                . '/security',
            [],
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

        if (($result['contract'] ?? null) !== 'OWASYS_SECURITY_SNAPSHOT_V1') {
            throw new RuntimeException(
                'OWASYS_SECURITY_SNAPSHOT_CONTRACT_INVALID'
            );
        }
        if ((string) ($result['application']['id'] ?? '') !== $siteId) {
            throw new RuntimeException(
                'OWASYS_SECURITY_SNAPSHOT_APPLICATION_MISMATCH'
            );
        }

        return $result;
    }

    /** @param array<string,mixed> $identity */
    public function reauthenticate(
        array $identity,
        string $password,
        string $siteId,
        array $mutation,
        string $phase
    ): string {
        $provider = trim((string) (
            $identity['provider'] ?? $this->defaultProvider
        ));
        $subject = trim((string) (
            $identity['subject'] ?? $identity['id'] ?? ''
        ));
        if ($provider !== 'local-password') {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_REAUTH_PROVIDER_UNSUPPORTED'
            );
        }
        if ($subject === '' || $password === '') {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_REAUTH_REQUIRED'
            );
        }
        try {
            $candidate = $this->sso->authenticate(
                'local-password',
                [
                    'username' => $subject,
                    'password' => $password,
                ]
            );
        } finally {
            unset($password);
        }
        if ($candidate->subject !== $subject) {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_REAUTH_IDENTITY_MISMATCH'
            );
        }

        $siteId = $this->securitySiteId($siteId);
        $mutationJson = Json::instance()->encode($mutation, false);
        $result = $this->rest->request(
            'POST',
            '/api/v1/applications/'
                . rawurlencode($siteId)
                . '/security/fresh-auth-proofs',
            [
                'mutation_json' => $mutationJson,
                'phase' => $phase,
            ],
            $this->restActor($identity)
        );
        if (($result['contract'] ?? null)
                !== 'OWASYS_FRESH_AUTH_PROOF_V1'
            || !is_string($result['proof'] ?? null)
            || trim((string) $result['proof']) === '') {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_FRESH_AUTH_PROOF_INVALID'
            );
        }

        return (string) $result['proof'];
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $mutation
     * @return array<string,mixed>
     */
    public function previewSecurityMutation(
        array $identity,
        string $siteId,
        array $mutation,
        string $reason,
        string $freshAuthProof
    ): array {
        $siteId = $this->securitySiteId($siteId);
        $result = $this->rest->request(
            'POST',
            '/api/v1/applications/'
                . rawurlencode($siteId)
                . '/security/previews',
            [
                'mutation_json' => Json::instance()->encode(
                    $mutation,
                    false
                ),
                'reason' => $reason,
                'fresh_auth_proof' => $freshAuthProof,
            ],
            $this->restActor($identity)
        );
        if (($result['contract'] ?? null)
            !== 'OWASYS_SECURITY_MUTATION_PREVIEW_V1') {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_PREVIEW_CONTRACT_INVALID'
            );
        }
        if ((string) ($result['application']['id'] ?? '') !== $siteId) {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_APPLICATION_MISMATCH'
            );
        }
        return $result;
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $mutation
     * @return array<string,mixed>
     */
    public function commitSecurityMutation(
        array $identity,
        string $siteId,
        array $mutation,
        string $reason,
        string $freshAuthProof,
        string $expectedStateHash,
        string $confirmationToken
    ): array {
        $siteId = $this->securitySiteId($siteId);
        $result = $this->rest->request(
            'PATCH',
            '/api/v1/applications/' . rawurlencode($siteId) . '/security',
            [
                'mutation_json' => Json::instance()->encode(
                    $mutation,
                    false
                ),
                'reason' => $reason,
                'fresh_auth_proof' => $freshAuthProof,
                'expected_state_hash' => $expectedStateHash,
                'confirmation_token' => $confirmationToken,
            ],
            $this->restActor($identity)
        );
        if (($result['contract'] ?? null)
            !== 'OWASYS_SECURITY_MUTATION_COMMIT_V1') {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_COMMIT_CONTRACT_INVALID'
            );
        }
        if ((string) ($result['application']['id'] ?? '') !== $siteId) {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_APPLICATION_MISMATCH'
            );
        }
        return $result;
    }

    /** @param array<string,mixed> $identity @return array<string,mixed> */
    public function startDevelopmentServer(
        array $identity,
        string $siteId
    ): array {
        $siteId = strtolower(trim($siteId));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $siteId) !== 1) {
            throw new RuntimeException('OWASYS_DEV_PREVIEW_SITE_ID_INVALID');
        }

        $result = $this->rest->request(
            'POST',
            '/api/v1/applications/'
                . rawurlencode($siteId)
                . '/development-server',
            [],
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

        $url = trim((string) ($result['url'] ?? ''));
        $parts = parse_url($url);
        if (($result['contract'] ?? null)
                !== 'OPUS_CONSOLE_DEV_SERVER_START_RESULT_V1'
            || ($result['started'] ?? false) !== true
            || ($result['background'] ?? false) !== true
            || (string) ($result['application_id'] ?? '') !== $siteId
            || !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'http'
            || !in_array(
                strtolower((string) ($parts['host'] ?? '')),
                ['127.0.0.1', 'localhost', '::1'],
                true
            )) {
            throw new RuntimeException('OWASYS_DEV_PREVIEW_RESULT_INVALID');
        }
        return $result;
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

    /** @param array<string,mixed> $identity @return array<string,mixed> */
    private function restActor(array $identity): array
    {
        return [
            'subject' => (string) (
                $identity['subject'] ?? $identity['id'] ?? ''
            ),
            'roles' => is_array($identity['roles'] ?? null)
                ? $identity['roles']
                : [],
            'provider' => (string) (
                $identity['provider'] ?? $this->defaultProvider
            ),
        ];
    }

    private function securitySiteId(string $siteId): string
    {
        $siteId = strtolower(trim($siteId));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $siteId) !== 1) {
            throw new RuntimeException('OWASYS_SECURITY_SITE_ID_INVALID');
        }
        return $siteId;
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
