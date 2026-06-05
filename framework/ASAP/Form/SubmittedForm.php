<?php

declare(strict_types=1);

namespace ASAP\Form;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Carry submitted values for one declared form.
 *
 * Contract:
 *   Submission data only. No validation side effect.
 *
 * Since:
 *   P112D4B
 */
final class SubmittedForm
{
    /**
     * @param array<string,string> $values Submitted values.
     */
    public function __construct(
        public readonly FormDefinition $definition,
        public readonly array $values
    ) {
    }

    public function value(string $name): string
    {
        return (string) ($this->values[$name] ?? '');
    }
}
