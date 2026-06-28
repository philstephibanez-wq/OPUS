<?php
declare(strict_types=1);

namespace Opus\Lstsar\Pipeline;

use Opus\Lstsar\LstsarPipelineInterface;

/**
 * Immutable pipeline contract loaded from the LSTSAR registry.
 */
final class DeclaredLstsarPipeline implements LstsarPipelineInterface
{
    /** @var array<string,mixed> */
    private array $data;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(array $data)
    {
        if (trim((string) ($data['id'] ?? '')) === '') {
            throw new \InvalidArgumentException('OPUS_LSTSAR_PIPELINE_ID_EMPTY');
        }
        if (!isset($data['stages']) || !is_array($data['stages']) || $data['stages'] === []) {
            throw new \InvalidArgumentException('OPUS_LSTSAR_PIPELINE_STAGES_EMPTY');
        }

        $this->data = $data;
    }

    public function id(): string
    {
        return (string) $this->data['id'];
    }

    public function stageOrder(): array
    {
        return array_map('strval', $this->data['stages']);
    }

    public function describe(): array
    {
        return $this->data;
    }
}
