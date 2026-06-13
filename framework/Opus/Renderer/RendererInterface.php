<?php

declare(strict_types=1);

namespace Opus\Renderer;

use ASAP\Http\Response;

/*
 * OPUS_REFBOOK:
 *   domain: RENDERER
 *   role: Interface RendererInterface belongs to the RENDERER Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the RENDERER domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - renderer-overview
 *   diagrams:
 *     - renderer-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC CONTRACT
 *
 * Role:
 *   Render a ViewModel into an HTTP response.
 *
 * Responsibility:
 *   Own representation conversion only.
 *
 * Contract:
 *   No route decision, no controller dispatch, no content lookup.
 *
 * Since:
 *   P112D4B
 */
interface RendererInterface
{
    public function render(ViewModel $viewModel): Response;
}
