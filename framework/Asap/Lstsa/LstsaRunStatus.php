<?php
declare(strict_types=1);

namespace ASAP\Lstsa;

final class LstsaRunStatus
{
    public const PENDING = 'PENDING';
    public const QUEUED = 'QUEUED';
    public const RUNNING = 'RUNNING';
    public const PAUSED = 'PAUSED';
    public const DONE = 'DONE';
    public const PARTIAL = 'PARTIAL';
    public const FAILED = 'FAILED';
    public const CANCELLED = 'CANCELLED';
    public const TIMEOUT_EXCEEDED = 'TIMEOUT_EXCEEDED';
    public const QUARANTINED = 'QUARANTINED';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::QUEUED,
            self::RUNNING,
            self::PAUSED,
            self::DONE,
            self::PARTIAL,
            self::FAILED,
            self::CANCELLED,
            self::TIMEOUT_EXCEEDED,
            self::QUARANTINED,
        ];
    }

    public static function assertValid(string $status): void
    {
        if (!in_array($status, self::all(), true)) {
            throw new \InvalidArgumentException('Unknown Lstsa run status: ' . $status);
        }
    }

    public static function isFinal(string $status): bool
    {
        return in_array($status, [
            self::DONE,
            self::PARTIAL,
            self::FAILED,
            self::CANCELLED,
            self::TIMEOUT_EXCEEDED,
            self::QUARANTINED,
        ], true);
    }
}
