<?php

declare(strict_types=1);

namespace Opus\Lstsa;

/*
 * OPUS_REFBOOK:
 *   domain: LSTSA
 *   role: Class LstsaFsmState belongs to the LSTSA Opus framework domain.
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
/**
 * PUBLIC LSTSAR FSM STATE REGISTRY
 *
 * @visibility public
 * @role Defines the stable states used by the Lstsa background runner FSM.
 * @contract States are orchestration states only. They do not contain business
 *           data and must never replace the run payload, checkpoints, archives,
 *           or reports persisted by LstsaRunStore.
 * @sideEffects None.
 */
final class LstsaFsmState
{
    public const ACQUIRED = 'ACQUIRED';
    public const LOAD_REQUIRED = 'LOAD_REQUIRED';
    public const SECURE_INPUT_REQUIRED = 'SECURE_INPUT_REQUIRED';
    public const TRANSFORM_REQUIRED = 'TRANSFORM_REQUIRED';
    public const SECURE_OUTPUT_REQUIRED = 'SECURE_OUTPUT_REQUIRED';
    public const STORE_REQUIRED = 'STORE_REQUIRED';
    public const ARCHIVE_REQUIRED = 'ARCHIVE_REQUIRED';
    public const REPORT_REQUIRED = 'REPORT_REQUIRED';
    public const DONE = 'DONE';
    public const FAILED = 'FAILED';

    /**
     * PUBLIC API
     *
     * @return list<string> Ordered declared states.
     */
    public static function all(): array
    {
        return [
            self::ACQUIRED,
            self::LOAD_REQUIRED,
            self::SECURE_INPUT_REQUIRED,
            self::TRANSFORM_REQUIRED,
            self::SECURE_OUTPUT_REQUIRED,
            self::STORE_REQUIRED,
            self::ARCHIVE_REQUIRED,
            self::REPORT_REQUIRED,
            self::DONE,
            self::FAILED,
        ];
    }
}
