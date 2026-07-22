<?php
declare(strict_types=1);

namespace Opus\Lstsar;

use Opus\Model\TableModel;

/**
 * Read-only context passed to a destination-assignment transform hook.
 */
final class LstsarTransformHookContext implements LstsarTransformHookContextInterface
{
    private LstsarConfig $config;
    private TableModel $sourceModel;
    private TableModel $destinationModel;
    /** @var array<string,mixed> */
    private array $sourceRecord;
    /** @var array<string,mixed> */
    private array $destinationRecord;
    private string $destinationField;
    /** @var array<string,mixed> */
    private array $assignment;

    /**
     * @param array<string,mixed> $sourceRecord
     * @param array<string,mixed> $destinationRecord
     * @param array<string,mixed> $assignment
     */
    public function __construct(LstsarConfig $config, TableModel $sourceModel, TableModel $destinationModel, array $sourceRecord, array $destinationRecord, string $destinationField, array $assignment)
    {
        $this->config = $config;
        $this->sourceModel = $sourceModel;
        $this->destinationModel = $destinationModel;
        $this->sourceRecord = $sourceRecord;
        $this->destinationRecord = $destinationRecord;
        $this->destinationField = $destinationField;
        $this->assignment = $assignment;
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
    public function destinationRecord(): array
    {
        return $this->destinationRecord;
    }

    public function destinationField(): string
    {
        return $this->destinationField;
    }

    /** @return array<string,mixed> */
    public function assignment(): array
    {
        return $this->assignment;
    }
}
