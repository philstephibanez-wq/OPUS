<?php
declare(strict_types=1);

namespace Opus\Application\Package;

/**
 * Immutable manifest for a Composer-installable OPUS application package.
 *
 * Official OPUS applications such as RefBook, demo apps and ODBC Manager must
 * expose this contract instead of relying on manual folder copies.
 */
final class ApplicationPackageManifest implements ApplicationPackageManifestInterface
{
    public const CONTRACT_ID = 'OPUS_APPLICATION_PACKAGE_MANIFEST_V1';

    private string $packageName;
    private string $applicationSlug;
    private string $applicationName;
    /** @var array<string,string> */
    private array $paths;
    /** @var array<string,bool> */
    private array $integrations;
    /** @var array<string,mixed> */
    private array $security;
    /** @var array<string,mixed> */
    private array $metadata;

    /**
     * @param array<string,string> $paths
     * @param array<string,bool> $integrations
     * @param array<string,mixed> $security
     * @param array<string,mixed> $metadata
     */
    private function __construct(string $packageName, string $applicationSlug, string $applicationName, array $paths, array $integrations, array $security = [], array $metadata = [])
    {
        $packageName = trim($packageName);
        $applicationSlug = trim($applicationSlug);
        $applicationName = trim($applicationName);

        if (!preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/', $packageName)) {
            throw new \InvalidArgumentException('OPUS_APP_PACKAGE_NAME_INVALID: ' . $packageName);
        }
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,80}$/', $applicationSlug)) {
            throw new \InvalidArgumentException('OPUS_APP_PACKAGE_SLUG_INVALID: ' . $applicationSlug);
        }
        if ($applicationName === '') {
            throw new \InvalidArgumentException('OPUS_APP_PACKAGE_NAME_EMPTY: ' . $applicationSlug);
        }

        foreach (['application', 'routes', 'views', 'i18n'] as $requiredPath) {
            if (!isset($paths[$requiredPath]) || trim((string) $paths[$requiredPath]) === '') {
                throw new \InvalidArgumentException('OPUS_APP_PACKAGE_PATH_MISSING: ' . $requiredPath);
            }
        }

        foreach (['scoretemplate', 'i18n', 'sso_acl', 'diagnostics', 'profiler'] as $requiredIntegration) {
            if (!array_key_exists($requiredIntegration, $integrations)) {
                throw new \InvalidArgumentException('OPUS_APP_PACKAGE_INTEGRATION_MISSING: ' . $requiredIntegration);
            }
        }

        $this->packageName = $packageName;
        $this->applicationSlug = $applicationSlug;
        $this->applicationName = $applicationName;
        $this->paths = array_map('strval', $paths);
        $this->integrations = array_map('boolval', $integrations);
        $this->security = $security;
        $this->metadata = $metadata;
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        if (($data['contract'] ?? '') !== self::CONTRACT_ID) {
            throw new \InvalidArgumentException('OPUS_APP_PACKAGE_MANIFEST_CONTRACT_INVALID');
        }
        if (!isset($data['application']) || !is_array($data['application'])) {
            throw new \InvalidArgumentException('OPUS_APP_PACKAGE_APPLICATION_MISSING');
        }
        if (!isset($data['paths']) || !is_array($data['paths'])) {
            throw new \InvalidArgumentException('OPUS_APP_PACKAGE_PATHS_MISSING');
        }
        if (!isset($data['integrations']) || !is_array($data['integrations'])) {
            throw new \InvalidArgumentException('OPUS_APP_PACKAGE_INTEGRATIONS_MISSING');
        }

        return new self(
            (string) ($data['package'] ?? ''),
            (string) ($data['application']['slug'] ?? ''),
            (string) ($data['application']['name'] ?? ''),
            self::stringMap($data['paths']),
            self::boolMap($data['integrations']),
            isset($data['security']) && is_array($data['security']) ? $data['security'] : [],
            isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : []
        );
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException('OPUS_APP_PACKAGE_MANIFEST_MISSING: ' . $path);
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('OPUS_APP_PACKAGE_MANIFEST_JSON_INVALID: ' . $path);
        }

        return self::fromArray($decoded);
    }

    public function packageName(): string
    {
        return $this->packageName;
    }

    public function applicationSlug(): string
    {
        return $this->applicationSlug;
    }

    public function applicationName(): string
    {
        return $this->applicationName;
    }

    /** @return array<string,string> */
    public function paths(): array
    {
        return $this->paths;
    }

    /** @return array<string,bool> */
    public function integrations(): array
    {
        return $this->integrations;
    }

    /** @return array<string,mixed> */
    public function security(): array
    {
        return $this->security;
    }

    public function isProtected(): bool
    {
        return (bool) ($this->security['protected'] ?? false);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'contract' => self::CONTRACT_ID,
            'package' => $this->packageName,
            'application' => [
                'slug' => $this->applicationSlug,
                'name' => $this->applicationName,
            ],
            'paths' => $this->paths,
            'integrations' => $this->integrations,
            'security' => $this->security,
            'metadata' => $this->metadata,
        ];
    }

    /** @param array<mixed> $data @return array<string,string> */
    private static function stringMap(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $out[(string) $key] = (string) $value;
        }
        return $out;
    }

    /** @param array<mixed> $data @return array<string,bool> */
    private static function boolMap(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $out[(string) $key] = (bool) $value;
        }
        return $out;
    }
}
