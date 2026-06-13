<?php

declare(strict_types=1);

namespace Opus\Lstsa;

/*
 * OPUS_REFBOOK:
 *   domain: LSTSA
 *   role: Class LstsaLoadPhase belongs to the LSTSA Opus framework domain.
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
 * PUBLIC LSTSAR LOAD PHASE
 *
 * @visibility public
 * @role Loads rows from the declared source database table.
 * @contract Reads only the fields declared by the Lstsa definition. No source
 *           table discovery and no implicit column fallback are performed.
 * @sideEffects Reads from the source PDO connection and updates the in-memory
 *              run context counts.
 */
final class LstsaLoadPhase implements LstsaPhaseInterface
{
    public function execute(LstsaPipelineContext $context): void
    {
        $columns = array_map(
            static fn(string $name): string => LstsaIdentifier::quote($name),
            array_keys($context->definition->loadFields())
        );

        $sql = 'SELECT ' . implode(', ', $columns) . ' FROM ' . LstsaIdentifier::quote($context->definition->loadTable());
        $statement = $context->sourcePdo->query($sql);
        if ($statement === false) {
            throw new \RuntimeException('Lstsa load query failed');
        }

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            throw new \RuntimeException('Lstsa load result must be an array');
        }

        $context->loadedRows = array_values(array_map(
            static fn(array $row): array => $row,
            $rows
        ));
        $context->counts['loaded'] = count($context->loadedRows);
    }
}
