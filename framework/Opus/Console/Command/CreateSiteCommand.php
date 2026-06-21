<?php
declare(strict_types=1);

namespace Opus\Console\Command;

/**
 * Backward-compatible alias for creating a fullstack OPUS application scaffold.
 *
 * Public contract:
 * - create:site is an alias of create:application;
 * - site remains a public/user-facing application type, not a legacy folder model;
 * - generated applications keep explicit frontend/backend separation;
 * - no divergent scaffold path is allowed here.
 */
final class CreateSiteCommand implements OpusConsoleCommandInterface
{
    private CreateApplicationCommand $createApplicationCommand;

    public function __construct(string $opusRoot)
    {
        $this->createApplicationCommand = new CreateApplicationCommand($opusRoot);
    }

    public function name(): string
    {
        return 'create:site';
    }

    /**
     * @param list<string> $arguments
     */
    public function run(array $arguments): int
    {
        return $this->createApplicationCommand->run($arguments);
    }
}
