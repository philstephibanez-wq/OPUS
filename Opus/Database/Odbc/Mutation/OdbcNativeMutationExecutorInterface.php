<?php
declare(strict_types=1);

namespace Opus\Database\Odbc\Mutation;

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;

/**
 * Contract interface for Opus\Database\Odbc\Mutation\OdbcNativeMutationExecutor.
 *
 * @generated-by P117L_OPUS_DATABASE_NAMESPACE_CONTRACT
 *
 * Contract:
 * - OPUS framework component contract;
 * - explicit exception-awareness contract;
 * - profiler-awareness contract;
 * - complete self-documentation contract for RefBook output.
 */
interface OdbcNativeMutationExecutorInterface extends
    OdbcMutationExecutorInterface,
OpusFrameworkComponentInterface,
OpusExceptionAwareInterface,
OpusProfilerAwareInterface,
OpusSelfDocumentingInterface
{
}
