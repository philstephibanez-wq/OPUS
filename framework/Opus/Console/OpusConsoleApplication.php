<?php
declare(strict_types=1);

namespace Opus\Console;

use Opus\Console\Command\AddLanguageCommand;
use Opus\Console\Command\CreateModuleCommand;
use Opus\Console\Command\CreateSiteCommand;
use Opus\Console\Command\OpusConsoleCommandInterface;
use Opus\Console\Command\ServeSiteCommand;
use Opus\Console\Command\ValidateSiteCommand;

/**
 * OPUS console application.
 *
 * Public contract:
 * - Composer-facing entry point for OPUS generators and local tools;
 * - no external dependency;
 * - no implicit fallback;
 * - unknown commands fail explicitly;
 * - creation commands create scaffolds only;
 * - serve/validate commands operate on existing sites only.
 */
final class OpusConsoleApplication
{
    private string $opusRoot;

    /** @var array<string, OpusConsoleCommandInterface> */
    private array $commands = [];

    public function __construct(string $opusRoot)
    {
        $this->opusRoot = $opusRoot;

        $this->register(new CreateSiteCommand($opusRoot));
        $this->register(new CreateModuleCommand($opusRoot));
        $this->register(new AddLanguageCommand($opusRoot));
        $this->register(new ServeSiteCommand($opusRoot));
        $this->register(new ValidateSiteCommand($opusRoot));
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $arguments = $argv;
        array_shift($arguments);

        $commandName = (string)($arguments[0] ?? 'help');

        if ($commandName === 'help' || $commandName === '--help' || $commandName === '-h') {
            $this->printHelp();
            return 0;
        }

        array_shift($arguments);

        if (!isset($this->commands[$commandName])) {
            fwrite(STDERR, "OPUS_CONSOLE_UNKNOWN_COMMAND: {$commandName}\n");
            $this->printHelp();
            return 10;
        }

        try {
            return $this->commands[$commandName]->run(array_values($arguments));
        } catch (OpusConsoleException $exception) {
            fwrite(STDERR, $exception->getMessage() . "\n");
            return 20;
        }
    }

    private function register(OpusConsoleCommandInterface $command): void
    {
        $this->commands[$command->name()] = $command;
    }

    private function printHelp(): void
    {
        echo "OPUS Console\n";
        echo "\n";
        echo "Create commands:\n";
        echo "  create:site <site-id> [--dry-run|--write]\n";
        echo "  create:module <site-id> <ModuleName> [--dry-run|--write]\n";
        echo "  add:language <site-id> <locale> [--dry-run|--write]\n";
        echo "\n";
        echo "Inspect / local runtime commands:\n";
        echo "  validate:site <site-id>\n";
        echo "  serve:site <site-id> [--host 127.0.0.1] [--port 8791]\n";
        echo "\n";
        echo "Composer examples:\n";
        echo "  composer opus:create-site -- skeleton --write\n";
        echo "  composer opus:create-module -- skeleton Dashboard --write\n";
        echo "  composer opus:add-language -- skeleton en --write\n";
        echo "  composer opus:validate-site -- skeleton\n";
        echo "  composer opus:serve-site -- skeleton --port 8791\n";
    }
}
