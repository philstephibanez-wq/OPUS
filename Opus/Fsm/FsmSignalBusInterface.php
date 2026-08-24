<?php
declare(strict_types=1);

namespace Opus\Fsm;

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;

/** Contract for bounded in-process inter-EFSM signal delivery. */
interface FsmSignalBusInterface extends
    OpusFrameworkComponentInterface,
    OpusExceptionAwareInterface,
    OpusProfilerAwareInterface,
    OpusSelfDocumentingInterface
{
    /** Receiver signature: function (array $message): array */
    public function register(string $fsmId, callable $receiver): void;

    /** @param array<string,mixed> $context @return array<string,mixed> */
    public function command(
        string $sourceFsm,
        string $targetFsm,
        string $signal,
        array $context = [],
        ?string $correlationId = null,
        ?string $causationId = null,
        int $ttl = 4
    ): array;

    /** @param array<string,mixed> $context @return array<string,mixed> */
    public function event(
        string $sourceFsm,
        string $targetFsm,
        string $signal,
        array $context = [],
        ?string $correlationId = null,
        ?string $causationId = null,
        int $ttl = 4
    ): array;
}
