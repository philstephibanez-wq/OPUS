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
 *
 * Composer-facing aliases remain registry metadata. They are resolved only
 * after the owning application descriptor has been selected, so an RCP request
 * cannot load a provider from another application that declares the same alias.
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
     *     commands:list<string>,
     *     aliases:array<string,string>
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
        $applicationId = $this->requestApplicationId($request);
        $matches = $this->matchingDescriptors($command, $applicationId);
        if ($matches === []) {
            throw new \RuntimeException(
                $applicationId === ''
                    ? 'OPUS_APPLICATION_COMMAND_UNKNOWN'
                    : 'OPUS_APPLICATION_COMMAND_TARGET_PROVIDER_MISSING'
            );
        }
        if (count($matches) !== 1) {
            throw new \RuntimeException(
                'OPUS_APPLICATION_COMMAND_AMBIGUOUS'
            );
        }

        $descriptor = $matches[0];
        $canonicalCommand = $this->canonicalCommand(
            $descriptor,
            $command
        );
        $provider = $this->loadProvider($descriptor);
        if (!$provider->supports($canonicalCommand)) {
            throw new \RuntimeException(
                'OPUS_APPLICATION_COMMAND_PROVIDER_CONTRACT_MISMATCH'
            );
        }

        return $provider->execute(
            $canonicalCommand,
            $arguments,
            $request
        );
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

        $aliases = $this->aliases($registry['aliases'] ?? null);
        $declaredCommands = [];
        $descriptorOffset = count($this->providerDescriptors);

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

            foreach ($commands as $declaredCommand) {
                $declaredCommands[$declaredCommand] = true;
            }

            $this->providerDescriptors[] = [
                'site_id' => $declaredSite,
                'site_root' => $siteRoot,
                'bootstrap' => $bootstrap,
                'commands' => $commands,
                'aliases' => [],
            ];
        }

        foreach ($aliases as $alias => $target) {
            if (!isset($declaredCommands[$target])) {
                throw new \RuntimeException(
                    'OPUS_APPLICATION_COMMAND_ALIAS_TARGET_UNKNOWN:'
                    . $declaredSite
                );
            }
        }

        $descriptorCount = count($this->providerDescriptors);
        for ($index = $descriptorOffset; $index < $descriptorCount; ++$index) {
            $commands = $this->providerDescriptors[$index]['commands'];
            $this->providerDescriptors[$index]['aliases'] = array_filter(
                $aliases,
                static fn (string $target): bool => in_array(
                    $target,
                    $commands,
                    true
                )
            );
        }
    }

    /**
     * @return list<array{
     *     site_id:string,
     *     site_root:string,
     *     bootstrap:string,
     *     commands:list<string>,
     *     aliases:array<string,string>
     * }>
     */
    private function matchingDescriptors(
        string $command,
        string $applicationId = ''
    ): array {
        return array_values(array_filter(
            $this->providerDescriptors,
            static fn (array $descriptor): bool => (
                $applicationId === ''
                || $descriptor['site_id'] === $applicationId
            ) && (
                in_array($command, $descriptor['commands'], true)
                || array_key_exists($command, $descriptor['aliases'])
            )
        ));
    }

    /**
     * @param array{
     *     site_id:string,
     *     site_root:string,
     *     bootstrap:string,
     *     commands:list<string>,
     *     aliases:array<string,string>
     * } $descriptor
     */
    private function canonicalCommand(
        array $descriptor,
        string $command
    ): string {
        return $descriptor['aliases'][$command] ?? $command;
    }

    /** @param array<string,mixed> $request */
    private function requestApplicationId(array $request): string
    {
        $contract = trim((string) ($request['contract'] ?? ''));
        $applicationId = trim((string) (
            $request['application_id'] ?? ''
        ));

        if ($contract === 'OPUS_RCP_COMPOSER_COMMAND_REQUEST_V1'
            && $applicationId === '') {
            throw new \RuntimeException(
                'OPUS_APPLICATION_COMMAND_TARGET_REQUIRED'
            );
        }
        if ($applicationId !== ''
            && preg_match('/^[a-z][a-z0-9-]*$/', $applicationId) !== 1) {
            throw new \RuntimeException(
                'OPUS_APPLICATION_COMMAND_TARGET_INVALID'
            );
        }

        return $applicationId;
    }

    /**
     * @param array{
     *     site_id:string,
     *     site_root:string,
     *     bootstrap:string,
     *     commands:list<string>,
     *     aliases:array<string,string>
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
            $candidate = $this->commandName(
                $command,
                'OPUS_APPLICATION_COMMAND_PROVIDER_COMMAND_INVALID'
            );
            $commands[] = $candidate;
        }

        return array_values(array_unique($commands));
    }

    /** @return array<string,string> */
    private function aliases(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (!is_array($value)) {
            throw new \RuntimeException(
                'OPUS_APPLICATION_COMMAND_ALIASES_INVALID'
            );
        }

        $aliases = [];
        foreach ($value as $alias => $target) {
            $aliasName = $this->commandName(
                $alias,
                'OPUS_APPLICATION_COMMAND_ALIAS_INVALID'
            );
            $targetName = $this->commandName(
                $target,
                'OPUS_APPLICATION_COMMAND_ALIAS_TARGET_INVALID'
            );
            if (isset($aliases[$aliasName])
                && $aliases[$aliasName] !== $targetName) {
                throw new \RuntimeException(
                    'OPUS_APPLICATION_COMMAND_ALIAS_CONFLICT'
                );
            }
            $aliases[$aliasName] = $targetName;
        }

        return $aliases;
    }

    private function commandName(mixed $value, string $error): string
    {
        $candidate = trim((string) $value);
        if ($candidate === ''
            || preg_match('/^[a-z0-9][a-z0-9:_-]*$/', $candidate) !== 1) {
            throw new \RuntimeException($error);
        }
        return $candidate;
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
