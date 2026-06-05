<?php

declare(strict_types=1);

namespace ASAP\Form;

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
{
    /** @var array<string,FormField> */
    private array $fields = [];

    /**
     * @param FormField[] $fields Field declarations.
     */
    public function __construct(public readonly string $name, array $fields)
    {
        if (trim($this->name) === '') {
            throw FormException::because('ASAP_FORM_NAME_EMPTY');
        }

        foreach ($fields as $field) {
            $this->fields[$field->name] = $field;
        }

        if ($this->fields === []) {
            throw FormException::because('ASAP_FORM_FIELDS_EMPTY', $this->name);
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
