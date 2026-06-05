<?php

declare(strict_types=1);

namespace ASAP\Form;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Carry the result of form validation.
 *
 * Since:
 *   P112D4B
 */
final class FormValidationResult
{
    /**
     * @param FormError[] $errors Validation errors.
     */
    public function __construct(public readonly array $errors)
    {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
