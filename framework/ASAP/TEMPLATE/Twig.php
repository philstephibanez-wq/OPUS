<?php

declare(strict_types=1);

namespace ASAP\TEMPLATE;

use ASAP\Template\TwigTemplateRenderer;

/**
 * PUBLIC LEGACY-ALIGNED TWIG ADAPTER
 *
 * Role:
 *   Preserve the original ASAP `TEMPLATE\Twig` adapter while delegating to the
 *   modern Composer-backed Twig renderer.
 *
 * Contract:
 *   No template fallback. Missing templates fail through the renderer.
 *
 * Since:
 *   P112D4C
 */
final class Twig implements Adapter
{
    public function __construct(private readonly TwigTemplateRenderer $renderer)
    {
    }

    public function render(string $template, array $data = []): string
    {
        return $this->renderer->render($template, $data);
    }
}
