<?php

declare(strict_types=1);

namespace Opus\Form;

/*
 * OPUS_REFBOOK:
 *   domain: FORM
 *   role: Class FormValidationResult belongs to the FORM Opus framework domain.
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
 *   Carry the result of form validation.
 *
 * Since:
 *   P112D4B
 */
final class FormValidationResult
 implements FormValidationResultInterface {
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
