<?php
declare(strict_types=1);

namespace Opus\Lstsar;

use Opus\Model\TableModel;

/**
 * Runtime context shared by the six LSTSAR stages.
 */
final class LstsarContext
{
    private LstsarConfig $config;
    private TableModel $sourceModel;
    private TableModel $destinationModel;
    /** @var array<string,mixed> */
    private array $sourceRecord;
    /** @var array<string,mixed> */
    private array $transformedRecord;
    /** @var array<string,array<string,mixed>> */
    private array $stagePayloads;

    /**
     * @param array<string,mixed> $sourceRecord
     * @param array<string,mixed> $transformedRecord
     * @param array<string,array<string,mixed>> $stagePayloads
     */
    public function __construct(LstsarConfig $config, TableModel $sourceModel, TableModel $destinationModel, array $sourceRecord, array $transformedRecord = [], array $stagePayloads = [])
    {
        $this->config = $config;
        $this->sourceModel = $sourceModel;
        $this->destinationModel = $destinationModel;
        $this->sourceRecord = $sourceRecord;
        $this->transformedRecord = $transformedRecord;
        $this->stagePayloads = $stagePayloads;
    }

    public function config(): LstsarConfig
    {
        return $this->config;
    }

    public function sourceModel(): TableModel
    {
        return $this->sourceModel;
    }

    public function destinationModel(): TableModel
    {
        return $this->destinationModel;
    }

    /** @return array<string,mixed> */
    public function sourceRecord(): array
    {
        return $this->sourceRecord;
    }

    /** @return array<string,mixed> */
    public function transformedRecord(): array
    {
        return $this->transformedRecord;
    }

    /** @param array<string,mixed> $record */
    public function withTransformedRecord(array $record): self
    {
        return new self($this->config, $this->sourceModel, $this->destinationModel, $this->sourceRecord, $record, $this->stagePayloads);
    }

    /** @param array<string,mixed> $payload */
    public function withStagePayload(string $stage, array $payload): self
    {
        $stage = LstsarStageName::normalize($stage);
        $payloads = $this->stagePayloads;
        $payloads[$stage] = $payload;

        return new self($this->config, $this->sourceModel, $this->destinationModel, $this->sourceRecord, $this->transformedRecord, $payloads);
    }

    /** @return array<string,mixed> */
    public function stagePayload(string $stage): array
    {
        $stage = LstsarStageName::normalize($stage);
        return $this->stagePayloads[$stage] ?? [];
    }

    /** @return array<string,array<string,mixed>> */
    public function stagePayloads(): array
    {
        return $this->stagePayloads;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'run_id' => $this->config->runId(),
            'source' => $this->config->source(),
            'destination' => $this->config->destination(),
            'source_model' => $this->sourceModel->id(),
            'destination_model' => $this->destinationModel->id(),
            'source_record_fields' => array_keys($this->sourceRecord),
            'transformed_record_fields' => array_keys($this->transformedRecord),
            'stage_payloads' => array_keys($this->stagePayloads),
        ];
    }
}
