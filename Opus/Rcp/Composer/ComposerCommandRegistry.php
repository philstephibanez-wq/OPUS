<?php
declare(strict_types=1);

namespace Opus\Rcp\Composer;

use Opus\File\StructuredFileLoader;

/** Resolves typed REST operations to the public Composer user-command catalog. */
final class ComposerCommandRegistry implements ComposerCommandRegistryInterface
{
    /** @var array<string,array<string,mixed>> */
    private array $operations;
    /** @var array<string,mixed> */
    private array $composerScripts;

    private function __construct(string $opusRoot, string $catalogRelative)
    {
        $root = rtrim(str_replace('\\', '/', $opusRoot), '/');
        $relative = $this->safeRelative($catalogRelative);
        $loader = StructuredFileLoader::instance();
        $catalog = $loader->read($root . '/' . $relative);
        if (($catalog['contract'] ?? null) !== 'OPUS_RCP_COMPOSER_OPERATION_CATALOG_V1') {
            throw new \RuntimeException('OPUS_RCP_OPERATION_CATALOG_CONTRACT_INVALID');
        }
        $operations = $catalog['operations'] ?? null;
        if (!is_array($operations) || $operations === []) {
            throw new \RuntimeException('OPUS_RCP_OPERATION_CATALOG_EMPTY');
        }

        $composer = $loader->read($root . '/composer.json');
        $scripts = $composer['scripts'] ?? null;
        if (!is_array($scripts)) {
            throw new \RuntimeException('OPUS_RCP_COMPOSER_SCRIPTS_MISSING');
        }

        $this->operations = $operations;
        $this->composerScripts = $scripts;
    }

    public static function fromRoot(
        string $opusRoot,
        string $catalogRelative
    ): self {
        return new self($opusRoot, $catalogRelative);
    }

    public function operation(string $operation): array
    {
        $operation = trim($operation);
        if (preg_match('/^[a-z][a-z0-9.-]*$/', $operation) !== 1) {
            throw new \RuntimeException('OPUS_RCP_OPERATION_INVALID');
        }
        $entry = $this->operations[$operation] ?? null;
        if (!is_array($entry)) {
            throw new \RuntimeException('OPUS_RCP_OPERATION_UNKNOWN');
        }
        $script = trim((string) ($entry['composer_script'] ?? ''));
        if ($script === '' || !array_key_exists($script, $this->composerScripts)) {
            throw new \RuntimeException('OPUS_RCP_COMPOSER_SCRIPT_UNDECLARED');
        }
        $entry['operation'] = $operation;
        $entry['composer_script'] = $script;
        return $entry;
    }

    public function publicOperations(): array
    {
        $result = [];
        foreach (array_keys($this->operations) as $operation) {
            if (!is_string($operation)) {
                continue;
            }
            $entry = $this->operation($operation);
            $result[] = [
                'operation' => $operation,
                'roles' => array_values(array_filter(
                    is_array($entry['roles'] ?? null) ? $entry['roles'] : [],
                    'is_string'
                )),
                'secret_input' => ($entry['secret_input'] ?? false) === true,
            ];
        }
        usort($result, static fn (array $a, array $b): int =>
            strcmp((string) $a['operation'], (string) $b['operation'])
        );
        return $result;
    }

    public function arguments(array $entry, array $parameters): array
    {
        if (($entry['secret_input'] ?? false) === true) {
            return [];
        }
        $definitions = is_array($entry['arguments'] ?? null)
            ? $entry['arguments']
            : [];
        $known = [];
        $arguments = [];

        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                throw new \RuntimeException('OPUS_RCP_ARGUMENT_DEFINITION_INVALID');
            }
            $name = trim((string) ($definition['name'] ?? ''));
            if ($name === '' || preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
                throw new \RuntimeException('OPUS_RCP_ARGUMENT_NAME_INVALID');
            }
            $known[$name] = true;
            $present = array_key_exists($name, $parameters);
            if (($definition['required'] ?? false) === true && !$present) {
                throw new \RuntimeException('OPUS_RCP_ARGUMENT_REQUIRED:' . $name);
            }
            if (!$present) {
                continue;
            }

            $value = $parameters[$name];
            if (($definition['type'] ?? null) === 'boolean') {
                if (!is_bool($value)) {
                    throw new \RuntimeException('OPUS_RCP_ARGUMENT_TYPE_INVALID:' . $name);
                }
                if ($value && isset($definition['flag'])) {
                    $arguments[] = (string) $definition['flag'];
                }
                continue;
            }
            if (!is_string($value)) {
                throw new \RuntimeException('OPUS_RCP_ARGUMENT_TYPE_INVALID:' . $name);
            }
            $value = trim($value);
            $maximum = max(1, (int) ($definition['max_length'] ?? 1024));
            if (strlen($value) > $maximum || str_contains($value, "\0")) {
                throw new \RuntimeException('OPUS_RCP_ARGUMENT_LENGTH_INVALID:' . $name);
            }
            $pattern = (string) ($definition['pattern'] ?? '');
            if ($pattern !== '' && @preg_match('/' . str_replace('/', '\/', $pattern) . '/D', $value) !== 1) {
                throw new \RuntimeException('OPUS_RCP_ARGUMENT_VALUE_INVALID:' . $name);
            }

            if (isset($definition['option'])) {
                $arguments[] = (string) $definition['option'] . '=' . $value;
            } else {
                $arguments[] = $value;
            }
        }

        foreach (array_keys($parameters) as $name) {
            if (is_string($name) && !isset($known[$name])) {
                throw new \RuntimeException('OPUS_RCP_ARGUMENT_UNKNOWN:' . $name);
            }
        }

        return $arguments;
    }

    private function safeRelative(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if (
            $path === ''
            || str_contains($path, '..')
            || preg_match('/^[A-Za-z]:\//', $path) === 1
        ) {
            throw new \RuntimeException('OPUS_RCP_CONFIG_PATH_INVALID');
        }
        return $path;
    }
}
