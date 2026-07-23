<?php
declare(strict_types=1);

namespace Opus\Console\Application;

use Opus\File\File;
use Opus\File\StructuredFileLoader;

/**
 * Discovers application-owned Composer command providers from each OPUS site.
 *
 * Each application owns its provider configuration below sites/<site>/config.
 * OPUS only supplies the generic discovery and dispatch boundary.
 */
final class ApplicationCommandDispatcher implements ApplicationCommandDispatcherInterface
{
    /** @var list<ApplicationCommandProviderInterface> */
    private array $providers = [];

    private function __construct(string $opusRoot)
    {
        $root = rtrim(str_replace('\\', '/', $opusRoot), '/');
        if ($root === '' || !is_dir($root)) {
            throw new \RuntimeException('OPUS_APPLICATION_COMMAND_ROOT_INVALID');
        }

        $file = File::instance();
        $loader = StructuredFileLoader::instance();
        $registries = $file->matching(
            $root . '/sites/*/config/composer.commands.json'
        );

        foreach ($registries as $registryFile) {
            $registry = $loader->read($registryFile);
            if (($registry['contract'] ?? null)
                !== 'OPUS_APPLICATION_COMMAND_PROVIDER_REGISTRY_V1') {
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
                $path = $siteRoot . '/' . $bootstrap;
                if (!$file->exists($path)) {
                    throw new \RuntimeException(
                        'OPUS_APPLICATION_COMMAND_BOOTSTRAP_MISSING:'
                        . $this->relative($root, $path)
                    );
                }

                $instance = require $path;
                if (!$instance instanceof ApplicationCommandProviderInterface) {
                    throw new \RuntimeException(
                        'OPUS_APPLICATION_COMMAND_PROVIDER_INVALID:'
                        . $this->relative($root, $path)
                    );
                }
                $this->providers[] = $instance;
            }
        }
    }

    public static function fromRoot(string $opusRoot): self
    {
        return new self($opusRoot);
    }

    public function supports(string $command): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($command)) {
                return true;
            }
        }
        return false;
    }

    public function execute(string $command, array $arguments, array $request): array
    {
        $matches = array_values(array_filter(
            $this->providers,
            static fn (ApplicationCommandProviderInterface $provider): bool =>
                $provider->supports($command)
        ));

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

        return $matches[0]->execute($command, $arguments, $request);
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
