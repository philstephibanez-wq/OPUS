<?php
declare(strict_types=1);

namespace Opus\Console\Command;

use Opus\Console\OpusConsoleException;
use Opus\Scaffold\ModuleScaffoldPlan;
use Opus\Scaffold\ScaffoldWriter;

/**
 * Creates an OPUS application module scaffold inside an existing site.
 */
final class CreateModuleCommand implements OpusConsoleCommandInterface
{
    public function __construct(private readonly string $opusRoot)
    {
    }

    public function name(): string
    {
        return 'create:module';
    }

    public function run(array $arguments): int
    {
        [$positionals, $write] = $this->parseArguments($arguments);

        $siteId = (string)($positionals[0] ?? '');
        $moduleName = (string)($positionals[1] ?? '');

        if ($siteId === '') {
            throw new OpusConsoleException('OPUS_CREATE_MODULE_MISSING_SITE_ID');
        }

        if ($moduleName === '') {
            throw new OpusConsoleException('OPUS_CREATE_MODULE_MISSING_MODULE_NAME');
        }

        if (!preg_match('/^[a-z][a-z0-9-]*$/', $siteId)) {
            throw new OpusConsoleException('OPUS_CREATE_MODULE_INVALID_SITE_ID: ' . $siteId);
        }

        if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $moduleName)) {
            throw new OpusConsoleException('OPUS_CREATE_MODULE_INVALID_MODULE_NAME: ' . $moduleName);
        }

        $writer = new ScaffoldWriter($this->opusRoot);
        $writer->assertDirectoryExists('sites/' . $siteId);
        $writer->assertDirectoryExists('sites/' . $siteId . '/application/modules');

        $plan = ModuleScaffoldPlan::forModule($siteId, $moduleName);
        $writer->assertPathDoesNotExist($plan->rootRelativePath());

        if (!$write) {
            echo "OPUS_CREATE_MODULE_DRY_RUN\n";
            $writer->renderPlan($plan);
            echo "Run again with --write to create the module.\n";
            return 0;
        }

        $writer->writePlan($plan);
        echo "OPUS_CREATE_MODULE_WRITTEN: {$siteId}/{$moduleName}\n";

        return 0;
    }

    /**
     * @param list<string> $arguments
     * @return array{0:list<string>,1:bool}
     */
    private function parseArguments(array $arguments): array
    {
        $write = false;
        $positionals = [];

        foreach ($arguments as $argument) {
            if ($argument === '--write') {
                $write = true;
                continue;
            }

            if ($argument === '--dry-run') {
                continue;
            }

            if (str_starts_with($argument, '--')) {
                throw new OpusConsoleException('OPUS_CREATE_MODULE_UNKNOWN_OPTION: ' . $argument);
            }

            $positionals[] = $argument;
        }

        return [$positionals, $write];
    }
}
