<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * Storage boundary consumed by the LSTSAR engine.
 */
interface LstsarStoreInterface
{
    /** @param array<string,mixed> $record */
    public function store(string $datasetId, array $record): string;

    /** @return array<string,mixed> */
    public function restore(string $datasetId, string $recordId): array;

    /** @param array<string,mixed> $event */
    public function audit(string $datasetId, array $event): void;

    /** @return list<array<string,mixed>> */
    public function auditTrail(string $datasetId): array;
}
