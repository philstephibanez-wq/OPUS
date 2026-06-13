<?php

declare(strict_types=1);

namespace Opus\Lstsa;

use ASAP\Database\DatabaseConnectionsConfig;
use PDO;

/*
 * OPUS_REFBOOK:
 *   domain: LSTSA
 *   role: Class LstsaPipelineContext belongs to the LSTSA Opus framework domain.
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
 * PUBLIC LSTSAR PIPELINE CONTEXT
 *
 * @visibility public
 * @role Carries the validated data shared by the Load/Secure/Transform/Store/
 *       Archive/Report phase objects for one background run.
 * @contract This object is scoped to one runner execution. Durable state belongs
 *           to LstsaRunStore, checkpoints, archives and reports.
 * @sideEffects None by itself. Phase objects mutate it during one run.
 */
final class LstsaPipelineContext
{
    public LstsaDefinition $definition;
    public DatabaseConnectionsConfig $connections;
    public PDO $sourcePdo;
    public PDO $targetPdo;

    /** @var list<array<string,mixed>> */
    public array $loadedRows = [];

    /** @var list<array<string,mixed>> */
    public array $acceptedRows = [];

    /** @var list<array<string,mixed>> */
    public array $transformedRows = [];

    /** @var list<array<string,mixed>> */
    public array $rejectedRows = [];

    public ?string $stageTable = null;
    public ?string $eventPath = null;
    public ?string $archivePath = null;
    public ?string $quarantinePath = null;

    /** @var array<string,int> */
    public array $counts = [
        'loaded' => 0,
        'accepted' => 0,
        'transformed' => 0,
        'stored' => 0,
        'archived' => 0,
        'checkpoints' => 0,
        'rejected' => 0,
        'errors' => 0,
    ];

    /**
     * @param array<string,mixed> $payload Validated run payload.
     */
    public function __construct(
        public array &$run,
        public readonly array $payload,
        public readonly LstsaRunStore $store
    ) {
    }

    /**
     * PUBLIC API
     *
     * @param string $message Error message to record with one rejected row.
     * @param array<string,mixed>|null $input Input row when available.
     * @param array<string,mixed>|null $output Output row when available.
     */
    public function reject(string $message, ?array $input = null, ?array $output = null): void
    {
        $this->rejectedRows[] = [
            'row_index' => count($this->rejectedRows),
            'errors' => [$message],
            'input' => $input,
            'output' => $output,
        ];
        ++$this->counts['rejected'];
        ++$this->counts['errors'];
    }
}
