<?php
declare(strict_types=1);
namespace ASAP\FILE;
/**
 * Legacy-aligned ASAP File domain.
 * No silent fallback. Single responsibility only.
 * Since P112D4D_SAFE.
 */
final class File
{
public function __construct(public readonly string $path) { if (!is_file($this->path)) { throw new \RuntimeException('ASAP_FILE_MISSING: ' . $this->path); } }
public function read(): string { return (string) file_get_contents($this->path); }
}
