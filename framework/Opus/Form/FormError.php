<?php

declare(strict_types=1);

namespace Opus\Form;

/*
 * OPUS_REFBOOK:
 *   domain: FORM
 *   role: Class FormError belongs to the FORM Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the FORM domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - form-overview
 *   diagrams:
 *     - form-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Carry one validation error.
 *
 * Since:
 *   P112D4B
 */
final class FormError
{
    public function __construct(
        public readonly string $field,
        public readonly string $code
    ) {
        if (trim($this->field) === '' || trim($this->code) === '') {
            throw FormException::because('OPUS_FORM_ERROR_INVALID');
        }
    }
}
