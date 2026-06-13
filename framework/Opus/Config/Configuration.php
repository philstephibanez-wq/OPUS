<?php

declare(strict_types=1);

namespace Opus\Config;

/*
 * OPUS_REFBOOK:
 *   domain: CONFIG
 *   role: Class Configuration belongs to the CONFIG Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the CONFIG domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - config-overview
 *   diagrams:
 *     - config-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY-ALIGNED CONFIGURATION
 *
 * Role:
 *   Preserve the original Opus Configuration object as typed key/value config.
 *
 * Responsibility:
 *   Store and expose declared configuration values.
 *
 * Contract:
 *   Missing keys fail explicitly unless caller checks `has()` first.
 *
 * Since:
 *   P112D4C
 *
 * Legacy compatibility:
 *   P112P1 restores the remaining non-risky legacy methods.
 */
final class Configuration
{
    /** @var array<string,mixed> */
    private array $values = [];

    /**
     * @param array<string,mixed> $values Initial configuration.
     */
    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            throw \ASAP\Exception\Exception::because('OPUS_CONFIGURATION_KEY_MISSING', $key);
        }

        return $this->values[$key];
    }

    public function set(string $key, mixed $value): void
    {
        if (trim($key) === '') {
            throw \ASAP\Exception\Exception::because('OPUS_CONFIGURATION_KEY_EMPTY');
        }

        $this->values[$key] = $value;
    }

    public function getDatabase(): mixed
    {
        return $this->get('database');
    }

    public function getEnv(): mixed
    {
        return $this->get('env');
    }

    public function setEnv(string $env): void
    {
        if (trim($env) === '') {
            throw \ASAP\Exception\Exception::because('OPUS_CONFIGURATION_ENV_EMPTY');
        }

        $this->set('env', $env);
    }

    public function getRoutes(): mixed
    {
        return $this->get('routes');
    }

    public function get_browser(?string $userAgent = null): string
    {
        $agent = $this->resolveUserAgent($userAgent);

        return $this->detectBrowser($agent);
    }

    public function get_os(?string $userAgent = null): string
    {
        $agent = $this->resolveUserAgent($userAgent);

        return $this->detectOs($agent);
    }

    /**
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    private function resolveUserAgent(?string $userAgent): string
    {
        if ($userAgent !== null && trim($userAgent) !== '') {
            return $userAgent;
        }

        if ($this->has('user_agent')) {
            $value = $this->get('user_agent');

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        throw \ASAP\Exception\Exception::because('OPUS_CONFIGURATION_USER_AGENT_MISSING');
    }

    private function detectBrowser(string $userAgent): string
    {
        $agent = strtolower($userAgent);

        if (str_contains($agent, 'edg/')) {
            return 'edge';
        }

        if (str_contains($agent, 'firefox/')) {
            return 'firefox';
        }

        if (str_contains($agent, 'chrome/') || str_contains($agent, 'chromium/')) {
            return 'chrome';
        }

        if (str_contains($agent, 'safari/') && !str_contains($agent, 'chrome/')) {
            return 'safari';
        }

        if (str_contains($agent, 'msie') || str_contains($agent, 'trident/')) {
            return 'internet_explorer';
        }

        return 'unknown';
    }

    private function detectOs(string $userAgent): string
    {
        $agent = strtolower($userAgent);

        if (str_contains($agent, 'windows')) {
            return 'windows';
        }

        if (str_contains($agent, 'android')) {
            return 'android';
        }

        if (str_contains($agent, 'iphone') || str_contains($agent, 'ipad') || str_contains($agent, 'ios')) {
            return 'ios';
        }

        if (str_contains($agent, 'mac os') || str_contains($agent, 'macintosh')) {
            return 'macos';
        }

        if (str_contains($agent, 'linux')) {
            return 'linux';
        }

        return 'unknown';
    }
}
