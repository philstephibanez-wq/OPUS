<?php

declare(strict_types=1);

namespace Opus\Lstsa;

/*
 * OPUS_REFBOOK:
 *   domain: LSTSA
 *   role: Class LstsaArchivePhase belongs to the LSTSA Opus framework domain.
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
 * PUBLIC LSTSAR ARCHIVE PHASE
 *
 * @visibility public
 * @role Persists append-only execution evidence after Store succeeds.
 * @contract Archive is technical evidence. It is distinct from the final Report
 *           phase and can be purged by an explicit retention policy later.
 * @sideEffects Writes archive/quarantine JSON artifacts through LstsaRunStore.
 */
final class LstsaArchivePhase implements LstsaPhaseInterface
{
    public function execute(LstsaPipelineContext $context): void
    {
        $context->archivePath = $context->store->writeArchivePayload($context->run, 'database_staging_rows.json', [
            'definition_id' => $context->definition->id(),
            'definition_version' => $context->definition->version(),
            'load_connection' => $context->definition->loadConnection(),
            'load_table' => $context->definition->loadTable(),
            'store_connection' => $context->definition->storeConnection(),
            'store_table' => $context->definition->storeTable(),
            'store_mode' => $context->definition->storeMode(),
            'counts' => $context->counts,
            'rows' => $context->transformedRows,
        ]);

        if ($context->rejectedRows !== []) {
            $context->quarantinePath = $context->store->writeQuarantinePayload($context->run, 'database_staging_rejected_rows.json', [
                'definition_id' => $context->definition->id(),
                'definition_version' => $context->definition->version(),
                'rows' => $context->rejectedRows,
            ]);
        }

        $context->counts['archived'] = count((array)($context->run['artifacts']['archives'] ?? []));
    }
}
