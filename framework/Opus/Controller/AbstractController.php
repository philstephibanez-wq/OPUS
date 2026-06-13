<?php

declare(strict_types=1);

namespace Opus\Controller;

/*
 * OPUS_REFBOOK:
 *   domain: CONTROLLER
 *   role: Class AbstractController belongs to the CONTROLLER Opus framework domain.
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
 * PUBLIC LEGACY-ALIGNED ABSTRACT CONTROLLER
 *
 * Role:
 *   Compatibility alias for projects expecting an abstract base controller.
 *
 * Contract:
 *   Extends the canonical legacy-aligned `Controller`.
 *
 * Since:
 *   P112D4C
 */
abstract class AbstractController extends Controller
{
}
