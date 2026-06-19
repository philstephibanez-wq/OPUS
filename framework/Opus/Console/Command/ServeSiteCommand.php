<?php
declare(strict_types=1);

namespace Opus\Console\Command;

use Opus\Console\OpusConsoleException;

/**
 * Serves an existing OPUS site through PHP built-in web server.
 *
 * Contract:
 * - never creates or patches a site;
 * - refuses missing or incomplete public document roots;
 * - never opens a browser automatically;
 * - prints the URL the user must test manually;
 * - keeps the current terminal attached to the PHP server until Ctrl+C.
 */
final class ServeSiteCommand implements OpusConsoleCommandInterface
{
    public function __construct(private readonly string $opusRoot)
    {
    }

    public function name(): string
    {
        return 'serve:site';
    }

    /**
     * @param list<string> $arguments
     */
    public function run(array $arguments): int
    {
        [$positionals, $options] = $this->parseArguments($arguments);

        $siteId = (string)($positionals[0] ?? '');
        if ($siteId === '') {
            throw new OpusConsoleException('OPUS_SERVE_SITE_MISSING_SITE_ID');
        }

        if (!preg_match('/^[a-z][a-z0-9-]*$/', $siteId)) {
            throw new OpusConsoleException('OPUS_SERVE_SITE_INVALID_SITE_ID: ' . $siteId);
        }

        $host = (string)($options['host'] ?? '127.0.0.1');
        $port = (string)($options['port'] ?? '8791');

        if (!preg_match('/^(?:127\.0\.0\.1|localhost)$/', $host)) {
            throw new OpusConsoleException('OPUS_SERVE_SITE_INVALID_HOST: ' . $host);
        }

        if (!preg_match('/^[0-9]{2,5}$/', $port) || (int)$port < 1024 || (int)$port > 65535) {
            throw new OpusConsoleException('OPUS_SERVE_SITE_INVALID_PORT: ' . $port);
        }

        $siteRoot = $this->absolutePath('sites/' . $siteId);
        $publicRoot = $siteRoot . DIRECTORY_SEPARATOR . 'public';
        $frontController = $publicRoot . DIRECTORY_SEPARATOR . 'index.php';

        if (!is_dir($siteRoot)) {
            throw new OpusConsoleException('OPUS_SERVE_SITE_NOT_FOUND: sites/' . $siteId);
        }

        if (!is_dir($publicRoot)) {
            throw new OpusConsoleException('OPUS_SERVE_SITE_PUBLIC_ROOT_MISSING: sites/' . $siteId . '/public');
        }

        if (!is_file($frontController)) {
            throw new OpusConsoleException('OPUS_SERVE_SITE_FRONT_CONTROLLER_MISSING: sites/' . $siteId . '/public/index.php');
        }

        $url = 'http://' . $host . ':' . $port . '/';

        echo "OPUS_SERVE_SITE\n";
        echo "Site: {$siteId}\n";
        echo "Document root: sites/{$siteId}/public\n";
        echo "Test URL: {$url}\n";
        echo "Press Ctrl+C to stop the PHP built-in server.\n";
        echo "\n";

        $command = escapeshellarg(PHP_BINARY) . ' -S ' . escapeshellarg($host . ':' . $port) . ' -t ' . escapeshellarg($publicRoot);
        passthru($command, $exitCode);

        return (int)$exitCode;
    }

    /**
     * @param list<string> $arguments
     * @return array{0:list<string>,1:array<string,string>}
     */
    private function parseArguments(array $arguments): array
    {
        $positionals = [];
        $options = [];

        for ($index = 0; $index < count($arguments); $index++) {
            $argument = (string)$arguments[$index];

            if ($argument === '--host') {
                $index++;
                $options['host'] = (string)($arguments[$index] ?? '');
                if ($options['host'] === '') {
                    throw new OpusConsoleException('OPUS_SERVE_SITE_HOST_VALUE_MISSING');
                }
                continue;
            }

            if (str_starts_with($argument, '--host=')) {
                $options['host'] = substr($argument, 7);
                continue;
            }

            if ($argument === '--port') {
                $index++;
                $options['port'] = (string)($arguments[$index] ?? '');
                if ($options['port'] === '') {
                    throw new OpusConsoleException('OPUS_SERVE_SITE_PORT_VALUE_MISSING');
                }
                continue;
            }

            if (str_starts_with($argument, '--port=')) {
                $options['port'] = substr($argument, 7);
                continue;
            }

            if (str_starts_with($argument, '--')) {
                throw new OpusConsoleException('OPUS_SERVE_SITE_UNKNOWN_OPTION: ' . $argument);
            }

            $positionals[] = $argument;
        }

        return [$positionals, $options];
    }

    private function absolutePath(string $relativePath): string
    {
        return $this->opusRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }
}
