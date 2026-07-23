<?php
declare(strict_types=1);

namespace Opus\Rcp\Rest;

interface RcpExecutionStoreInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function exists(string $executionId): bool;
    /** @param array<string,mixed> $record */
    public function write(string $executionId, array $record): void;
    /** @return array<string,mixed> */
    public function read(string $executionId): array;
}
