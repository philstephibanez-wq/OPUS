<?php
/**
 * OPUS delivery layout validator.
 *
 * Visibility: public CLI maintenance tool.
 * Role: validate the OPUS development or delivery tree topology without modifying files.
 * Arguments: --root=<path> [--mode=dev|delivery]
 * Returns: process exit code 0 when the topology passes, 1 on contract failure.
 * Side effects: none; prints deterministic status/errors.
 * Business contract: useful delivery roots may be empty, tests are dev-only, no silent fallback,
 *                    no secrets, no runtime payloads in delivered var/.
 */
final class OpusDeliveryLayoutValidator
{
    private const LICENSE_PROFILE = 'OPUS_SOURCE_AVAILABLE_FREE_NONCOMMERCIAL_COMMERCIAL_ROYALTIES';
    private const COPYRIGHT_HOLDER = 'Philippe Stéphane Ibanez';

    /** @var list<string> */
    private array $errors = [];

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        $args = $this->parseArgs($argv);
        $root = $this->requiredArg($args, 'root');
        $mode = isset($args['mode']) && is_string($args['mode']) ? $args['mode'] : 'dev';

        if (!in_array($mode, ['dev', 'delivery'], true)) {
            $this->error('Invalid --mode. Expected dev or delivery.');
        }

        $rootPath = realpath($root);
        if ($rootPath === false || !is_dir($rootPath)) {
            $this->error('Root directory not found: ' . $root);
            return $this->finish(false);
        }

        $this->validateRequiredTopology($rootPath);
        $this->validateExampleConfig($rootPath);
        $this->scanForbiddenArtifacts($rootPath, $mode);

        if ($this->errors === []) {
            fwrite(STDOUT, "OPUS_DELIVERY_LAYOUT_OK\n");
            fwrite(STDOUT, 'ROOT=' . $rootPath . "\n");
            fwrite(STDOUT, 'MODE=' . $mode . "\n");
            return 0;
        }

