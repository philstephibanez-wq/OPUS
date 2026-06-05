<?php

declare(strict_types=1);

namespace ASAP\JSON;

/**
 * PUBLIC LEGACY-ALIGNED JSON
 *
 * Role:
 *   Preserve the ASAP JSON domain.
 *
 * Contract:
 *   JSON errors are explicit.
 *
 * Since:
 *   P112D4D_SAFE
 *
 * Deepened:
 *   P112D4F
 */
final class Json
{
    /**
     * @param array<string,mixed> $data
     */
    public function encode(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function pretty(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * @return array<string,mixed>
     */
    public function decode(string $json): array
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new \RuntimeException('ASAP_JSON_ROOT_INVALID');
        }

        return $data;
    }

    /**
     * @return array<string,mixed>
     */
    public function readFile(string $file): array
    {
        if (!is_file($file)) {
            throw new \RuntimeException('ASAP_JSON_FILE_MISSING: ' . $file);
        }

        return $this->decode((string) file_get_contents($file));
    }

    /**
     * @param array<string,mixed> $data
     */
    public function writeFile(string $file, array $data): void
    {
        $dir = dirname($file);

        if (!is_dir($dir)) {
            throw new \RuntimeException('ASAP_JSON_TARGET_DIR_MISSING: ' . $dir);
        }

        file_put_contents($file, $this->pretty($data));
    }
}
