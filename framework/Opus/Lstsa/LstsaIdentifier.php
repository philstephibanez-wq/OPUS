<?php

declare(strict_types=1);

namespace Opus\Lstsa;

/*
 * OPUS_REFBOOK:
 *   domain: LSTSA
 *   role: Class LstsaIdentifier belongs to the LSTSA Opus framework domain.
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
 * INTERNAL LSTSAR IDENTIFIER HELPER
 *
 * @visibility internal
 * @role Quotes and validates SQL identifiers used by the controlled SQLite test
 *       execution path.
 * @contract Identifiers are never concatenated before validation. Values remain
 *           bound through prepared statements.
 * @sideEffects None.
 */
final class LstsaIdentifier
{
    public static function quote(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException('Invalid Lstsa SQL identifier: ' . $identifier);
        }

        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public static function stageTable(string $runId, string $targetTable): string
    {
        $seed = preg_replace('/[^A-Za-z0-9_]/', '_', $runId . '_' . $targetTable) ?? '';
        $seed = trim($seed, '_');
        if ($seed === '') {
            throw new \InvalidArgumentException('Invalid Lstsa staging seed');
        }

        return 'lstsa_stage_' . substr($seed, 0, 48);
    }
}
