<?php
declare(strict_types=1);

namespace Opus\Lstsar;

use Opus\Model\TableModel;

/**
 * Deterministic in-memory source reader for smokes, tests and demos.
 */
final class LstsarInMemoryOdbcSourceReader implements LstsarOdbcSourceReaderInterface
{
    /** @var array<string,array<string,mixed>> */
    private array $recordsByModel;

    /** @param array<string,array<string,mixed>> $recordsByModel */
    public function __construct(array $recordsByModel)
    {
        foreach ($recordsByModel as $modelId => $record) {
            if (!is_array($record)) {
                throw new \InvalidArgumentException('OPUS_LSTSAR_MEMORY_SOURCE_RECORD_INVALID: ' . (string) $modelId);
            }
        }
        $this->recordsByModel = $recordsByModel;
    }

    public function load(LstsarConfig $config, TableModel $sourceModel): array
    {
        $modelId = $sourceModel->id();
        if (!isset($this->recordsByModel[$modelId])) {
            throw new \RuntimeException('OPUS_LSTSAR_MEMORY_SOURCE_RECORD_MISSING: ' . $modelId);
        }

        return $this->recordsByModel[$modelId];
    }
}
