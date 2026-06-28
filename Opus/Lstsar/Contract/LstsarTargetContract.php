<?php
declare(strict_types=1);

namespace Opus\Lstsar\Contract;

/**
 * Immutable declaration of the target side of a LSTSAR job.
 */
final class LstsarTargetContract
{
    private string $id;
    private string $kind;
    private LstsarConstraintSet $constraints;
    /** @var array<string,mixed> */
    private array $metadata;

    /**
     * @param array<string,mixed> $metadata
     */
    public function __construct(string $id, string $kind, LstsarConstraintSet $constraints, array $metadata = [])
    {
        if (trim($id) === '') {
            throw new \InvalidArgumentException('OPUS_LSTSAR_TARGET_ID_EMPTY');
        }
        if (trim($kind) === '') {
            throw new \InvalidArgumentException('OPUS_LSTSAR_TARGET_KIND_EMPTY');
        }

        $this->id = $id;
        $this->kind = $kind;
        $this->constraints = $constraints;
        $this->metadata = $metadata;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'constraints' => $this->constraints->toArray(),
            'metadata' => $this->metadata,
        ];
    }
}
