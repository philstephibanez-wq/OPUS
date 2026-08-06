<?php
declare(strict_types=1);

namespace Opus\Security\Csrf;

/** Session-bound, scoped and single-use CSRF token manager for OPUS forms. */
final class CsrfTokenManager implements CsrfTokenManagerInterface
{
    public const CONTRACT = 'OPUS_CSRF_TOKEN_MANAGER_V1';
    private const SESSION_KEY = 'opus.security.csrf.v1';

    public function issue(string $scope): string
    {
        $scope = $this->scope($scope);
        $store = $this->store();
        $token = $store[$scope] ?? null;
        if (!is_string($token)
            || preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $store[$scope] = $token;
            $_SESSION[self::SESSION_KEY] = $store;
        }
        return $token;
    }

    public function assertValid(string $scope, string $token): void
    {
        $scope = $this->scope($scope);
        $token = strtolower(trim($token));
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            throw new \RuntimeException('OPUS_CSRF_TOKEN_INVALID');
        }
        $store = $this->store();
        $expected = $store[$scope] ?? null;
        if (!is_string($expected) || !hash_equals($expected, $token)) {
            throw new \RuntimeException('OPUS_CSRF_TOKEN_INVALID');
        }
        unset($store[$scope]);
        $_SESSION[self::SESSION_KEY] = $store;
    }

    private function scope(string $scope): string
    {
        $scope = strtolower(trim($scope));
        if (preg_match('/^[a-z][a-z0-9._-]{2,127}$/D', $scope) !== 1) {
            throw new \RuntimeException('OPUS_CSRF_SCOPE_INVALID');
        }
        return $scope;
    }

    /** @return array<string,string> */
    private function store(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('OPUS_CSRF_SESSION_REQUIRED');
        }
        $store = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($store)
            || array_filter($store, 'is_string') !== $store) {
            throw new \RuntimeException('OPUS_CSRF_SESSION_INVALID');
        }
        return $store;
    }
}
