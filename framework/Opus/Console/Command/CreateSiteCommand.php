<?php
declare(strict_types=1);

namespace Opus\Console\Command;

use Opus\Console\OpusConsoleException;
use Opus\Scaffold\ScaffoldWriter;
use Opus\Scaffold\SiteScaffoldPlan;

/**
 * Creates a full OPUS site/application scaffold.
 *
 * Contract:
 * - dry-run is side-effect free;
 * - write is explicit;
 * - optional dev server is explicit with --serve;
 * - no browser is opened automatically;
 * - no external dependency is used.
 */
final class CreateSiteCommand implements OpusConsoleCommandInterface
{
    public function __construct(private readonly string $opusRoot)
    {
    }

    public function name(): string
    {
        return 'create:site';
    }

    public function run(array $arguments): int
    {
        [$positionals, $write, $serve, $port] = $this->parseArguments($arguments);

        $siteId = (string)($positionals[0] ?? '');
        if ($siteId === '') {
            throw new OpusConsoleException('OPUS_CREATE_SITE_MISSING_SITE_ID');
        }

        if (!preg_match('/^[a-z][a-z0-9-]*$/', $siteId)) {
            throw new OpusConsoleException('OPUS_CREATE_SITE_INVALID_SITE_ID: ' . $siteId);
        }

        $plan = SiteScaffoldPlan::forSite($siteId);
        $writer = new ScaffoldWriter($this->opusRoot);
        $writer->assertPathDoesNotExist($plan->rootRelativePath());

        if (!$write) {
            echo "OPUS_CREATE_SITE_DRY_RUN\n";
            $writer->renderPlan($plan);
            echo "Run again with --write to create the site.\n";
            if ($serve) {
                echo "Dev server requested but not started during dry-run. Run again with --write --serve.\n";
            }
            return 0;
        }

        $writer->writePlan($plan);
        echo "OPUS_CREATE_SITE_WRITTEN: {$siteId}\n";

        if ($serve) {
            return $this->serveSite($siteId, $port);
        }

        echo "Dev URL: http://127.0.0.1:{$port}/\n";
        echo "Run with --serve to start PHP built-in server automatically.\n";

        return 0;
    }

    /**
     * @param list<string> $arguments
     * @return array{0:list<string>,1:bool,2:bool,3:int}
     */
    private function parseArguments(array $arguments): array
    {
        $write = false;
        $serve = false;
        $port = 8791;
        $positionals = [];
        $consumePort = false;

        foreach ($arguments as $argument) {
            if ($consumePort) {
                $port = $this->parsePort($argument);
                $consumePort = false;
                continue;
            }

            if ($argument === '--write') {
                $write = true;
                continue;
            }

            if ($argument === '--dry-run') {
                continue;
            }

            if ($argument === '--serve') {
                $serve = true;
                continue;
            }

            if ($argument === '--port') {
                $consumePort = true;
                continue;
            }

            if (str_starts_with($argument, '--port=')) {
                $port = $this->parsePort(substr($argument, 7));
                continue;
            }

            if (str_starts_with($argument, '--')) {
                throw new OpusConsoleException('OPUS_CREATE_SITE_UNKNOWN_OPTION: ' . $argument);
            }

            $positionals[] = $argument;
        }

        if ($consumePort) {
            throw new OpusConsoleException('OPUS_CREATE_SITE_PORT_VALUE_MISSING');
        }

        return [$positionals, $write, $serve, $port];
    }

    private function parsePort(string $port): int
    {
        if (!preg_match('/^[0-9]+$/', $port)) {
            throw new OpusConsoleException('OPUS_CREATE_SITE_INVALID_PORT: ' . $port);
        }

        $value = (int)$port;
        if ($value < 1024 || $value > 65535) {
            throw new OpusConsoleException('OPUS_CREATE_SITE_PORT_OUT_OF_RANGE: ' . $port);
        }

        return $value;
    }

    private function serveSite(string $siteId, int $port): int
    {
        $documentRoot = $this->opusRoot . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . $siteId . DIRECTORY_SEPARATOR . 'public';
        if (!is_dir($documentRoot)) {
            throw new OpusConsoleException('OPUS_CREATE_SITE_PUBLIC_ROOT_MISSING: sites/' . $siteId . '/public');
        }

        echo "OPUS_CREATE_SITE_DEV_SERVER\n";
        echo "URL: http://127.0.0.1:{$port}/\n";
        echo "DocumentRoot: sites/{$siteId}/public\n";
        echo "Press Ctrl+C to stop the PHP development server.\n";

        $command = escapeshellarg(PHP_BINARY)
            . ' -S 127.0.0.1:' . $port
            . ' -t ' . escapeshellarg($documentRoot);

        passthru($command, $exitCode);

        return (int)$exitCode;
    }
}
