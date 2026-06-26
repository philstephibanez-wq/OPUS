<?php
declare(strict_types=1);

namespace Opus\Framework;

/**
 * Contract marker for OPUS exception classes.
 */
interface OpusExceptionContractInterface extends
    OpusFrameworkComponentInterface,
    OpusProfilerAwareInterface,
    OpusSelfDocumentingInterface
{
}
