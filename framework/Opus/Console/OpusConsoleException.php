<?php
declare(strict_types=1);

namespace Opus\Console;

use RuntimeException;

/**
 * Exception thrown by the OPUS console layer.
 *
 * Contract:
 * - explicit failure only;
 * - no silent fallback;
 * - user-facing messages must be actionable.
 */
final class OpusConsoleException extends RuntimeException
{
}
