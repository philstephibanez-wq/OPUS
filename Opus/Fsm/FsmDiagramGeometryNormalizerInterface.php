<?php
declare(strict_types=1);

namespace Opus\Fsm;

/**
 * Contract for deterministic post-layout FSM SVG geometry normalization.
 *
 * The normalizer may alter presentation geometry only. FSM state, transition,
 * signal, target, actionability and semantic labels remain authoritative.
 */
interface FsmDiagramGeometryNormalizerInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function normalize(string $html, float $scale = 0.60): string;
}
