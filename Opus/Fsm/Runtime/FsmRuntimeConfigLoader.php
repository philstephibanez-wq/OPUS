<?php
declare(strict_types=1);

namespace Opus\Fsm\Runtime;

final class FsmRuntimeConfigLoader implements FsmRuntimeConfigLoaderInterface
{
    private string $configDir;

    public function __construct(string $configDir)
    {
        $this->configDir = rtrim(str_replace('\\', '/', $configDir), '/');
        if (!is_dir($this->configDir)) {
            throw new \RuntimeException('OPUS_FSM_RUNTIME_CONFIG_DIR_MISSING: ' . $this->configDir);
        }
    }

    public function availableMaps(): array
    {
        $files = glob($this->configDir . '/*.json') ?: [];
        $maps = [];
        foreach ($files as $file) {
            $maps[] = basename($file, '.json');
        }
        sort($maps);
        return $maps;
    }

    public function load(string $id): array
    {
        if (!preg_match('/^[a-z0-9_\-]+$/', $id)) {
            throw new \InvalidArgumentException('OPUS_FSM_RUNTIME_CONFIG_ID_INVALID: ' . $id);
        }
        $path = $this->configDir . '/' . $id . '.json';
        if (!is_file($path)) {
            throw new \RuntimeException('OPUS_FSM_RUNTIME_CONFIG_MISSING: ' . $id);
        }
        $json = file_get_contents($path);
        if (!is_string($json)) {
            throw new \RuntimeException('OPUS_FSM_RUNTIME_CONFIG_READ_FAILED: ' . $id);
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException('OPUS_FSM_RUNTIME_CONFIG_JSON_INVALID: ' . $id);
        }
        $this->validate($id, $data);
        return $data;
    }

    public function flowForDisplay(string $id): array
    {
        $map = $this->load($id);
        $rows = [];
        foreach ($map['transitions'] as $transition) {
            $rows[] = [
                'state' => (string)$transition['from'],
                'signal' => (string)$transition['signal'],
                'action' => (string)($transition['collector'] ?? ''),
                'next' => (string)$transition['to'],
            ];
        }
        return $rows;
    }

    private function validate(string $id, array $data): void
    {
        if (($data['schema'] ?? '') !== 'OPUS_FSM_RUNTIME_V1') {
            throw new \RuntimeException('OPUS_FSM_RUNTIME_CONFIG_SCHEMA_INVALID: ' . $id);
        }
        if (($data['id'] ?? '') !== $id) {
            throw new \RuntimeException('OPUS_FSM_RUNTIME_CONFIG_ID_MISMATCH: ' . $id);
        }
        if (!isset($data['transitions']) || !is_array($data['transitions']) || $data['transitions'] === []) {
            throw new \RuntimeException('OPUS_FSM_RUNTIME_CONFIG_TRANSITIONS_EMPTY: ' . $id);
        }
        foreach ($data['transitions'] as $index => $transition) {
            if (!is_array($transition)) {
                throw new \RuntimeException('OPUS_FSM_RUNTIME_CONFIG_TRANSITION_INVALID: ' . $id . '#' . $index);
            }
            foreach (['from', 'signal', 'to'] as $required) {
                if (!isset($transition[$required]) || trim((string)$transition[$required]) === '') {
                    throw new \RuntimeException('OPUS_FSM_RUNTIME_CONFIG_TRANSITION_FIELD_MISSING: ' . $id . '#' . $index . ':' . $required);
                }
            }
        }
    }
}