<?php

declare(strict_types=1);

namespace Opus\Form;

/*
 * OPUS_REFBOOK:
 *   domain: FORM
 *   role: Class SubmittedForm belongs to the FORM Opus framework domain.
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
 *   Carry submitted values for one declared form.
 *
 * Contract:
 *   Submission data only. No validation side effect.
 *
 * Since:
 *   P112D4B
 */
final class SubmittedForm
 implements SubmittedFormInterface {
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
