<?php
declare(strict_types=1);
namespace ASAP\DIRECTORY;
/**
 * Legacy-aligned ASAP Directory domain.
 * No silent fallback. Single responsibility only.
 * Since P112D4D_SAFE.
 */
final class Directory
{
public function __construct(public readonly string $path) { if (!is_dir($this->path)) { throw new \RuntimeException('ASAP_DIRECTORY_MISSING: ' . $this->path); } }
public function files(string $pattern = '*'): array { $files = glob(rtrim($this->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $pattern); return is_array($files) ? $files : []; }
}
