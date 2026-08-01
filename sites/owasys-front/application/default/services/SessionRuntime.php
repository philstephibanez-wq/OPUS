<?php
declare(strict_types=1);

/** Opens the single configured OWASYS frontend session for every request path. */
final class OwasysSessionRuntime implements OwasysSessionRuntimeInterface
{
    public const CONTRACT = 'OWASYS_FRONT_SESSION_RUNTIME_V1';

    /** @param array<string,mixed> $siteConfig */
    public function __construct(private readonly array $siteConfig)
    {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->assertActiveSessionName();
            return;
        }
        if (session_status() !== PHP_SESSION_NONE) {
            throw new RuntimeException('OWASYS_SESSION_STATE_INVALID');
        }

        $name = $this->configuredName();
        session_name($name);
        if (!session_start()) {
            throw new RuntimeException('OWASYS_SESSION_START_FAILED');
        }
        $this->assertActiveSessionName();
    }

    private function assertActiveSessionName(): void
    {
        if (session_name() !== $this->configuredName()) {
            throw new RuntimeException('OWASYS_SESSION_CONTEXT_MISMATCH');
        }
    }

    private function configuredName(): string
    {
        $auth = is_array($this->siteConfig['auth'] ?? null)
            ? $this->siteConfig['auth']
            : [];
        $name = trim((string) ($auth['session_name'] ?? 'OWASYS_LOCAL_SESSION'));
        if (preg_match('/^[A-Za-z0-9_-]+$/D', $name) !== 1) {
            throw new RuntimeException('OWASYS_SESSION_NAME_INVALID');
        }
        return $name;
    }
}
