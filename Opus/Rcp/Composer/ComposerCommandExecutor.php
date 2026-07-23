<?php
declare(strict_types=1);

namespace Opus\Rcp\Composer;

use Opus\File\Json;

/** Executes one allow-listed public Composer script and returns its JSON result. */
final class ComposerCommandExecutor implements ComposerCommandExecutorInterface
{
    /** @param list<string> $composerCommand */
    public function __construct(
        private readonly string $opusRoot,
        private readonly array $composerCommand,
        private readonly int $timeoutSeconds,
        private readonly int $maxOutputBytes
    ) {
        if ($this->composerCommand === []
            || array_filter($this->composerCommand, 'is_string') !== $this->composerCommand) {
            throw new \RuntimeException('OPUS_RCP_COMPOSER_COMMAND_INVALID');
        }
        if ($this->timeoutSeconds < 1 || $this->timeoutSeconds > 600) {
            throw new \RuntimeException('OPUS_RCP_TIMEOUT_INVALID');
        }
        if ($this->maxOutputBytes < 4096 || $this->maxOutputBytes > 16777216) {
            throw new \RuntimeException('OPUS_RCP_OUTPUT_LIMIT_INVALID');
        }
    }

    public function execute(array $entry, array $request): array
    {
        $script = trim((string) ($entry['composer_script'] ?? ''));
        $argv = is_array($entry['argv'] ?? null)
            ? array_values(array_filter($entry['argv'], 'is_string'))
            : [];
        if ($script === '' || preg_match('/^[a-z0-9][a-z0-9:_-]*$/', $script) !== 1) {
            throw new \RuntimeException('OPUS_RCP_COMPOSER_SCRIPT_INVALID');
        }

        $command = [
            ...$this->composerCommand,
            '--no-interaction',
            '--no-plugins',
            '--no-ansi',
            $script,
            '--',
            ...$argv,
            '--format=json',
        ];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            $this->opusRoot,
            null,
            ['bypass_shell' => true, 'suppress_errors' => true]
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('OPUS_RCP_COMPOSER_PROCESS_START_FAILED');
        }

        $encoded = Json::instance()->encode($request, false);
        try {
            if (fwrite($pipes[0], $encoded) === false) {
                throw new \RuntimeException('OPUS_RCP_STDIN_WRITE_FAILED');
            }
            fclose($pipes[0]);
            unset($encoded);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            [$stdout, $stderr, $exitCode] = $this->collect(
                $process,
                $pipes[1],
                $pipes[2]
            );
        } catch (\Throwable $error) {
            @proc_terminate($process);
            throw $error;
        } finally {
            foreach ([1, 2] as $index) {
                if (isset($pipes[$index]) && is_resource($pipes[$index])) {
                    fclose($pipes[$index]);
                }
            }
            if (is_resource($process)) {
                @proc_close($process);
            }
            unset($request);
        }

        $result = $this->parseResult($stdout, $stderr, $exitCode);
        unset($stdout, $stderr);
        if ($exitCode !== 0 || ($result['status'] ?? null) !== 'succeeded') {
            $code = trim((string) ($result['error_code'] ?? 'OPUS_RCP_COMPOSER_COMMAND_FAILED'));
            throw new \RuntimeException(
                preg_match('/^[A-Z0-9_:-]{3,240}$/', $code) === 1
                    ? $code
                    : 'OPUS_RCP_COMPOSER_COMMAND_FAILED'
            );
        }

        return $result;
    }

    /** @return array{0:string,1:string,2:int} */
    private function collect($process, $stdoutPipe, $stderrPipe): array
    {
        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + $this->timeoutSeconds;
        $exitCode = -1;

        while (true) {
            $out = stream_get_contents($stdoutPipe);
            $err = stream_get_contents($stderrPipe);
            $stdout .= is_string($out) ? $out : '';
            $stderr .= is_string($err) ? $err : '';
            if (strlen($stdout) + strlen($stderr) > $this->maxOutputBytes) {
                proc_terminate($process);
                throw new \RuntimeException('OPUS_RCP_OUTPUT_LIMIT_EXCEEDED');
            }

            $status = proc_get_status($process);
            if (!is_array($status)) {
                throw new \RuntimeException('OPUS_RCP_PROCESS_STATUS_FAILED');
            }
            if (($status['running'] ?? false) !== true) {
                $exitCode = (int) ($status['exitcode'] ?? -1);
                $out = stream_get_contents($stdoutPipe);
                $err = stream_get_contents($stderrPipe);
                $stdout .= is_string($out) ? $out : '';
                $stderr .= is_string($err) ? $err : '';
                break;
            }
            if (microtime(true) >= $deadline) {
                proc_terminate($process);
                throw new \RuntimeException('OPUS_RCP_COMPOSER_COMMAND_TIMEOUT');
            }
            usleep(20000);
        }

        return [$stdout, $stderr, $exitCode];
    }

    /** @return array<string,mixed> */
    private function parseResult(
        string $stdout,
        string $stderr,
        int $exitCode
    ): array {
        foreach (array_reverse($this->jsonObjects($stdout)) as $candidate) {
            try {
                $decoded = Json::instance()->parse(
                    $candidate,
                    'composer:stdout'
                );
            } catch (\Throwable) {
                continue;
            }
            if (in_array(
                $decoded['contract'] ?? null,
                ['OPUS_CONSOLE_COMMAND_RESULT_V1', 'OPUS_CONSOLE_ERROR_V1'],
                true
            )) {
                return $decoded;
            }
        }

        $stderrCode = $this->stderrErrorCode($stderr);
        if ($stderrCode !== null) {
            throw new \RuntimeException($stderrCode);
        }
        if ($exitCode !== 0) {
            throw new \RuntimeException(
                'OPUS_RCP_COMPOSER_COMMAND_FAILED'
            );
        }

        throw new \RuntimeException('OPUS_RCP_COMPOSER_RESULT_MISSING');
    }

    /** @return list<string> */
    private function jsonObjects(string $output): array
    {
        $objects = [];
        $length = strlen($output);
        $start = null;
        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($index = 0; $index < $length; ++$index) {
            $character = $output[$index];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($character === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($character === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($character === '"') {
                if ($depth > 0) {
                    $inString = true;
                }
                continue;
            }
            if ($character === '{') {
                if ($depth === 0) {
                    $start = $index;
                }
                ++$depth;
                continue;
            }
            if ($character !== '}' || $depth === 0) {
                continue;
            }

            --$depth;
            if ($depth === 0 && is_int($start)) {
                $objects[] = substr(
                    $output,
                    $start,
                    $index - $start + 1
                );
                $start = null;
            }
        }

        return $objects;
    }

    private function stderrErrorCode(string $stderr): ?string
    {
        $lines = array_reverse(preg_split('/\R/', trim($stderr)) ?: []);
        foreach ($lines as $line) {
            $candidate = trim((string) $line);
            if (preg_match('/^[A-Z][A-Z0-9_:-]{2,239}$/', $candidate) === 1) {
                return $candidate;
            }
        }
        return null;
    }
}
