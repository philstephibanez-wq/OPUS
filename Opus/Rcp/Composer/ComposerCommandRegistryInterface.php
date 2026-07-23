<?php
declare(strict_types=1);

namespace Opus\Rcp\Composer;

interface ComposerCommandRegistryInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /** @return array<string,mixed> */
    public function operation(string $operation): array;
    /** @return list<array<string,mixed>> */
    public function publicOperations(): array;
    /** @param array<string,mixed> $parameters @return list<string> */
    public function arguments(array $entry, array $parameters): array;
}
