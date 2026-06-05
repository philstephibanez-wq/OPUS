<?php

declare(strict_types=1);

namespace ASAP\Renderer;

use ASAP\Http\Response;

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
