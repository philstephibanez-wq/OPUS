<?php

declare(strict_types=1);

namespace Opus\Database;

use RuntimeException;

/*
 * OPUS_REFBOOK:
 *   domain: DATABASE
 *   role: Class DatabaseException belongs to the DATABASE Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the DATABASE domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - database-overview
 *   diagrams:
 *     - database-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC DATABASE EXCEPTION
 *
 * Role:
 *   Carry explicit database configuration and connection failures.
 *
 * Contract:
 *   No silent provider fallback.
 */
final class DatabaseException extends RuntimeException
{
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
