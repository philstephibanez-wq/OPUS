<?php

declare(strict_types=1);

namespace Opus\Lstsa;

/*
 * OPUS_REFBOOK:
 *   domain: LSTSA
 *   role: Class LstsaReportPhase belongs to the LSTSA Opus framework domain.
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
 * PUBLIC LSTSAR REPORT PHASE
 *
 * @visibility public
 * @role Marks the report phase as reached before LstsaRunStore writes the final
 *       JSON and Markdown reports during finish().
 * @contract Report is mandatory after Archive. This phase does not write DB data
 *           and must remain safe for background execution only.
 * @sideEffects Updates the run heartbeat to REPORT_REQUIRED.
 */
final class LstsaReportPhase implements LstsaPhaseInterface
{
    public function execute(LstsaPipelineContext $context): void
    {
        $context->store->heartbeat($context->run, LstsaFsmState::REPORT_REQUIRED, $context->counts);
    }
}
