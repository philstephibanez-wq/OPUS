<?php

declare(strict_types=1);

namespace ASAP\Renderer;

use ASAP\Contract\ContractException;

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
            throw ContractException::because('ASAP_VIEW_MODEL_TEMPLATE_EMPTY');
        }

        if ($this->status < 100 || $this->status > 599) {
            throw ContractException::because('ASAP_VIEW_MODEL_STATUS_INVALID', (string) $this->status);
        }
    }
}
