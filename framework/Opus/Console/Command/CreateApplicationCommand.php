<?php
declare(strict_types=1);

namespace Opus\Console\Command;

use Opus\Console\OpusConsoleException;
use Opus\Scaffold\FullstackApplicationScaffoldPlan;
use Opus\Scaffold\ScaffoldWriter;

/**
 * Creates a fullstack OPUS application scaffold with explicit frontend/backend separation.
 *
 * Public contract:
 * - dry-run is side-effect free;
 * - write is explicit;
 * - generated applications are fullstack;
 * - frontend owns representation only;
 * - backend owns business/data processing only;
 * - no external dependency is used.
 */
final class CreateApplicationCommand implements OpusConsoleCommandInterface
{
    public function __construct(private readonly string $opusRoot)
    {
    }

    public function name(): string
    {
        return 'create:application';
    }

    /**
     * @param list<string> $arguments
     */
    public function run(array $arguments): int
    {
        [$positionals, $write, $serve, $port] = $this->parseArguments($arguments);

        $applicationId = (string)($positionals[0] ?? '');
        if ($applicationId === '') {
            throw new OpusConsoleException('OPUS_CREATE_APPLICATION_MISSING_APPLICATION_ID');
        }

        if (!preg_match('/^[a-z][a-z0-9-]*$/', $applicationId)) {
            throw new OpusConsoleException('OPUS_CREATE_APPLICATION_INVALID_APPLICATION_ID: ' . $applicationId);
        }

        $plan = FullstackApplicationScaffoldPlan::forApplication($applicationId);
        $writer = new ScaffoldWriter($this->opusRoot);
        $writer->assertPathDoesNotExist($plan->rootRelativePath());

        if (!$write) {
            echo "OPUS_CREATE_APPLICATION_DRY_RUN\n";
            $writer->renderPlan($plan);
            echo "Run again with --write to create the application.\n";
            if ($serve) {
                echo "Dev server requested but not started during dry-run. Run again with --write --serve.\n";
            }
            return 0;
        }

        $writer->writePlan($plan);
        echo "OPUS_CREATE_APPLICATION_WRITTEN: {$applicationId}\n";

        if ($serve) {
            return $this->serveApplication($applicationId, $port);
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
                throw new OpusConsoleException('OPUS_CREATE_APPLICATION_UNKNOWN_OPTION: ' . $argument);
            }

            $positionals[] = $argument;
        }

        if ($consumePort) {
            throw new OpusConsoleException('OPUS_CREATE_APPLICATION_PORT_VALUE_MISSING');
        }

        return [$positionals, $write, $serve, $port];
    }

    private function parsePort(string $port): int
    {
        if (!preg_match('/^[0-9]+$/', $port)) {
            throw new OpusConsoleException('OPUS_CREATE_APPLICATION_INVALID_PORT: ' . $port);
        }

        $value = (int)$port;
        if ($value < 1024 || $value > 65535) {
            throw new OpusConsoleException('OPUS_CREATE_APPLICATION_PORT_OUT_OF_RANGE: ' . $port);
        }

        return $value;
    }

    private function serveApplication(string $applicationId, int $port): int
    {
        $documentRoot = $this->opusRoot . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . $applicationId . DIRECTORY_SEPARATOR . 'public';
        if (!is_dir($documentRoot)) {
            throw new OpusConsoleException('OPUS_CREATE_APPLICATION_PUBLIC_ROOT_MISSING: sites/' . $applicationId . '/public');
        }

        echo "OPUS_CREATE_APPLICATION_DEV_SERVER\n";
        echo "URL: http://127.0.0.1:{$port}/\n";
        echo "DocumentRoot: sites/{$applicationId}/public\n";
        echo "Press Ctrl+C to stop the PHP development server.\n";

        $command = escapeshellarg(PHP_BINARY)
            . ' -S 127.0.0.1:' . $port
            . ' -t ' . escapeshellarg($documentRoot);

        passthru($command, $exitCode);

        return (int)$exitCode;
    }
}
