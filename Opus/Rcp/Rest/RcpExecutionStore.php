<?php
declare(strict_types=1);

namespace Opus\Rcp\Rest;

use Opus\File\File;
use Opus\File\StructuredFileLoader;

/** File-backed idempotency and execution-result store. */
final class RcpExecutionStore implements RcpExecutionStoreInterface
{
    private readonly string $root;
    private readonly File $file;
    private readonly StructuredFileLoader $loader;

    public function __construct(string $root)
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if ($root === '' || str_contains($root, "\0")) {
            throw new \RuntimeException('OPUS_RCP_EXECUTION_STORE_ROOT_INVALID');
        }
        $this->root = $root;
        $this->file = File::instance();
        $this->loader = StructuredFileLoader::instance();
    }

    public function exists(string $executionId): bool
    {
        return $this->file->exists($this->path($executionId));
    }

    public function write(string $executionId, array $record): void
    {
        $this->loader->writeJson($this->path($executionId), $record);
    }

    public function read(string $executionId): array
    {
        return $this->loader->read($this->path($executionId));
    }

    private function path(string $executionId): string
    {
        if (preg_match('/^[a-f0-9]{32}$/', $executionId) !== 1) {
            throw new \RuntimeException('OPUS_RCP_EXECUTION_ID_INVALID');
        }
        return $this->root . '/' . $executionId . '.json';
    }
}
