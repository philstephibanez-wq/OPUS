<?php
declare(strict_types=1);

namespace Opus\Api\Security;

use Opus\Http\Request;

/**
 * Authenticates a secured REST_API transport and its delegated application actor.
 *
 * Environment-HMAC mode proves service identity and binds the complete request
 * body to the HTTP method, path, timestamp and nonce. Auth0 proxy mode is an
 * alternative transport adapter behind the same identity contract.
 */
final class RestRequestAuthenticator implements RestRequestAuthenticatorInterface
{
    /** @param array<string,mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function authenticate(
        Request $request,
        array $server
    ): RestIdentityInterface {
        $mode = trim((string) ($this->config['mode'] ?? ''));
        return match ($mode) {
            'environment_hmac' => $this->environmentHmac(
                $request,
                $server
            ),
            'auth0_proxy' => $this->auth0Proxy($server),
            default => throw new \RuntimeException(
                'OPUS_REST_API_AUTH_MODE_UNSUPPORTED'
            ),
        };
    }

    /** @param array<string,mixed> $server */
    private function environmentHmac(
        Request $request,
        array $server
    ): RestIdentityInterface {
        $minimum = max(
            32,
            (int) ($this->config['minimum_secret_length'] ?? 32)
        );
        $token = $this->environmentSecret('token_env', $minimum, 'TOKEN');
        $hmac = $this->environmentSecret('hmac_env', $minimum, 'HMAC');
        $authorization = trim(
            (string) ($server['HTTP_AUTHORIZATION'] ?? '')
        );

        if (!str_starts_with($authorization, 'Bearer ')
            || !hash_equals($token, substr($authorization, 7))) {
            throw new \RuntimeException('OPUS_REST_API_AUTHENTICATION_FAILED');
        }

        $timestamp = trim(
            (string) ($server['HTTP_X_OPUS_REST_TIMESTAMP'] ?? '')
        );
        $nonce = trim((string) ($server['HTTP_X_OPUS_REST_NONCE'] ?? ''));
        $provided = strtolower(trim(
            (string) ($server['HTTP_X_OPUS_REST_SIGNATURE'] ?? '')
        ));
        $actorHeader = trim((string) (
            $server['HTTP_X_OPUS_REST_ACTOR'] ?? ''
        ));
        $skew = max(
            5,
            min(
                300,
                (int) ($this->config['max_clock_skew_seconds'] ?? 60)
            )
        );

        if (preg_match('/^[0-9]{10,13}$/', $timestamp) !== 1
            || abs(time() - (int) $timestamp) > $skew
            || preg_match('/^[a-f0-9]{32,64}$/', $nonce) !== 1
            || preg_match('/^[a-f0-9]{64}$/', $provided) !== 1) {
            throw new \RuntimeException(
                'OPUS_REST_API_SIGNATURE_HEADERS_INVALID'
            );
        }

        $expected = hash_hmac(
            'sha256',
            $this->canonical(
                $request->method,
                '/' . ltrim($request->path, '/'),
                $timestamp,
                $nonce,
                $actorHeader,
                $request->body()
            ),
            $hmac
        );
        unset($token, $hmac);

        if (!hash_equals($expected, $provided)) {
            throw new \RuntimeException('OPUS_REST_API_SIGNATURE_INVALID');
        }

        $padding = (4 - strlen($actorHeader) % 4) % 4;
        $actorJson = base64_decode(
            strtr($actorHeader . str_repeat('=', $padding), '-_', '+/'),
            true
        );
        if (!is_string($actorJson)) {
            throw new \RuntimeException('OPUS_REST_API_ACTOR_HEADER_INVALID');
        }
        try {
            $actor = \Opus\File\Json::instance()->parse(
                $actorJson,
                'X-Opus-Rest-Actor'
            );
        } catch (\Throwable $error) {
            throw new \RuntimeException(
                'OPUS_REST_API_ACTOR_HEADER_INVALID',
                0,
                $error
            );
        }
        return $this->delegatedIdentity($actor);
    }

