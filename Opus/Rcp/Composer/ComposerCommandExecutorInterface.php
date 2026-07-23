<?php
declare(strict_types=1);

namespace Opus\Rcp\Composer;

interface ComposerCommandExecutorInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /** @param array<string,mixed> $entry @param array<string,mixed> $request @return array<string,mixed> */
    public function execute(array $entry, array $request): array;
}
