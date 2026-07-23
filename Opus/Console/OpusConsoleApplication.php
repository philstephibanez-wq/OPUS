<?php
declare(strict_types=1);

namespace Opus\Console;

use Opus\Console\Application\ApplicationCommandDispatcher;
use Opus\Console\Application\ApplicationCommandDispatcherInterface;
use Opus\Console\Service\SiteArchiveExporter;
use Opus\Console\Service\SiteArchiveExporterInterface;
use Opus\Console\Service\SiteCommandService;
use Opus\Console\Service\SiteCommandServiceInterface;
use Opus\File\Json;

/** Public Composer-facing command application supplied by the OPUS framework. */
final class OpusConsoleApplication implements OpusConsoleApplicationInterface
{
    public function __construct(
        private readonly SiteCommandServiceInterface $sites,
        private readonly SiteArchiveExporterInterface $exporter,
        private readonly ApplicationCommandDispatcherInterface $applications
    ) {
    }

    public static function fromRoot(string $opusRoot): self
    {
        return new self(
            new SiteCommandService($opusRoot),
            new SiteArchiveExporter($opusRoot),
            ApplicationCommandDispatcher::fromRoot($opusRoot)
        );
    }

    public function run(array $argv): int
    {
        $arguments = $argv;
        array_shift($arguments);
        $command = trim((string) array_shift($arguments));

        if ($command === '' || in_array($command, ['help', '--help', '-h'], true)) {
            $this->help();
            return 0;
        }

        $format = $this->option($arguments, 'format', 'text');
        $arguments = $this->withoutOption($arguments, 'format');
        $request = $this->stdinRequest();

        try {
            $result = match ($command) {
                'create:application', 'create:site' => $this->create(
                    $arguments,
                    $request
                ),
                'export:site' => $this->export($arguments, $request),
                'add:language' => $this->addLanguage($arguments, $request),
                'validate:site' => $this->validate($arguments, $request),
                'list:routes' => $this->listRoutes($arguments, $request),
                'create:page' => $this->createPage($arguments, $request),
                'create:rubric' => $this->createRubric($arguments, $request),
                'serve:site' => $this->serve($arguments, $request),
                default => $this->applicationCommand(
                    $command,
                    $arguments,
                    $request
                ),
            };

            if (is_int($result)) {
                return $result;
            }

            $this->output($result, $format);
            return 0;
        } catch (\Throwable $error) {
            $payload = [
                'contract' => 'OPUS_CONSOLE_ERROR_V1',
                'status' => 'failed',
                'error_code' => $this->safeErrorCode($error),
            ];
            if ($format === 'json') {
                fwrite(
                    STDOUT,
                    Json::instance()->encode($payload, false) . PHP_EOL
                );
            } else {
                fwrite(STDERR, $payload['error_code'] . PHP_EOL);
            }
            return 20;
        }
    }

    /** @param list<string> $arguments @param array<string,mixed> $request */
    private function applicationCommand(
        string $command,
        array $arguments,
        array $request
    ): array {
        if (!$this->applications->supports($command)) {
            throw new OpusConsoleException(
                'OPUS_CONSOLE_UNKNOWN_COMMAND:' . $command
            );
        }

        return $this->applications->execute(
            $command,
            $this->positionals($arguments),
            $request
        );
    }

    /** @return array<string,mixed> */
    private function stdinRequest(): array
    {
        if (function_exists('stream_isatty') && stream_isatty(STDIN)) {
            return [];
        }
        $raw = stream_get_contents(STDIN);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        return Json::instance()->parse($raw, 'php://stdin');
    }

    /** @param list<string> $arguments @param array<string,mixed> $request */
    private function create(array $arguments, array $request): array
    {
        $this->assertRcpActor($request, ['admin', 'developer']);
        $siteId = (string) ($this->positionals($arguments)[0] ?? '');
        if ($siteId === '') {
            throw new OpusConsoleException('OPUS_CREATE_SITE_ID_REQUIRED');
        }
        return $this->sites->create(
            $siteId,
            $this->flag($arguments, 'write')
        );
    }

