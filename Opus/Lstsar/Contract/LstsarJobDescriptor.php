<?php
declare(strict_types=1);

namespace Opus\Lstsar\Contract;

use Opus\Lstsar\LstsarJobInterface;

/**
 * Immutable descriptor for a declared LSTSAR job.
 *
 * This is a contract object. It does not load, transform, store or report data by
 * itself.
 */
final class LstsarJobDescriptor implements LstsarJobInterface
{
    private string $id;
    private string $pipelineId;
    private LstsarSourceContract $source;
    private LstsarTargetContract $target;
    /** @var array<string,mixed> */
    private array $metadata;

    /**
     * @param array<string,mixed> $metadata
     */
    public function __construct(string $id, string $pipelineId, LstsarSourceContract $source, LstsarTargetContract $target, array $metadata = [])
    {
        if (trim($id) === '') {
            throw new \InvalidArgumentException('OPUS_LSTSAR_JOB_ID_EMPTY');
        }
        if (trim($pipelineId) === '') {
            throw new \InvalidArgumentException('OPUS_LSTSAR_JOB_PIPELINE_EMPTY');
        }

        $this->id = $id;
        $this->pipelineId = $pipelineId;
        $this->source = $source;
        $this->target = $target;
        $this->metadata = $metadata;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function pipelineId(): string
    {
        return $this->pipelineId;
    }

    public function sourceContract(): array
    {
        return $this->source->toArray();
    }

    public function targetContract(): array
    {
        return $this->target->toArray();
    }

    public function constraints(): array
    {
        return [
            'source' => $this->source->toArray()['constraints'],
            'target' => $this->target->toArray()['constraints'],
        ];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'pipeline_id' => $this->pipelineId,
            'source' => $this->source->toArray(),
            'target' => $this->target->toArray(),
            'constraints' => $this->constraints(),
            'metadata' => $this->metadata,
        ];
    }
}
