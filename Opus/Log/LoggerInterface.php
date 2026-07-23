<?php
declare(strict_types=1);

namespace Opus\Log;

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;

/** Contract for the structured OPUS logger. */
interface LoggerInterface extends
    OpusFrameworkComponentInterface,
    OpusExceptionAwareInterface,
    OpusProfilerAwareInterface,
    OpusSelfDocumentingInterface
{
    /** @param array<string,mixed> $context */
    public function debug(
        string $channel,
        string $message,
        array $context = [],
        ?string $traceId = null
    ): void;

    /** @param array<string,mixed> $context */
    public function info(
        string $channel,
        string $message,
        array $context = [],
        ?string $traceId = null
    ): void;

    /** @param array<string,mixed> $context */
    public function warning(
        string $channel,
        string $message,
        array $context = [],
        ?string $traceId = null
    ): void;

    /** @param array<string,mixed> $context */
    public function error(
        string $channel,
        string $message,
        array $context = [],
        ?string $traceId = null
    ): void;

    /** @param array<string,mixed> $context */
    public function critical(
        string $channel,
        string $message,
        array $context = [],
        ?string $traceId = null
    ): void;
}
