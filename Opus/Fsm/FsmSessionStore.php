<?php
declare(strict_types=1);

namespace Opus\Fsm;

use InvalidArgumentException;
use RuntimeException;

/**
 * Explicit HTTP-session persistence adapter for one FSM runtime snapshot.
 */
final class FsmSessionStore implements FsmSessionStoreInterface
{
    public function __construct(private readonly string $key)
    {
        if (preg_match('/^[A-Za-z0-9_.:-]+$/D', $key) !== 1) {
            throw new InvalidArgumentException('OPUS_FSM_SESSION_KEY_INVALID');
        }
    }

    public function restore(FsmProcessor $processor): void
    {
        $this->assertSessionStarted();
        if (!array_key_exists($this->key, $_SESSION)) {
            return;
        }
        if (!is_array($_SESSION[$this->key])) {
            throw new RuntimeException('OPUS_FSM_SESSION_SNAPSHOT_INVALID');
        }
        $processor->restore($_SESSION[$this->key]);
    }

    public function persist(FsmProcessor $processor): void
    {
        $this->assertSessionStarted();
        $_SESSION[$this->key] = $processor->snapshot();
    }

    public function clear(): void
    {
        $this->assertSessionStarted();
        unset($_SESSION[$this->key]);
    }

    private function assertSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('OPUS_FSM_SESSION_NOT_STARTED');
        }
    }
}
