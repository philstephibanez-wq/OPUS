<?php

declare(strict_types=1);

namespace Opus\Renderer;

use ASAP\Http\Response;
use ASAP\Template\TemplateRendererInterface;

/*
 * OPUS_REFBOOK:
 *   domain: RENDERER
 *   role: Class HtmlRenderer belongs to the RENDERER Opus framework domain.
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
 * PUBLIC RENDERER
 *
 * Role:
 *   Render HTML ViewModels through the official template adapter.
 *
 * Responsibility:
 *   Convert prepared ViewModel data into Response.
 *
 * Contract:
 *   This renderer represents data only. It does not load content or call services.
 *
 * Since:
 *   P112D4B
 */
final class HtmlRenderer implements RendererInterface
{
    public function __construct(private readonly TemplateRendererInterface $templateRenderer)
    {
    }

    public function render(ViewModel $viewModel): Response
    {
        $body = $this->templateRenderer->render($viewModel->template, $viewModel->data);

        return new Response($body, $viewModel->status, $viewModel->headers);
    }
}
