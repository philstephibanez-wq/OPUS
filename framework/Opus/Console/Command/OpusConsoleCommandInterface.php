<?php
declare(strict_types=1);

namespace Opus\Console\Command;

/**
 * Contract for an OPUS console command.
 */
interface OpusConsoleCommandInterface
{
    public function name(): string;

    /**
     * @param list<string> $arguments
     */
    public function run(array $arguments): int;
}
