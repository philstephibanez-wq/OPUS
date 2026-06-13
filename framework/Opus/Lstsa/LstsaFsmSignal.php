<?php

declare(strict_types=1);

namespace Opus\Lstsa;

/*
 * OPUS_REFBOOK:
 *   domain: LSTSA
 *   role: Class LstsaFsmSignal belongs to the LSTSA Opus framework domain.
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
 * PUBLIC LSTSAR FSM SIGNAL REGISTRY
 *
 * @visibility public
 * @role Defines the stable signals accepted by the Lstsa background runner FSM.
 * @contract Signals are explicit transition instructions. There is no implicit
 *           next step and no fallback transition when one signal is missing.
 * @sideEffects None.
 */
final class LstsaFsmSignal
{
    public const START = 'START';
    public const LOAD_OK = 'LOAD_OK';
    public const SECURE_INPUT_OK = 'SECURE_INPUT_OK';
    public const TRANSFORM_OK = 'TRANSFORM_OK';
    public const SECURE_OUTPUT_OK = 'SECURE_OUTPUT_OK';
    public const STORE_OK = 'STORE_OK';
    public const ARCHIVE_OK = 'ARCHIVE_OK';
    public const REPORT_OK = 'REPORT_OK';
    public const FAIL = 'FAIL';
}
