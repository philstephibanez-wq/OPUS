<?php
declare(strict_types=1);

namespace Opus\Diagnostics;

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;

/**
 * Contract interface for Opus\Diagnostics\Diagnostics.
 *
 * @generated-by P7A1C_BIG_TOKENIZER_EXCEPTION_PROFILER_CONTRACT_ONE_RUN
 *
 * Contract:
 * - OPUS framework component contract;
 * - explicit exception-awareness contract;
 * - profiler-awareness contract;
 * - complete self-documentation contract for RefBook output.
 */
interface DiagnosticsInterface extends
    OpusFrameworkComponentInterface,
    OpusExceptionAwareInterface,
    OpusProfilerAwareInterface,
    OpusSelfDocumentingInterface
{
}
