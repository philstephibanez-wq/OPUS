<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\File\File;
use Opus\File\StructuredFileLoader;

/** Loads and validates the environment-driven OPUS Profiler registration policy. */
final class ProfilerConfiguration implements ProfilerConfigurationInterface
{
    public const CONTRACT = 'OPUS_PROFILER_ENVIRONMENT_CONFIG_V1';

    private function __construct(
        private readonly string $environment,
        private readonly bool $collect,
        private readonly bool $web,
        private readonly bool $links
    ) {
    }

    public static function fromSiteRoot(string $siteRoot): self
    {
        $root = rtrim(str_replace('\\', '/', $siteRoot), '/');
        if ($root === '' || !is_dir($root)) {
            throw new \RuntimeException('OPUS_PROFILER_SITE_ROOT_INVALID');
        }

        $path = $root . '/config/environment.yaml';
        if (!File::instance()->exists($path)) {
            return new self('production', false, false, false);
        }

        $data = StructuredFileLoader::instance()->read($path);
        if (($data['contract'] ?? null) !== self::CONTRACT) {
            throw new \RuntimeException(
                'OPUS_PROFILER_ENVIRONMENT_CONFIG_CONTRACT_INVALID'
            );
        }

        $environment = strtolower(trim((string) ($data['environment'] ?? '')));
        if (preg_match('/^[a-z][a-z0-9_-]{1,31}$/D', $environment) !== 1) {
            throw new \RuntimeException('OPUS_PROFILER_ENVIRONMENT_INVALID');
        }

        $profiler = is_array($data['profiler'] ?? null)
            ? $data['profiler']
            : null;
        $web = is_array($profiler['web'] ?? null)
            ? $profiler['web']
            : null;
        if (!is_array($profiler)
            || !is_bool($profiler['collect'] ?? null)
            || !is_array($web)
            || !is_bool($web['enabled'] ?? null)
            || !is_bool($web['links'] ?? null)) {
            throw new \RuntimeException('OPUS_PROFILER_ENVIRONMENT_CONFIG_INVALID');
        }

        $collect = $profiler['collect'];
        $webEnabled = $web['enabled'];
        $linksEnabled = $web['links'];

        if ($environment !== 'dev' && ($webEnabled || $linksEnabled)) {
            throw new \RuntimeException(
                'OPUS_PROFILER_WEB_ENVIRONMENT_FORBIDDEN:' . $environment
            );
        }
        if ($linksEnabled && !$webEnabled) {
            throw new \RuntimeException('OPUS_PROFILER_LINKS_REQUIRE_WEB');
        }
        if ($webEnabled && !$collect) {
            throw new \RuntimeException('OPUS_PROFILER_WEB_REQUIRES_COLLECTION');
        }

        $runtimeEnvironment = strtolower(trim((string) (
            getenv('OPUS_ENV') ?: ''
        )));
        if ($environment === 'dev'
            && $runtimeEnvironment === 'dev'
            && $collect
            && $webEnabled) {
            $linksEnabled = true;
        }

        return new self(
            $environment,
            $collect,
            $webEnabled,
            $linksEnabled
        );
    }

    public function environment(): string
    {
        return $this->environment;
    }

    public function collectEnabled(): bool
    {
        return $this->collect;
    }

    public function webEnabled(): bool
    {
        return $this->web;
    }

    public function linksEnabled(): bool
    {
        return $this->links;
    }
}
