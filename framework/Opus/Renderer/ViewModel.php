<?php

declare(strict_types=1);

namespace Opus\Renderer;

use ASAP\Contract\ContractException;

/*
 * OPUS_REFBOOK:
 *   domain: RENDERER
 *   role: Class ViewModel belongs to the RENDERER Opus framework domain.
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
 * PUBLIC VIEW MODEL
 *
 * Role:
 *   Carry prepared representation data from controller to renderer.
 *
 * Responsibility:
 *   Declare template, data, HTTP status and headers.
 *
 * Contract:
 *   Data only. No service calls, no filesystem reads, no template rendering.
 *
 * Since:
 *   P112D4B
 */
final class ViewModel
{
    /**
     * @param string $template Template name.
     * @param array<string,mixed> $data Prepared view data.
     * @param int $status HTTP status.
     * @param array<string,string> $headers Response headers.
     */
    public function __construct(
        public readonly string $template,
        public readonly array $data,
        public readonly int $status = 200,
        public readonly array $headers = ['Content-Type' => 'text/html; charset=utf-8']
    ) {
        if (trim($this->template) === '') {
            throw ContractException::because('OPUS_VIEW_MODEL_TEMPLATE_EMPTY');
        }

        if ($this->status < 100 || $this->status > 599) {
            throw ContractException::because('OPUS_VIEW_MODEL_STATUS_INVALID', (string) $this->status);
        }
    }
}
