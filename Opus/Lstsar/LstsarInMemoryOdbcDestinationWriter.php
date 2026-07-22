<?php
declare(strict_types=1);

namespace Opus\Lstsar;

use Opus\Model\ModelRecord;
use Opus\Model\TableModel;

/**
 * Deterministic in-memory destination writer for smokes, tests and demos.
 */
final class LstsarInMemoryOdbcDestinationWriter implements LstsarOdbcDestinationWriterInterface, LstsarInMemoryOdbcDestinationWriterInterface
{
    /** @var array<string,array<string,mixed>> */
    private array $records = [];

    public function store(LstsarConfig $config, TableModel $destinationModel, array $record): string
    {
        new ModelRecord($destinationModel, $record);
        $recordId = hash('sha256', $config->runId() . ':' . $destinationModel->id() . ':' . json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->records[$recordId] = $record;

        return $recordId;
    }

    /** @return array<string,array<string,mixed>> */
    public function records(): array
    {
        return $this->records;
    }
}
