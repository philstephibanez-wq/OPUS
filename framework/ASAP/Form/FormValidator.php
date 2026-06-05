<?php

declare(strict_types=1);

namespace ASAP\Form;

/**
 * PUBLIC VALIDATOR
 *
 * Role:
 *   Validate submitted data against a FormDefinition.
 *
 * Responsibility:
 *   Enforce required fields and type-level structural checks.
 *
 * Contract:
 *   Validator returns FormValidationResult. It does not render errors.
 *
 * Since:
 *   P112D4B
 */
final class FormValidator
{
    public function validate(SubmittedForm $form): FormValidationResult
    {
        $errors = [];

        foreach ($form->definition->fields() as $field) {
            $value = trim($form->value($field->name));

            if ($field->required && $value === '') {
                $errors[] = new FormError($field->name, 'ASAP_FORM_REQUIRED');
                continue;
            }

            if ($field->type === 'email' && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = new FormError($field->name, 'ASAP_FORM_EMAIL_INVALID');
            }
        }

        return new FormValidationResult($errors);
    }
}
