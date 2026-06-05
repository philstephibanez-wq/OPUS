<?php

declare(strict_types=1);

namespace ASAP\VIEW;

use ASAP\Http\Response;
use ASAP\Template\TemplateRendererInterface;

/**
 * PUBLIC LEGACY-ALIGNED VIEW
 *
 * Role:
 *   Preserve the original ASAP `VIEW\Html` layer.
 *
 * Responsibility:
 *   Carry one HTML representation request: template, data, status and headers.
 *
 * Contract:
 *   View carries representation data only. It does not route, authorize or load
 *   business data. Rendering requires an explicit template renderer.
 *
 * Since:
 *   P112D4C
 */
final class Html
{
    /**
     * @param array<string,mixed> $data Prepared view data.
     * @param array<string,string> $headers HTTP headers.
     */
    public function __construct(
        public readonly string $template,
        public readonly array $data = [],
        public readonly int $status = 200,
        public readonly array $headers = ['Content-Type' => 'text/html; charset=utf-8']
    ) {
        if (trim($this->template) === '') {
            throw ViewException::because('ASAP_VIEW_TEMPLATE_EMPTY');
        }

        if ($this->status < 100 || $this->status > 599) {
            throw ViewException::because('ASAP_VIEW_STATUS_INVALID', (string) $this->status);
        }
    }

    public function toResponse(TemplateRendererInterface $renderer): Response
    {
        return new Response($renderer->render($this->template, $this->data), $this->status, $this->headers);
    }
}
