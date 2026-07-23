<?php
declare(strict_types=1);

namespace Opus\Console;

interface OpusConsoleApplicationInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /** @param list<string> $argv */
    public function run(array $argv): int;
}
