<?php
declare(strict_types=1);

namespace Opus\Console\Development;

use Opus\File\File;
use Opus\File\StructuredFileLoader;

/** Runtime-only registry for OPUS local development servers. */
final class DevelopmentServerRegistry implements DevelopmentServerRegistryInterface
{
    public const CONTRACT = 'OPUS_DEVELOPMENT_SERVER_REGISTRY_V1';

    private readonly File $file;
    private readonly StructuredFileLoader $loader;

    private function __construct(private readonly string $registryFile)
    {
        $this->file = File::instance();
        $this->loader = StructuredFileLoader::instance();
    }

    public static function forFile(string $registryFile): self
    {
        $registryFile = trim(str_replace('\\', '/', $registryFile));
        if ($registryFile === '' || str_contains($registryFile, "\0")) {
            throw new \InvalidArgumentException(
                'OPUS_DEV_SERVER_REGISTRY_PATH_INVALID'
            );
        }
        return new self($registryFile);
    }

    public function register(string $applicationId, string $host, int $port): array
    {
        $applicationId = $this->applicationId($applicationId);
        $host = $this->host($host);
        $this->port($port);
        return $this->withLock(function () use (
            $applicationId,
            $host,
            $port
        ): array {
            $registry = $this->read();
            $registry['applications'][$applicationId] = [
                'application_id' => $applicationId,
                'host' => $host,
                'port' => $port,
                'base_url' => $this->url($host, $port),
                'registered_at_utc' => gmdate('c'),
                'pid' => getmypid(),
            ];
            ksort($registry['applications'], SORT_STRING);
            $this->loader->writeJson($this->registryFile, $registry);
            return $registry['applications'][$applicationId];
        });
    }

    public function unregister(string $applicationId): void
    {
        $applicationId = $this->applicationId($applicationId);
        $this->withLock(function () use ($applicationId): void {
            if (!$this->file->exists($this->registryFile)) {
                return;
            }
            $registry = $this->read();
            unset($registry['applications'][$applicationId]);
            $this->loader->writeJson($this->registryFile, $registry);
        });
    }

    public function endpoint(string $applicationId, string $path): string
    {
        $applicationId = $this->applicationId($applicationId);
        $path = '/' . ltrim(trim($path), '/');
        if (preg_match('#^/[A-Za-z0-9/_-]+$#', $path) !== 1
            || str_contains($path, '..')) {
            throw new \InvalidArgumentException(
                'OPUS_DEV_SERVER_PEER_PATH_INVALID'
            );
        }
        $registry = $this->read();
        $entry = $registry['applications'][$applicationId] ?? null;
        if (!is_array($entry)) {
            throw new \RuntimeException(
                'OPUS_DEV_SERVER_PEER_NOT_REGISTERED:' . $applicationId
            );
        }
        $base = trim((string) ($entry['base_url'] ?? ''));
        if ($base === '') {
            throw new \RuntimeException(
                'OPUS_DEV_SERVER_PEER_ENTRY_INVALID:' . $applicationId
            );
        }
        return rtrim($base, '/') . $path;
    }

    /** @template T @param callable():T $callback @return T */
    private function withLock(callable $callback): mixed
    {
        $directory = dirname($this->registryFile);
        if (!is_dir($directory)
            && !mkdir($directory, 0775, true)
            && !is_dir($directory)) {
            throw new \RuntimeException(
                'OPUS_DEV_SERVER_REGISTRY_DIRECTORY_CREATE_FAILED'
            );
        }
        $stream = fopen($this->registryFile . '.lock', 'c+b');
        if ($stream === false) {
            throw new \RuntimeException(
                'OPUS_DEV_SERVER_REGISTRY_LOCK_OPEN_FAILED'
            );
        }
        try {
            if (!flock($stream, LOCK_EX)) {
                throw new \RuntimeException(
                    'OPUS_DEV_SERVER_REGISTRY_LOCK_FAILED'
                );
            }
            return $callback();
        } finally {
            @flock($stream, LOCK_UN);
            fclose($stream);
        }
    }

    /** @return array{contract:string,applications:array<string,array<string,mixed>>} */
    private function read(): array
    {
        if (!$this->file->exists($this->registryFile)) {
            return [
                'contract' => self::CONTRACT,
                'applications' => [],
            ];
        }
        $registry = $this->loader->read($this->registryFile);
        if (($registry['contract'] ?? null) !== self::CONTRACT
            || !is_array($registry['applications'] ?? null)) {
            throw new \RuntimeException(
                'OPUS_DEV_SERVER_REGISTRY_CONTRACT_INVALID'
            );
        }
        return [
            'contract' => self::CONTRACT,
            'applications' => array_filter(
                $registry['applications'],
                'is_array'
            ),
        ];
    }

    private function applicationId(string $applicationId): string
    {
        $applicationId = strtolower(trim($applicationId));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/', $applicationId) !== 1) {
            throw new \InvalidArgumentException(
                'OPUS_DEV_SERVER_APPLICATION_ID_INVALID'
            );
        }
        return $applicationId;
    }

    private function host(string $host): string
    {
        $host = strtolower(trim($host));
        if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new \InvalidArgumentException(
                'OPUS_DEV_SERVER_HOST_NOT_LOCAL:' . $host
            );
        }
        return $host;
    }

    private function port(int $port): void
    {
        if ($port < 1024 || $port > 65535) {
            throw new \InvalidArgumentException(
                'OPUS_DEV_SERVER_PORT_INVALID'
            );
        }
    }

    private function url(string $host, int $port): string
    {
        $authority = $host === '::1' ? '[::1]' : $host;
        return 'http://' . $authority . ':' . $port;
    }
}
