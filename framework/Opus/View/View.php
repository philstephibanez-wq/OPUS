<?php

declare(strict_types=1);

namespace Opus\View;

use ASAP\Template\TemplateRendererInterface;

/*
 * OPUS_REFBOOK:
 *   domain: VIEW
 *   role: Class View belongs to the VIEW Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the VIEW domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - view-overview
 *   diagrams:
 *     - view-runtime
 * END_OPUS_REFBOOK
 */
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
            throw \ASAP\Exception\Exception::because('OPUS_VIEW_TEMPLATE_EMPTY');
        }

        if ($renderer instanceof TemplateRendererInterface) {
            return $renderer->render($this->template, $this->data);
        }

        $result = $renderer($this->template, $this->data);

        if (!is_string($result)) {
            throw \ASAP\Exception\Exception::because('OPUS_VIEW_RENDERER_RESULT_INVALID');
        }

        return $result;
    }
}
