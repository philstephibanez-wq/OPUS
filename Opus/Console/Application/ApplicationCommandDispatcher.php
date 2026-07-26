<?php
declare(strict_types=1);

namespace Opus\Console\Application;

use Opus\File\File;
use Opus\File\FileInterface;
use Opus\File\StructuredFileLoader;
use Opus\File\StructuredFileLoaderInterface;

/**
 * Resolve application-owned Composer commands without bootstrapping every site.
 *
 * Registry metadata is read eagerly. A provider bootstrap is loaded only after
 * one command resolves to exactly one provider. Framework commands therefore do
 * not execute application bootstraps, and unrelated sites never share a process
 * merely because the OPUS console starts.
 */
final class ApplicationCommandDispatcher implements ApplicationCommandDispatcherInterface
{
    private const REGISTRY_CONTRACT =
        'OPUS_APPLICATION_COMMAND_PROVIDER_REGISTRY_V1';

    private readonly FileInterface $fileService;
    private readonly StructuredFileLoaderInterface $structuredFileLoader;

    /**
     * @var list<array{
     *     site_id:string,
     *     site_root:string,
     *     bootstrap:string,
     *     commands:list<string>
     * }>
     */
    private array $providerDescriptors = [];

    private function __construct(string $opusRoot)
    {
        $root = rtrim(str_replace('\\', '/', $opusRoot), '/');
        if ($root === '' || !is_dir($root)) {
            throw new \RuntimeException('OPUS_APPLICATION_COMMAND_ROOT_INVALID');
        }

        $this->fileService = File::instance();
        $this->structuredFileLoader = StructuredFileLoader::instance();

        foreach ($this->fileService->matching(
            $root . '/sites/*/config/composer.commands.json'
        ) as $registryFile) {
            $this->registerDescriptors($root, $registryFile);
        }
    }

    public static function fromRoot(string $opusRoot): self
    {
        return new self($opusRoot);
    }

    public function supports(string $command): bool
    {
        return $this->matchingDescriptors($command) !== [];
    }

    public function execute(string $command, array $arguments, array $request): array
    {
        $matches = $this->matchingDescriptors($command);
        if ($matches === []) {
            throw new \RuntimeException(
                'OPUS_APPLICATION_COMMAND_UNKNOWN:' . $command
            );
        }
        if (count($matches) !== 1) {
            throw new \RuntimeException(
                'OPUS_APPLICATION_COMMAND_AMBIGUOUS:' . $command
            );
        }

        $provider = $this->loadProvider($matches[0]);
        if (!$provider->supports($command)) {
            throw new \RuntimeException(
                'OPUS_APPLICATION_COMMAND_PROVIDER_CONTRACT_MISMATCH:'
                . $command
            );
        }

        return $provider->execute($command, $arguments, $request);
    }

    private function registerDescriptors(string $root, string $registryFile): void
    {
        $registry = $this->structuredFileLoader->read($registryFile);
        if (($registry['contract'] ?? null) !== self::REGISTRY_CONTRACT) {
            throw new \RuntimeException(
                'OPUS_APPLICATION_COMMAND_REGISTRY_CONTRACT_INVALID:'
                . $this->relative($root, $registryFile)
            );
        }

        $siteRoot = dirname(dirname($registryFile));
        $declaredSite = trim((string) ($registry['site_id'] ?? ''));
        if ($declaredSite === '' || $declaredSite !== basename($siteRoot)) {
            throw new \RuntimeException(
                'OPUS_APPLICATION_COMMAND_REGISTRY_SITE_INVALID:'
                . $this->relative($root, $registryFile)
            );
        }

        foreach ((array) ($registry['providers'] ?? []) as $provider) {
            if (!is_array($provider) || ($provider['enabled'] ?? false) !== true) {
                continue;
            }

            $bootstrap = $this->safeRelative(
                (string) ($provider['bootstrap'] ?? '')
            );
            $commands = $this->commands($provider['commands'] ?? null);
            if ($commands === []) {
                throw new \RuntimeException(
                    'OPUS_APPLICATION_COMMAND_PROVIDER_COMMANDS_INVALID:'
                    . $declaredSite
                );
            }

            $this->providerDescriptors[] = [
                'site_id' => $declaredSite,
                'site_root' => $siteRoot,
                'bootstrap' => $bootstrap,
                'commands' => $commands,
            ];
        }
    }

    /**
     * @return list<array{
     *     site_id:string,
     *     site_root:string,
     *     bootstrap:string,
     *     commands:list<string>
     * }>
     */
    private function matchingDescriptors(string $command): array
    {
        return array_values(array_filter(
            $this->providerDescriptors,
            static fn (array $descriptor): bool => in_array(
                $command,
                $descriptor['commands'],
                true
            )
        ));
    }

    /**
     * @param array{
     *     site_id:string,
     *     site_root:string,
     *     bootstrap:string,
     *     commands:list<string>
     * } $descriptor
     */
    private function loadProvider(
        array $descriptor
    ): ApplicationCommandProviderInterface {
        $path = $descriptor['site_root'] . '/' . $descriptor['bootstrap'];
        if (!$this->fileService->exists($path)) {
            throw new \RuntimeException(
                'OPUS_APPLICATION_COMMAND_BOOTSTRAP_MISSING:'
                . $descriptor['site_id'] . '/' . $descriptor['bootstrap']
            );
        }

        $instance = require $path;
        if (!$instance instanceof ApplicationCommandProviderInterface) {
            throw new \RuntimeException(
                'OPUS_APPLICATION_COMMAND_PROVIDER_INVALID:'
                . $descriptor['site_id'] . '/' . $descriptor['bootstrap']
            );
        }

        return $instance;
    }

    /** @return list<string> */
    private function commands(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $commands = [];
        foreach ($value as $command) {
            $candidate = trim((string) $command);
            if ($candidate === ''
                || preg_match('/^[a-z0-9][a-z0-9:_-]*$/', $candidate) !== 1) {
                throw new \RuntimeException(
                    'OPUS_APPLICATION_COMMAND_PROVIDER_COMMAND_INVALID'
                );
            }
            $commands[] = $candidate;
        }

        return array_values(array_unique($commands));
    }

    private function safeRelative(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === ''
            || str_contains($path, '..')
            || preg_match('/^[A-Za-z]:\//', $path) === 1) {
            throw new \RuntimeException('OPUS_APPLICATION_COMMAND_PATH_INVALID');
        }
        return $path;
    }

    private function relative(string $root, string $path): string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $path = str_replace('\\', '/', $path);
        return str_starts_with($path, $root)
            ? substr($path, strlen($root))
            : $path;
    }
}
