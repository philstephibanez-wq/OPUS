<?php

declare(strict_types=1);

namespace Opus\Form;

/*
 * OPUS_REFBOOK:
 *   domain: FORM
 *   role: Class FormDefinition belongs to the FORM Opus framework domain.
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
 *   Declare one explicit form schema.
 *
 * Contract:
 *   Form definition is data only. Validation belongs to FormValidator.
 *
 * Since:
 *   P112D4B
 */
final class FormDefinition
 implements FormDefinitionInterface {
    /** @var array<string,FormField> */
    private array $fields = [];

    /**
     * @param FormField[] $fields Field declarations.
     */
    public function __construct(public readonly string $name, array $fields)
    {
        if (trim($this->name) === '') {
            throw FormException::because('OPUS_FORM_NAME_EMPTY');
        }

        foreach ($fields as $field) {
            $this->fields[$field->name] = $field;
        }

        if ($this->fields === []) {
            throw FormException::because('OPUS_FORM_FIELDS_EMPTY', $this->name);
        }
    }

    /**
     * @return array<string,FormField>
     */
    public function fields(): array
    {
        return $this->fields;
    }
}
