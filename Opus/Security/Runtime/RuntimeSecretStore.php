<?php
declare(strict_types=1);

namespace Opus\Security\Runtime;

use Opus\File\File;
use Opus\File\Json;
use Opus\File\StructuredFileLoader;

/** Runtime-only secret store shared by independently started OPUS processes. */
final class RuntimeSecretStore implements RuntimeSecretStoreInterface
{
    public const CONTRACT = 'OPUS_RUNTIME_SECRET_STORE_V1';

    private readonly File $file;
    private readonly StructuredFileLoader $loader;

    private function __construct(private readonly string $storePath)
    {
        $this->file = File::instance();
        $this->loader = StructuredFileLoader::instance();
    }

    public static function forPath(string $storePath): self
    {
        $storePath = trim(str_replace('\\', '/', $storePath));
        if ($storePath === '' || str_contains($storePath, "\0")) {
            throw new \InvalidArgumentException(
                'OPUS_RUNTIME_SECRET_STORE_PATH_INVALID'
            );
        }

        return new self($storePath);
    }

    public function ensure(array $bindings): array
    {
        $bindings = $this->bindings($bindings);
        $directory = dirname($this->storePath);
        if (!is_dir($directory)
            && !mkdir($directory, 0775, true)
            && !is_dir($directory)) {
            throw new \RuntimeException(
                'OPUS_RUNTIME_SECRET_DIRECTORY_CREATE_FAILED'
            );
        }

        $lockPath = $this->storePath . '.lock';
        $lock = fopen($lockPath, 'c+b');
        if ($lock === false) {
            throw new \RuntimeException(
                'OPUS_RUNTIME_SECRET_LOCK_OPEN_FAILED'
            );
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException(
                    'OPUS_RUNTIME_SECRET_LOCK_FAILED'
                );
            }

            if (!$this->file->exists($this->storePath)) {
                $aliases = array_values(array_unique(array_values($bindings)));
                $secrets = [];
                foreach ($aliases as $alias) {
                    $secrets[$alias] = bin2hex(random_bytes(32));
                }
                $this->file->writeAtomic(
                    $this->storePath,
                    Json::instance()->encode([
                        'contract' => self::CONTRACT,
                        'created_at_utc' => gmdate('c'),
                        'secrets' => $secrets,
                    ], true)
                );
                if (DIRECTORY_SEPARATOR === '/') {
                    @chmod($this->storePath, 0600);
                }
            }

            $data = $this->loader->read($this->storePath);
            if (($data['contract'] ?? null) !== self::CONTRACT) {
                throw new \RuntimeException(
                    'OPUS_RUNTIME_SECRET_STORE_CONTRACT_INVALID'
                );
            }
            $stored = is_array($data['secrets'] ?? null)
                ? $data['secrets']
                : [];

            $environment = [];
            foreach ($bindings as $environmentName => $alias) {
                $secret = trim((string) ($stored[$alias] ?? ''));
                if (preg_match('/^[a-f0-9]{64}$/', $secret) !== 1) {
                    throw new \RuntimeException(
                        'OPUS_RUNTIME_SECRET_VALUE_INVALID:' . $alias
                    );
                }
                $environment[$environmentName] = $secret;
            }

            return $environment;
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @param array<string,string> $bindings
     * @return array<string,string>
     */
    private function bindings(array $bindings): array
    {
        if ($bindings === []) {
            throw new \InvalidArgumentException(
                'OPUS_RUNTIME_SECRET_BINDINGS_EMPTY'
            );
        }

        $normalized = [];
        foreach ($bindings as $environmentName => $alias) {
            $environmentName = trim((string) $environmentName);
            $alias = strtolower(trim((string) $alias));
            if (preg_match('/^[A-Z][A-Z0-9_]{2,127}$/', $environmentName) !== 1) {
                throw new \InvalidArgumentException(
                    'OPUS_RUNTIME_SECRET_ENVIRONMENT_INVALID'
                );
            }
            if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $alias) !== 1) {
                throw new \InvalidArgumentException(
                    'OPUS_RUNTIME_SECRET_ALIAS_INVALID'
                );
            }
            $normalized[$environmentName] = $alias;
        }

        return $normalized;
    }
}
