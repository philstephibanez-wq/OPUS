<?php
declare(strict_types=1);

namespace OpusLstsarManager\Diagnostics;

/**
 * Small profiler adapter used by the LSTSAR Manager package.
 */
final class LstsarManagerProfiler
{
    private bool $enabled;
    /** @var list<array<string,mixed>> */
    private array $events = [];

    private function __construct(bool $enabled)
    {
        $this->enabled = $enabled;
    }

    public static function disabled(): self
    {
        return new self(false);
    }

    public static function enabled(): self
    {
        return new self(true);
    }

    /**
     * @template T
     * @param array<string,mixed> $context
     * @param callable():T $callback
     * @return T
     */
    public function profile(string $action, array $context, callable $callback)
    {
        if (!$this->enabled) {
            return $callback();
        }

        $this->events[] = ['event' => 'started', 'category' => 'opus.lstsar_manager', 'action' => $action, 'context' => $this->redact($context)];
        try {
            $result = $callback();
            $this->events[] = ['event' => 'finished', 'category' => 'opus.lstsar_manager', 'action' => $action];
            return $result;
        } catch (\Throwable $e) {
            $this->events[] = ['event' => 'failed', 'category' => 'opus.lstsar_manager', 'action' => $action, 'error' => $e->getMessage()];
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> */
    public function events(): array
    {
        return $this->events;
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            $lower = strtolower((string) $key);
            if (in_array($lower, ['password', 'pass', 'secret', 'token', 'api_key', 'apikey', 'authorization', 'dsn_password', 'connection_string'], true)) {
                $context[$key] = '__redacted__';
            }
        }
        return $context;
    }
}
