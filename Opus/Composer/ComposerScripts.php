<?php
declare(strict_types=1);

namespace Opus\Composer;

use Opus\Console\OpusConsoleApplication;
use Opus\File\File;
use Opus\File\StructuredFileLoader;

/**
 * Generic autoloaded callback for public OPUS Composer commands.
 *
 * Composer invokes this class through the root package autoloader. The callback
 * resolves framework aliases generically and application aliases from each
 * application's own configuration. It never depends on the process CWD.
 */
final class ComposerScripts implements ComposerScriptsInterface
{
    private const APPLICATION_REGISTRY_CONTRACT =
        'OPUS_APPLICATION_COMMAND_PROVIDER_REGISTRY_V1';

    public static function run(object $event): void
    {
        if (!method_exists($event, 'getName')
            || !method_exists($event, 'getArguments')) {
            throw new \RuntimeException('OPUS_COMPOSER_EVENT_INVALID');
        }

        $alias = trim((string) $event->getName());
        $arguments = $event->getArguments();
        if (!is_array($arguments)
            || array_filter($arguments, 'is_string') !== $arguments) {
            throw new \RuntimeException(
                'OPUS_COMPOSER_EVENT_ARGUMENTS_INVALID'
            );
        }
        $arguments = array_values($arguments);

        $opusRoot = dirname(__DIR__, 2);
        $command = self::resolveCommand(
            $opusRoot,
            $alias,
            $arguments
        );

        $exitCode = OpusConsoleApplication::fromRoot($opusRoot)->run([
            'scripts/opus.php',
            $command,
            ...$arguments,
        ]);
        if ($exitCode !== 0) {
            throw new \RuntimeException(
                'OPUS_COMPOSER_CALLBACK_FAILED:' . $exitCode
            );
        }
    }

    /**
     * @param list<string> $arguments
     */
    private static function resolveCommand(
        string $opusRoot,
        string $alias,
        array &$arguments
    ): string {
        if ($alias === 'opus') {
            $command = trim((string) array_shift($arguments));
            return $command === '' ? 'help' : $command;
        }

        if (str_starts_with($alias, 'opus:')) {
            $suffix = substr($alias, strlen('opus:'));
            if ($suffix === ''
                || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $suffix) !== 1) {
                throw new \RuntimeException(
                    'OPUS_COMPOSER_FRAMEWORK_ALIAS_INVALID'
                );
            }
            return str_replace('-', ':', $suffix);
        }

        return self::applicationCommand($opusRoot, $alias);
    }

    private static function applicationCommand(
        string $opusRoot,
        string $alias
    ): string {
        if (preg_match(
            '/^[a-z0-9][a-z0-9:_-]*$/',
            $alias
        ) !== 1) {
            throw new \RuntimeException(
                'OPUS_COMPOSER_APPLICATION_ALIAS_INVALID'
            );
        }

        $file = File::instance();
        $loader = StructuredFileLoader::instance();
        $matches = [];

        foreach ($file->matching(
            rtrim(str_replace('\\', '/', $opusRoot), '/')
            . '/sites/*/config/composer.commands.json'
        ) as $registryPath) {
            $registry = $loader->read($registryPath);
            if (($registry['contract'] ?? null)
                !== self::APPLICATION_REGISTRY_CONTRACT) {
                throw new \RuntimeException(
                    'OPUS_COMPOSER_APPLICATION_REGISTRY_CONTRACT_INVALID'
                );
            }

            $aliases = is_array($registry['aliases'] ?? null)
                ? $registry['aliases']
                : [];
            if (!array_key_exists($alias, $aliases)) {
                continue;
            }

            $command = trim((string) $aliases[$alias]);
            $declared = is_array($registry['providers'] ?? null)
                ? $registry['providers']
                : [];
            if ($command === ''
                || !self::commandDeclared($declared, $command)) {
                throw new \RuntimeException(
                    'OPUS_COMPOSER_APPLICATION_ALIAS_TARGET_INVALID'
                );
            }
            $matches[] = $command;
        }

        $matches = array_values(array_unique($matches));
        if ($matches === []) {
            throw new \RuntimeException(
                'OPUS_COMPOSER_APPLICATION_ALIAS_UNKNOWN:' . $alias
            );
        }
        if (count($matches) !== 1) {
            throw new \RuntimeException(
                'OPUS_COMPOSER_APPLICATION_ALIAS_AMBIGUOUS:' . $alias
            );
        }

        return $matches[0];
    }

    /**
     * @param array<int,mixed> $providers
     */
    private static function commandDeclared(
        array $providers,
        string $command
    ): bool {
        foreach ($providers as $provider) {
            if (!is_array($provider)
                || ($provider['enabled'] ?? false) !== true) {
                continue;
            }
            $commands = is_array($provider['commands'] ?? null)
                ? $provider['commands']
                : [];
            if (in_array($command, $commands, true)) {
                return true;
            }
        }
        return false;
    }
}
