<?php
declare(strict_types=1);

namespace Opus\Console;

use Opus\Console\Command\AddLanguageCommand;
use Opus\Console\Command\CreateApplicationCommand;
use Opus\Console\Command\CreateModuleCommand;
use Opus\Console\Command\CreateSiteCommand;
use Opus\Console\Command\ListModulesCommand;
use Opus\Console\Command\ListRoutesCommand;
use Opus\Console\Command\OpusConsoleCommandInterface;
use Opus\Console\Command\ServeSiteCommand;
use Opus\Console\Command\ValidateSiteCommand;
use Opus\Console\Command\CreateRubricCommand;
use Opus\Console\Command\CreatePageCommand;

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

        $this->register(new CreateApplicationCommand($opusRoot));
        $this->register(new CreateSiteCommand($opusRoot));
        $this->register(new CreateModuleCommand($opusRoot));
        $this->register(new AddLanguageCommand($opusRoot));
        $this->register(new ServeSiteCommand($opusRoot));
        $this->register(new ValidateSiteCommand($opusRoot));
        $this->register(new ListRoutesCommand($opusRoot));
        $this->register(new ListModulesCommand($opusRoot));
        $this->register(new CreateRubricCommand($opusRoot));
        $this->register(new CreatePageCommand($opusRoot));
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
        echo "  create:application <application-id> [--dry-run|--write] [--serve] [--port 8791]\n";
        echo "  create:site <site-id> [--dry-run|--write]\n";
        echo "  create:module <site-id> <ModuleName> [--dry-run|--write]\n";
        echo "  add:language <site-id> <locale> [--dry-run|--write]\n";
        echo "\n";
        echo "Inspect / local runtime commands:\n";
        echo "  validate:site <site-id>\n";
        echo "  create:module <site-id> <ModuleId> [--title <title>] --write\n";
        echo "  create:page <site-id> <ModuleId> <page-id> <path> [--title <title>] --write\n";
        echo "  create:rubric <site-id> <ModuleId> <path> [--title <title>] --write\n";
        echo "  list:routes <site-id>\n";
        echo "  list:modules <site-id>\n";
        echo "  serve:site <site-id> [--host 127.0.0.1] [--port 8791]\n";
        echo "\n";
        echo "Composer examples:\n";
        echo "  composer opus:create-application -- skeleton --write\n";
        echo "  composer opus:create-site -- skeleton --write\n";
        echo "  composer opus:create-module -- skeleton Dashboard --write\n";
        echo "  composer opus:add-language -- skeleton en --write\n";
        echo "  composer opus:validate-site -- skeleton\n";
        echo "  composer opus:list-routes -- skeleton\n";
        echo "  composer opus:list-modules -- skeleton\n";
        echo "  composer opus:create-module -- skeleton Blog --title Blog --write\n";
        echo "  composer opus:create-page -- skeleton Blog archive /blog/archive --title Blog archive --write\n";
        echo "  composer opus:create-rubric -- skeleton News /news --title News --write\n";
        echo "  composer opus:serve-site -- skeleton --port 8791\n";
    }
}
