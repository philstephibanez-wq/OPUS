<?php
declare(strict_types=1);

namespace Opus\Lstsa;
/*
 * OPUS_REFBOOK:
 *   domain: LSTSA
 *   role: Class LstsaRunStatus belongs to the LSTSA Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the LSTSA domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - lstsa-overview
 *   diagrams:
 *     - lstsa-runtime
 * END_OPUS_REFBOOK
 */

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
