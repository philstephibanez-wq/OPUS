<?php

declare(strict_types=1);

namespace ASAP;

use ASAP\Template\TemplateRendererInterface;

/**
 * PUBLIC LEGACY COMPATIBILITY SHIM
 *
 * Role:
 *   Restore the small top-level `ASAP\View` surface.
 *
 * Contract:
 *   Rendering requires an explicit renderer. No template fallback.
 *
 * Since:
 *   P112P1
 */
final class View
{
    /** @param array<string,mixed> $data */
    public function __construct(
        private readonly string $template = '',
        private readonly array $data = []
    ) {
    }

    public function render(TemplateRendererInterface|callable $renderer): string
    {
        if ($this->template === '') {
            throw Exception::because('ASAP_VIEW_TEMPLATE_EMPTY');
        }

        if ($renderer instanceof TemplateRendererInterface) {
            return $renderer->render($this->template, $this->data);
        }

        $result = $renderer($this->template, $this->data);

        if (!is_string($result)) {
            throw Exception::because('ASAP_VIEW_RENDERER_RESULT_INVALID');
        }

        return $result;
    }
}