    /** @param list<string> $arguments @param array<string,mixed> $request */
    private function export(array $arguments, array $request): array
    {
        $this->assertRcpActor($request, ['admin', 'developer']);
        $positionals = $this->positionals($arguments);
        $siteId = (string) ($positionals[0] ?? '');
        if ($siteId === '') {
            throw new OpusConsoleException('OPUS_EXPORT_SITE_ID_REQUIRED');
        }
        return $this->exporter->export(
            $siteId,
            (string) ($positionals[1] ?? ''),
            $this->flag($arguments, 'overwrite')
        );
    }

    /** @param list<string> $arguments @param array<string,mixed> $request */
    private function addLanguage(array $arguments, array $request): array
    {
        $this->assertRcpActor($request, ['admin', 'developer']);
        $positionals = $this->positionals($arguments);
        if (($positionals[0] ?? '') === '' || ($positionals[1] ?? '') === '') {
            throw new OpusConsoleException(
                'OPUS_ADD_LANGUAGE_ARGUMENTS_REQUIRED'
            );
        }
        return $this->sites->addLanguage(
            (string) $positionals[0],
            (string) $positionals[1],
            $this->flag($arguments, 'write')
        );
    }

    /** @param list<string> $arguments @param array<string,mixed> $request */
    private function validate(array $arguments, array $request): array
    {
        $this->assertRcpActor($request, ['admin', 'developer', 'viewer']);
        $siteId = (string) ($this->positionals($arguments)[0] ?? '');
        if ($siteId === '') {
            throw new OpusConsoleException('OPUS_VALIDATE_SITE_ID_REQUIRED');
        }
        return $this->sites->validate($siteId);
    }

    /** @param list<string> $arguments @param array<string,mixed> $request */
    private function listRoutes(array $arguments, array $request): array
    {
        $this->assertRcpActor($request, ['admin', 'developer', 'viewer']);
        $siteId = (string) ($this->positionals($arguments)[0] ?? '');
        if ($siteId === '') {
            throw new OpusConsoleException('OPUS_LIST_ROUTES_SITE_ID_REQUIRED');
        }
        return $this->sites->listRoutes($siteId);
    }

    /** @param list<string> $arguments @param array<string,mixed> $request */
    private function createPage(array $arguments, array $request): array
    {
        $this->assertRcpActor($request, ['admin', 'developer']);
        $positionals = $this->positionals($arguments);
        if (count($positionals) < 4) {
            throw new OpusConsoleException(
                'OPUS_CREATE_PAGE_ARGUMENTS_REQUIRED'
            );
        }
        return $this->sites->createPage(
            (string) $positionals[0],
            (string) $positionals[1],
            (string) $positionals[2],
            (string) $positionals[3],
            $this->option($arguments, 'title', ''),
            $this->flag($arguments, 'write')
        );
    }

    /** @param list<string> $arguments @param array<string,mixed> $request */
    private function createRubric(array $arguments, array $request): array
    {
        $this->assertRcpActor($request, ['admin', 'developer']);
        $positionals = $this->positionals($arguments);
        if (count($positionals) < 3) {
            throw new OpusConsoleException(
                'OPUS_CREATE_RUBRIC_ARGUMENTS_REQUIRED'
            );
        }
        return $this->sites->createRubric(
            (string) $positionals[0],
            (string) $positionals[1],
            (string) $positionals[2],
            $this->option($arguments, 'title', ''),
            $this->flag($arguments, 'write')
        );
    }

    /** @param list<string> $arguments @param array<string,mixed> $request */
    private function serve(array $arguments, array $request): int
    {
        $this->assertRcpActor($request, ['admin', 'developer']);
        $siteId = (string) ($this->positionals($arguments)[0] ?? '');
        if ($siteId === '') {
            throw new OpusConsoleException('OPUS_SERVE_SITE_ID_REQUIRED');
        }
        $host = $this->option($arguments, 'host', '127.0.0.1');
        $portRaw = $this->option($arguments, 'port', '8791');
        if (preg_match('/^[0-9]+$/', $portRaw) !== 1) {
            throw new OpusConsoleException('OPUS_SERVE_PORT_INVALID');
        }
        fwrite(
            STDOUT,
            'OPUS_SERVE_URL:http://' . $host . ':' . $portRaw . '/' . PHP_EOL
        );
        return $this->sites->serve($siteId, $host, (int) $portRaw);
    }

