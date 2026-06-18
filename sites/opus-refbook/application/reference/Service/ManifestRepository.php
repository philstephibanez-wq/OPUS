<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Service;

use RuntimeException;

/**
 * LEGACY FIXTURE SERVICE
 *
 * Role:
 *   Load the historical generated Opus source manifest for legacy/offline tests only.
 *
 * Contract:
 *   Not used by the runtime controllers. Runtime data must come from the shared
 *   Opus provider through ReferenceRuntimeSnapshotRepository.
 */
final class ManifestRepository implements ReferenceSnapshotRepositoryInterface
{
    public function __construct(private readonly string $manifestFile)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function load(): array
    {
        if (!is_file($this->manifestFile)) {
            throw new RuntimeException('OPUS_REFBOOK_MANIFEST_FILE_MISSING=' . $this->manifestFile);
        }

        $json = json_decode((string) file_get_contents($this->manifestFile), true);
        if (!is_array($json)) {
            throw new RuntimeException('OPUS_REFBOOK_MANIFEST_JSON_INVALID=' . $this->manifestFile);
        }

        if (($json['schema'] ?? null) !== 'OPUS_REFBOOK_SOURCE_MANIFEST_V1') {
            throw new RuntimeException('OPUS_REFBOOK_MANIFEST_SCHEMA_INVALID=' . (string)($json['schema'] ?? 'missing'));
        }

        return $json;
    }
}
