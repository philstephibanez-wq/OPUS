<?php

declare(strict_types=1);

namespace Opus\Lstsa;

/*
 * OPUS_REFBOOK:
 *   domain: LSTSA
 *   role: Interface LstsaPhaseInterface belongs to the LSTSA Opus framework domain.
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
 * PUBLIC LSTSAR PHASE CONTRACT
 *
 * @visibility public
 * @role Common contract for one explicit background pipeline phase.
 * @contract A phase does one job only and must fail explicitly. It must not
 *           decide the next FSM transition and must not execute HTTP work.
 * @sideEffects Phase-specific, documented by each implementation.
 */
interface LstsaPhaseInterface
{
    /**
     * PUBLIC API
     *
     * @param LstsaPipelineContext $context Current background run context.
     */
    public function execute(LstsaPipelineContext $context): void;
}
