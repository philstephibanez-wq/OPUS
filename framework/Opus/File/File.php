<?php
declare(strict_types=1);
namespace Opus\File;
/*
 * OPUS_REFBOOK:
 *   domain: FILE
 *   role: Class File belongs to the FILE Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the FILE domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - file-overview
 *   diagrams:
 *     - file-runtime
 * END_OPUS_REFBOOK
 */
/**
 * Legacy-aligned Opus File domain.
 * No silent fallback. Single responsibility only.
 * Since P112D4D_SAFE.
 */
final class File
{
public function __construct(public readonly string $path) { if (!is_file($this->path)) { throw new \RuntimeException('OPUS_FILE_MISSING: ' . $this->path); } }
public function read(): string { return (string) file_get_contents($this->path); }
}
