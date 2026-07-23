<?php
declare(strict_types=1);

namespace Opus\Security\Sso;

/**
 * Resolves an OPUS identity asserted by a trusted Auth0 proxy or bastion.
 *
 * The provider trusts no browser header directly. The remote address and a
 * shared proxy secret are verified before subject and role headers are read.
 */
final class Auth0ProxySsoProvider implements Auth0ProxySsoProviderInterface
{
    /**
     * @param list<string> $trustedProxyAddresses
     */
    public function __construct(
        private readonly array $trustedProxyAddresses,
        private readonly string $proxySecretEnvironment,
        private readonly string $subjectHeader = 'HTTP_X_OPUS_AUTH0_SUBJECT',
        private readonly string $rolesHeader = 'HTTP_X_OPUS_AUTH0_ROLES',
        private readonly string $labelHeader = 'HTTP_X_OPUS_AUTH0_LABEL',
        private readonly string $secretHeader = 'HTTP_X_OPUS_PROXY_SECRET'
    ) {
        if ($this->trustedProxyAddresses === []
            || array_filter($this->trustedProxyAddresses, 'is_string')
                !== $this->trustedProxyAddresses) {
            throw new \InvalidArgumentException(
                'OPUS_AUTH0_PROXY_TRUSTED_ADDRESSES_INVALID'
            );
        }
        if (preg_match(
            '/^[A-Z][A-Z0-9_]{2,127}$/',
            $this->proxySecretEnvironment
        ) !== 1) {
            throw new \InvalidArgumentException(
                'OPUS_AUTH0_PROXY_SECRET_ENV_INVALID'
            );
        }
        foreach ([
            $this->subjectHeader,
            $this->rolesHeader,
            $this->labelHeader,
            $this->secretHeader,
        ] as $header) {
            if (preg_match('/^HTTP_[A-Z0-9_]{3,127}$/', $header) !== 1) {
                throw new \InvalidArgumentException(
                    'OPUS_AUTH0_PROXY_HEADER_INVALID'
                );
            }
        }
    }

    public function id(): string
    {
        return 'auth0-proxy';
    }

    public function authenticate(array $credentials): ?SsoIdentity
    {
        $server = is_array($credentials['server'] ?? null)
            ? $credentials['server']
            : [];
        $subject = trim((string) ($server[$this->subjectHeader] ?? ''));
        if ($subject === '') {
            return null;
        }

        $remote = trim((string) ($server['REMOTE_ADDR'] ?? ''));
        if (!in_array($remote, $this->trustedProxyAddresses, true)) {
            throw new \RuntimeException(
                'OPUS_AUTH0_PROXY_ADDRESS_UNTRUSTED'
            );
        }

        $expected = getenv($this->proxySecretEnvironment);
        $provided = (string) ($server[$this->secretHeader] ?? '');
        if (!is_string($expected)
            || strlen($expected) < 32
            || !hash_equals($expected, $provided)) {
            throw new \RuntimeException(
                'OPUS_AUTH0_PROXY_AUTHENTICATION_FAILED'
            );
        }

        if (preg_match(
            '/^[A-Za-z0-9][A-Za-z0-9._:@|\-]{0,191}$/',
            $subject
        ) !== 1) {
            throw new \RuntimeException(
                'OPUS_AUTH0_PROXY_SUBJECT_INVALID'
            );
        }

        $roles = array_values(array_unique(array_filter(array_map(
            'trim',
            explode(',', (string) ($server[$this->rolesHeader] ?? ''))
        ), static fn (string $role): bool =>
            preg_match('/^[A-Za-z][A-Za-z0-9._:-]{0,63}$/', $role) === 1
        )));
        if ($roles === []) {
            throw new \RuntimeException(
                'OPUS_AUTH0_PROXY_ROLES_MISSING'
            );
        }

        $label = trim((string) ($server[$this->labelHeader] ?? ''));
        if ($label === '') {
            $label = $subject;
        }

        return new SsoIdentity(
            $subject,
            $label,
            $roles,
            $this->id(),
            false
        );
    }
}
