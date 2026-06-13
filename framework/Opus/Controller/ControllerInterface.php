<?php

declare(strict_types=1);

namespace Opus\Controller;

/*
 * OPUS_REFBOOK:
 *   domain: CONTROLLER
 *   role: Interface ControllerInterface belongs to the CONTROLLER Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the CONTROLLER domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - controller-overview
 *   diagrams:
 *     - controller-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY-ALIGNED CONTRACT
 *
 * Role:
 *   Mark Opus controller classes.
 *
 * Responsibility:
 *   Preserve the original Opus controller domain as the official dispatch target.
 *
 * Since:
 *   P112D4C
 */
interface ControllerInterface
{
}