    /** @param array<string,mixed> $request @param list<string> $allowedRoles */
    private function assertRcpActor(array $request, array $allowedRoles): void
    {
        if ($request === []) {
            return;
        }
        if (($request['contract'] ?? null)
            !== 'OPUS_RCP_COMPOSER_COMMAND_REQUEST_V1') {
            throw new OpusConsoleException('OPUS_RCP_COMMAND_REQUEST_INVALID');
        }
        $actor = is_array($request['actor'] ?? null)
            ? $request['actor']
            : [];
        $subject = trim((string) ($actor['subject'] ?? ''));
        $roles = is_array($actor['roles'] ?? null)
            ? array_values(array_unique(array_filter(
                $actor['roles'],
                'is_string'
            )))
            : [];
        $provider = trim((string) ($actor['provider'] ?? ''));
        if ($subject === ''
            || $roles === []
            || $provider === ''
            || array_intersect($roles, $allowedRoles) === []) {
            throw new OpusConsoleException('OPUS_RCP_COMMAND_ACL_DENIED');
        }
    }

    /** @param array<string,mixed> $payload */
    private function output(array $payload, string $format): void
    {
        if ($format === 'json') {
            fwrite(STDOUT, Json::instance()->encode([
                'contract' => 'OPUS_CONSOLE_COMMAND_RESULT_V1',
                'status' => 'succeeded',
                'data' => $payload,
            ], false) . PHP_EOL);
            return;
        }
        fwrite(STDOUT, Json::instance()->encode($payload, true) . PHP_EOL);
    }

    /** @param list<string> $arguments */
    private function flag(array $arguments, string $name): bool
    {
        return in_array('--' . $name, $arguments, true)
            || $this->option($arguments, $name, '0') === '1';
    }

    /** @param list<string> $arguments */
    private function option(
        array $arguments,
        string $name,
        string $default
    ): string {
        $prefix = '--' . $name . '=';
        foreach ($arguments as $index => $argument) {
            if (str_starts_with($argument, $prefix)) {
                return substr($argument, strlen($prefix));
            }
            if ($argument === '--' . $name && isset($arguments[$index + 1])) {
                return (string) $arguments[$index + 1];
            }
        }
        return $default;
    }

    /** @param list<string> $arguments @return list<string> */
    private function withoutOption(array $arguments, string $name): array
    {
        $result = [];
        $skip = false;
        foreach ($arguments as $argument) {
            if ($skip) {
                $skip = false;
                continue;
            }
            if ($argument === '--' . $name) {
                $skip = true;
                continue;
            }
            if (str_starts_with($argument, '--' . $name . '=')) {
                continue;
            }
            $result[] = $argument;
        }
        return $result;
    }

    /** @param list<string> $arguments @return list<string> */
    private function positionals(array $arguments): array
    {
        $result = [];
        $skip = false;
        foreach ($arguments as $argument) {
            if ($skip) {
                $skip = false;
                continue;
            }
            if (in_array($argument, ['--title', '--host', '--port'], true)) {
                $skip = true;
                continue;
            }
            if (str_starts_with($argument, '--')) {
                continue;
            }
            $result[] = $argument;
        }
        return $result;
    }

    private function safeErrorCode(\Throwable $error): string
    {
        $message = trim($error->getMessage());
        return preg_match('/^[A-Z0-9_:-]{3,240}$/', $message) === 1
            ? $message
            : 'OPUS_CONSOLE_COMMAND_FAILED';
    }

    private function help(): void
    {
        $lines = [
            'OPUS Console',
            'composer opus:create-application -- <id> [--write]',
            'composer opus:create-site -- <id> [--write]',
            'composer opus:add-language -- <site> <locale> [--write]',
            'composer opus:validate-site -- <site>',
            'composer opus:list-routes -- <site>',
            'composer opus:create-page -- <site> <module> <page> <path> [--title=...] [--write]',
            'composer opus:create-rubric -- <site> <module> <path> [--title=...] [--write]',
            'composer opus:export-site -- <site> [output.zip] [--overwrite]',
            'composer opus:serve-site -- <site> [--host=127.0.0.1] [--port=8791]',
            'Application commands are discovered below sites/*/config/composer.commands.json.',
        ];
        fwrite(STDOUT, implode(PHP_EOL, $lines) . PHP_EOL);
    }
}
