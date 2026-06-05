<?php

declare(strict_types=1);

namespace ASAP\Form;

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
            throw FormException::because('ASAP_FORM_ERROR_INVALID');
        }
    }
}