    /** @param mixed $actor */
    private function delegatedIdentity($actor): RestIdentityInterface
    {
        if (!is_array($actor)) {
            throw new \RuntimeException('OPUS_REST_API_ACTOR_INVALID');
        }

        $subject = trim((string) ($actor['subject'] ?? $actor['id'] ?? ''));
        $roles = is_array($actor['roles'] ?? null)
            ? array_values(array_unique(array_filter(
                $actor['roles'],
                'is_string'
            )))
            : [];
        $provider = trim((string) ($actor['provider'] ?? ''));
        $allowedRoles = is_array($this->config['delegated_roles'] ?? null)
            ? array_values(array_filter(
                $this->config['delegated_roles'],
                'is_string'
            ))
            : [];
        $allowedProviders = is_array(
            $this->config['delegated_providers'] ?? null
        )
            ? array_values(array_filter(
                $this->config['delegated_providers'],
                'is_string'
            ))
            : [];

        if ($subject === ''
            || preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9._:@|\-]{0,191}$/',
                $subject
            ) !== 1
            || $roles === []
            || $allowedRoles === []
            || array_diff($roles, $allowedRoles) !== []
            || $provider === ''
            || !in_array($provider, $allowedProviders, true)) {
            throw new \RuntimeException('OPUS_REST_API_ACTOR_INVALID');
        }

        return new RestIdentity(
            $subject,
            $roles,
            $provider,
            $this->serviceName()
        );
    }

    /** @param array<string,mixed> $server */
    private function auth0Proxy(array $server): RestIdentityInterface
    {
        $remote = trim((string) ($server['REMOTE_ADDR'] ?? ''));
        $trusted = is_array(
            $this->config['trusted_proxy_addresses'] ?? null
        )
            ? array_values(array_filter(
                $this->config['trusted_proxy_addresses'],
                'is_string'
            ))
            : [];

        if (!in_array($remote, $trusted, true)) {
            throw new \RuntimeException(
                'OPUS_REST_API_PROXY_ADDRESS_UNTRUSTED'
            );
        }

        $secretEnv = trim(
            (string) ($this->config['proxy_secret_env'] ?? '')
        );
        $expected = $secretEnv !== '' ? getenv($secretEnv) : false;
        $provided = (string) (
            $server['HTTP_X_OPUS_PROXY_SECRET'] ?? ''
        );

        if (!is_string($expected)
            || strlen($expected) < 32
            || !hash_equals($expected, $provided)) {
            throw new \RuntimeException(
                'OPUS_REST_API_PROXY_AUTHENTICATION_FAILED'
            );
        }

        $subject = trim((string) (
            $server['HTTP_X_OPUS_AUTH0_SUBJECT'] ?? ''
        ));
        $rawRoles = trim((string) (
            $server['HTTP_X_OPUS_AUTH0_ROLES'] ?? ''
        ));
        $roles = array_values(array_filter(array_map(
            'trim',
            explode(',', $rawRoles)
        )));

        if ($subject === '' || $roles === []) {
            throw new \RuntimeException(
                'OPUS_REST_API_AUTH0_IDENTITY_INVALID'
            );
        }

        return new RestIdentity(
            $subject,
            $roles,
            'auth0-proxy',
            $this->serviceName()
        );
    }

    private function serviceName(): string
    {
        $service = trim((string) ($this->config['service'] ?? ''));
        if ($service === ''
            || preg_match('/^[a-z][a-z0-9.-]{1,127}$/', $service) !== 1) {
            throw new \RuntimeException('OPUS_REST_API_SERVICE_INVALID');
        }
        return $service;
    }

    private function environmentSecret(
        string $configKey,
        int $minimum,
        string $type
    ): string {
        $environment = trim((string) ($this->config[$configKey] ?? ''));
        $secret = $environment !== '' ? getenv($environment) : false;
        if (!is_string($secret) || strlen($secret) < $minimum) {
            throw new \RuntimeException(
                'OPUS_REST_API_SERVER_' . $type . '_NOT_CONFIGURED'
            );
        }
        return $secret;
    }

    private function canonical(
        string $method,
        string $path,
        string $timestamp,
        string $nonce,
        string $actor,
        string $body
    ): string {
        return strtoupper($method) . "\n"
            . '/' . ltrim($path, '/') . "\n"
            . $timestamp . "\n"
            . $nonce . "\n"
            . hash('sha256', $actor) . "\n"
            . hash('sha256', $body);
    }
}
