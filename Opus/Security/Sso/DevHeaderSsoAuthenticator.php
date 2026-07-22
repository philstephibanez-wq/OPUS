<?php
declare(strict_types=1);

namespace Opus\Security\Sso;

use Opus\Http\Request;
use Opus\Security\Identity\IdentityContext;
use Opus\Security\Identity\IdentityContextInterface;

/**
 * Development SSO adapter based on explicit HTTP headers.
 *
 * This adapter is configuration-driven and exists to exercise the OPUS security
 * contract before wiring OIDC/SAML providers. It does not pretend to be a
 * production identity provider.
 */
final class DevHeaderSsoAuthenticator implements SsoAuthenticatorInterface, DevHeaderSsoAuthenticatorInterface
{
    private string $userHeader;
    private string $rolesHeader;
    private string $scopesHeader;
    private string $anonymousSubject;

    /** @param array<string,mixed> $config */
    private function __construct(array $config)
    {
        if (($config['contract'] ?? '') !== 'OPUS_SSO_CONFIG_V1') {
            throw new \RuntimeException('OPUS_SSO_CONFIG_CONTRACT_INVALID');
        }
        if (($config['adapter'] ?? '') !== 'dev_header') {
            throw new \RuntimeException('OPUS_SSO_ADAPTER_UNSUPPORTED: ' . (string) ($config['adapter'] ?? ''));
        }

        $headers = (array) ($config['headers'] ?? []);
        $this->userHeader = strtoupper(str_replace('-', '_', (string) ($headers['subject'] ?? 'X-OPUS-USER')));
        $this->rolesHeader = strtoupper(str_replace('-', '_', (string) ($headers['roles'] ?? 'X-OPUS-ROLES')));
        $this->scopesHeader = strtoupper(str_replace('-', '_', (string) ($headers['scopes'] ?? 'X-OPUS-SCOPES')));
        $this->anonymousSubject = (string) ($config['anonymous_subject'] ?? 'anonymous');
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException('OPUS_SSO_CONFIG_MISSING: ' . $path);
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('OPUS_SSO_CONFIG_JSON_INVALID: ' . $path);
        }

        return new self($decoded);
    }

    public function authenticate(Request $request): IdentityContextInterface
    {
        $subject = $this->serverHeader($this->userHeader);
        if ($subject === '') {
            return IdentityContext::anonymous($this->anonymousSubject);
        }

        $roles = $this->csv($this->serverHeader($this->rolesHeader));
        $scopes = $this->csv($this->serverHeader($this->scopesHeader));

        return new IdentityContext($subject, $roles, $scopes, [
            'auth_method' => 'dev_header',
            'host' => $request->host,
        ], false);
    }

    private function serverHeader(string $name): string
    {
        $key = 'HTTP_' . $name;
        return trim((string) ($_SERVER[$key] ?? ''));
    }

    /** @return list<string> */
    private function csv(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $item): bool => $item !== ''));
    }
}
