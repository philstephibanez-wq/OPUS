<?php
declare(strict_types=1);

namespace Opus\Database;

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;

/** Contract for safe, driver-neutral database operation measurements. */
interface DatabaseOperationProfilerInterface extends
    OpusFrameworkComponentInterface,
    OpusExceptionAwareInterface,
    OpusProfilerAwareInterface,
    OpusSelfDocumentingInterface
{
    /**
     * @template T
     * @param callable():T $operation
     * @param array<string,mixed> $context
     * @return T
     */
    public function measure(
        string $driver,
        string $operationName,
        callable $operation,
        array $context = []
    ): mixed;
}
