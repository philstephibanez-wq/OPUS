<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Service;

/**
 * PUBLIC SERVICE CONTRACT
 *
 * Role:
 *   Provide one normalized RefBook snapshot to catalog/API consumers.
 *
 * Contract:
 *   Read-only snapshot loading only. No routing, no rendering, no fallback source.
 */
interface ReferenceSnapshotRepositoryInterface
{
    /**
     * @return array<string,mixed>
     */
    public function load(): array;
}
