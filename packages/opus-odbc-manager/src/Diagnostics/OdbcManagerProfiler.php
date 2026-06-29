<?php
declare(strict_types=1);

namespace OpusOdbcManager\Diagnostics;

/**
 * Profiler adapter for the OPUS ODBC Manager site package.
 *
 * The site package emits explicit action events when an OPUS profiler instance
 * is injected by the runtime. It stays disabled by default so the package can
 * be smoke-tested without starting a global trace manually.
 */
final class OdbcManagerProfiler
{
    public const CATEGORY = 'opus.odbc_manager';
    public const PACKAGE = 'logandplay/opus-odbc-manager';
    public const MODE = 'readonly';

    private ?object $profiler;

    private function __construct(?object $profiler)
    {
        $this->profiler = $profiler;
    }

    public static function disabled(): self
    {
        return new self(null);
    }

    public static function fromProfiler(?object $profiler): self
    {
        if ($profiler !== null && !method_exists($profiler, 'event')) {
            throw new \InvalidArgumentException('OPUS_ODBC_MANAGER_PROFILER_EVENT_METHOD_MISSING');
        }

        return new self($profiler);
    }

    public function isEnabled(): bool
    {
        return $this->profiler !== null;
    }

    /**
     * @template T
     * @param array<string,mixed> $context
     * @param callable():T $callback
     * @return T
     */
    public function profile(string $action, array $context, callable $callback): mixed
    {
        $context = $this->context($action, $context);
        $this->event('action.started', $context);

        try {
            $result = $callback();
            $this->event('action.finished', $context + ['status' => 'ok']);
            return $result;
        } catch (\Throwable $exception) {
            $this->event('action.failed', $context + [
                'status' => 'error',
                'exception_class' => $exception::class,
            ]);
            throw $exception;
        }
    }

    /** @param array<string,mixed> $context */
    public function event(string $name, array $context = []): void
    {
        if ($this->profiler === null) {
            return;
        }

        $this->profiler->event(self::CATEGORY, $name, $this->redact($context));
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function context(string $action, array $context): array
    {
        return $context + [
            'action' => $action,
            'package' => self::PACKAGE,
            'mode' => self::MODE,
        ];
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function redact(array $context): array
    {
        $redacted = [];
        foreach ($context as $key => $value) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, ['password', 'pass', 'secret', 'token', 'api_key', 'apikey', 'authorization'], true)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }
            $redacted[$key] = $value;
        }

        return $redacted;
    }
}
