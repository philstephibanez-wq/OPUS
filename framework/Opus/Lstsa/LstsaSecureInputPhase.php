<?php

declare(strict_types=1);

namespace Opus\Lstsa;

/*
 * OPUS_REFBOOK:
 *   domain: LSTSA
 *   role: Class LstsaSecureInputPhase belongs to the LSTSA Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the LSTSA domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - lstsa-overview
 *   diagrams:
 *     - lstsa-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LSTSAR SECURE INPUT PHASE
 *
 * @visibility public
 * @role Validates loaded source rows before transformation.
 * @contract Any rejected row aborts the database staging execution. The target
 *           tables are not touched unless the whole source batch is valid.
 * @sideEffects Updates accepted/rejected rows and counters in the run context.
 */
final class LstsaSecureInputPhase implements LstsaPhaseInterface
{
    public function execute(LstsaPipelineContext $context): void
    {
        foreach ($context->loadedRows as $index => $row) {
            $errors = [];

            foreach ($context->definition->loadFields() as $fieldName => $constraint) {
                $errors = array_merge($errors, $constraint->validate($row[$fieldName] ?? null, 'SECURE_INPUT'));
            }

            foreach (array_keys($row) as $fieldName) {
                if (!array_key_exists((string)$fieldName, $context->definition->loadFields())) {
                    $errors[] = 'SECURE_INPUT:' . (string)$fieldName . ':OPUS_Lstsa_FIELD_UNKNOWN';
                }
            }

            if ($errors !== []) {
                $context->rejectedRows[] = [
                    'row_index' => $index,
                    'errors' => $errors,
                    'input' => $row,
                    'output' => null,
                ];
                ++$context->counts['rejected'];
                $context->counts['errors'] += count($errors);
                continue;
            }

            $context->acceptedRows[] = $row;
        }

        $context->counts['accepted'] = count($context->acceptedRows);

        if ($context->rejectedRows !== []) {
            throw new \RuntimeException('Lstsa secure input rejected rows; target store aborted');
        }
    }
}
