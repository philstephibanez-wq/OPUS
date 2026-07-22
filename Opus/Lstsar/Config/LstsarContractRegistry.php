<?php
declare(strict_types=1);

namespace Opus\Lstsar\Config;

use Opus\Lstsar\Pipeline\DeclaredLstsarPipeline;

/**
 * Data-driven LSTSAR contract registry.
 *
 * This registry exposes declared LSTSAR stages and pipeline contracts. It does not run
 * a LSTSAR job and it does not embed endpoint-specific behavior.
 */
final class LstsarContractRegistry implements LstsarContractRegistryInterface
{
    /** @var array<string,mixed> */
    private array $data;

    /** @param array<string,mixed> $data */
    private function __construct(array $data)
    {
        $this->validate($data);
        $this->data = $data;
    }

    public static function fromProjectRoot(string $projectRoot): self
    {
        $projectRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR);
        if ($projectRoot === '') {
            throw new \RuntimeException('OPUS_LSTSAR_PROJECT_ROOT_MISSING');
        }

        return self::fromFile($projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'lstsar' . DIRECTORY_SEPARATOR . 'contracts.json');
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException('OPUS_LSTSAR_CONTRACT_REGISTRY_MISSING: ' . $path);
        }

        $json = file_get_contents($path);
        if (!is_string($json)) {
            throw new \RuntimeException('OPUS_LSTSAR_CONTRACT_REGISTRY_READ_FAILED: ' . $path);
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException('OPUS_LSTSAR_CONTRACT_REGISTRY_JSON_INVALID: ' . $path);
        }

        return new self($data);
    }

    /**
     * @return array<string,mixed>
     */
    public function export(): array
    {
        return $this->data;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function pipeline(string $id): ?array
    {
        foreach ((array) ($this->data['pipelines'] ?? []) as $pipeline) {
            if (is_array($pipeline) && (string) ($pipeline['id'] ?? '') === $id) {
                return $pipeline;
            }
        }

        return null;
    }

    public function declaredPipeline(string $id): ?DeclaredLstsarPipeline
    {
        $pipeline = $this->pipeline($id);
        if ($pipeline === null) {
            return null;
        }

        return new DeclaredLstsarPipeline($pipeline);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function validate(array $data): void
    {
        if (($data['contract'] ?? '') !== 'OPUS_LSTSAR_CONTRACT_REGISTRY_V1') {
            throw new \RuntimeException('OPUS_LSTSAR_CONTRACT_REGISTRY_CONTRACT_INVALID');
        }

        if (!isset($data['stages']) || !is_array($data['stages']) || $data['stages'] === []) {
            throw new \RuntimeException('OPUS_LSTSAR_CONTRACT_REGISTRY_STAGES_EMPTY');
        }

        if (!isset($data['pipelines']) || !is_array($data['pipelines']) || $data['pipelines'] === []) {
            throw new \RuntimeException('OPUS_LSTSAR_CONTRACT_REGISTRY_PIPELINES_EMPTY');
        }

        $declaredStages = [];
        foreach ($data['stages'] as $index => $stage) {
            if (!is_array($stage)) {
                throw new \RuntimeException('OPUS_LSTSAR_CONTRACT_STAGE_INVALID: ' . $index);
            }
            foreach (['id', 'interface', 'responsibility'] as $required) {
                if (!isset($stage[$required]) || trim((string) $stage[$required]) === '') {
                    throw new \RuntimeException('OPUS_LSTSAR_CONTRACT_STAGE_FIELD_MISSING: ' . $index . ':' . $required);
                }
            }
            $declaredStages[] = (string) $stage['id'];
        }

        foreach ($data['pipelines'] as $index => $pipeline) {
            if (!is_array($pipeline)) {
                throw new \RuntimeException('OPUS_LSTSAR_CONTRACT_PIPELINE_INVALID: ' . $index);
            }
            foreach (['id', 'stages', 'status'] as $required) {
                if (!array_key_exists($required, $pipeline)) {
                    throw new \RuntimeException('OPUS_LSTSAR_CONTRACT_PIPELINE_FIELD_MISSING: ' . $index . ':' . $required);
                }
            }
            foreach ((array) $pipeline['stages'] as $stageId) {
                if (!in_array((string) $stageId, $declaredStages, true)) {
                    throw new \RuntimeException('OPUS_LSTSAR_CONTRACT_PIPELINE_UNKNOWN_STAGE: ' . $pipeline['id'] . ':' . $stageId);
                }
            }
        }
    }
}
