<?php
declare(strict_types=1);

namespace Opus\Lstsar\Stage;

/**
 * Immutable result of one LSTSAR stage.
 *
 * In P7B3 this is used by the engine skeleton to declare what would be executed,
 * without performing real load, storage or report side effects.
 */
final class LstsarStageResult implements LstsarStageResultInterface
{
    private string $stage;
    private string $status;
    private string $reason;
    /** @var array<string,mixed> */
    private array $metadata;

    /**
     * @param array<string,mixed> $metadata
     */
    public function __construct(string $stage, string $status, string $reason, array $metadata = [])
    {
        if (trim($stage) === '') {
            throw new \InvalidArgumentException('OPUS_LSTSAR_STAGE_RESULT_STAGE_EMPTY');
        }
        if (trim($status) === '') {
            throw new \InvalidArgumentException('OPUS_LSTSAR_STAGE_RESULT_STATUS_EMPTY');
        }

        $this->stage = $stage;
        $this->status = $status;
        $this->reason = $reason;
        $this->metadata = $metadata;
    }

    /**
     * @param array<string,mixed> $metadata
     */
    public static function planned(string $stage, array $metadata = []): self
    {
        return new self($stage, 'planned_not_executed', 'P7_LSTSAR_CONTRACT_ENGINE_SKELETON', $metadata);
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'stage' => $this->stage,
            'status' => $this->status,
            'reason' => $this->reason,
            'metadata' => $this->metadata,
        ];
    }
}