        return $this->finish(false);
    }

    /** @param list<string> $argv @return array<string, string|true> */
    private function parseArgs(array $argv): array
    {
        $args = [];
        foreach (array_slice($argv, 1) as $arg) {
            if (!str_starts_with($arg, '--')) {
                $this->error('Invalid argument format: ' . $arg);
                continue;
            }
            $raw = substr($arg, 2);
            if (str_contains($raw, '=')) {
                [$key, $value] = explode('=', $raw, 2);
                $args[$key] = $value;
            } else {
                $args[$raw] = true;
            }
        }
        return $args;
    }

    /** @param array<string, string|true> $args */
    private function requiredArg(array $args, string $name): string
    {
        if (!isset($args[$name]) || !is_string($args[$name]) || trim($args[$name]) === '') {
            $this->error('Missing required argument --' . $name . '=...');
            return '';
        }
        return $args[$name];
    }

    private function validateRequiredTopology(string $root): void
    {
        foreach ([
            'framework' . DIRECTORY_SEPARATOR . 'Opus',
            'packages',
            'sites',
            'tools',
            'config',
            'var',
            'var' . DIRECTORY_SEPARATOR . 'cache',
            'var' . DIRECTORY_SEPARATOR . 'logs',
            'var' . DIRECTORY_SEPARATOR . 'tmp',
        ] as $dir) {
            if (!is_dir($root . DIRECTORY_SEPARATOR . $dir)) {
                $this->error('Required directory missing: ' . $dir);
            }
        }

        foreach ([
            'README.md',
            'LICENSE_INTENT.md',
            'DELIVERY_PROFILE.md',
            'packages' . DIRECTORY_SEPARATOR . 'README.md',
            'sites' . DIRECTORY_SEPARATOR . 'README.md',
            'config' . DIRECTORY_SEPARATOR . 'README.md',
            'config' . DIRECTORY_SEPARATOR . 'opus.example.json',
            'var' . DIRECTORY_SEPARATOR . 'README.md',
            'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . '.gitkeep',
            'var' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . '.gitkeep',
            'var' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . '.gitkeep',
        ] as $file) {
            if (!is_file($root . DIRECTORY_SEPARATOR . $file)) {
                $this->error('Required file missing: ' . $file);
            }
        }
    }

    private function validateExampleConfig(string $root): void
    {
        $path = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'opus.example.json';
        if (!is_file($path)) {
            return;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            $this->error('Unable to read config/opus.example.json');
            return;
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            $this->error('Invalid config/opus.example.json: ' . json_last_error_msg());
            return;
        }

        $this->requireEquals($json, 'fallback_allowed', false, 'config/opus.example.json');
        $this->requireEquals($json, 'framework_duplication_allowed', false, 'config/opus.example.json');
        $this->requireEquals($json, 'license_profile', self::LICENSE_PROFILE, 'config/opus.example.json');
        $this->requireEquals($json, 'copyright_holder', self::COPYRIGHT_HOLDER, 'config/opus.example.json');
    }

    private function scanForbiddenArtifacts(string $root, string $mode): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $entry) {
            $path = $entry->getPathname();
            $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
            $name = strtolower($entry->getFilename());

            if ($entry->isDir()) {
                $this->scanForbiddenDirectory($relative, $name, $mode);
                continue;
            }

            if (!$entry->isFile()) {
                continue;
            }

            $this->scanForbiddenFile($relative, $name, $mode);
        }
    }

    private function scanForbiddenDirectory(string $relative, string $name, string $mode): void
    {
        if ($mode === 'delivery' && in_array($relative, ['tests', '.git', '.github'], true)) {
            $this->error('Development-only directory forbidden in delivery: ' . $relative);
        }

        if ($mode === 'delivery' && preg_match('#(^|/)(node_modules|coverage|reports)(/|$)#i', $relative) === 1) {
            $this->error('Development artifact directory forbidden in delivery: ' . $relative);
        }

        if (preg_match('#(^|/)(cache|tmp|\.cache)(/|$)#i', $relative) === 1 && !str_starts_with($relative, 'var/')) {
            $this->error('Unexpected cache/tmp directory outside var/: ' . $relative);
        }
    }

    private function scanForbiddenFile(string $relative, string $name, string $mode): void
    {
        if (preg_match('/\.(bak|old|orig|tmp|swp)$/i', $name) === 1) {
            $this->error('Backup/temp artifact forbidden: ' . $relative);
        }

        if (preg_match('/(^|[._-])legacy([._-]|$)/i', $name) === 1) {
            $this->error('Legacy artifact forbidden: ' . $relative);
        }

        if (in_array($name, ['.env', '.env.local', 'secrets.json', 'secret.json'], true)) {
            $this->error('Secret-like file forbidden: ' . $relative);
        }

        if ($mode === 'delivery' && preg_match('#^var/(cache|logs|tmp)/#', $relative) === 1 && $name !== '.gitkeep') {
            $this->error('Runtime var payload forbidden in delivery: ' . $relative);
        }
    }

    /** @param array<string, mixed> $data */
    private function requireEquals(array $data, string $key, mixed $expected, string $label): void
    {
        if (!array_key_exists($key, $data) || $data[$key] !== $expected) {
            $this->error($label . ': ' . $key . ' must be ' . var_export($expected, true));
        }
    }

    private function error(string $message): void
    {
        $this->errors[] = $message;
    }

    private function finish(bool $success): int
    {
        if ($this->errors === []) {
            return $success ? 0 : 1;
        }

        fwrite(STDERR, "OPUS_DELIVERY_LAYOUT_FAILED\n");
        foreach ($this->errors as $error) {
            fwrite(STDERR, '- ' . $error . "\n");
        }
        return 1;
    }
}

exit((new OpusDeliveryLayoutValidator())->run($argv));
