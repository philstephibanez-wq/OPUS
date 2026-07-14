<?php
declare(strict_types=1);

namespace Opus\Fsm;

use RuntimeException;

/**
 * Resolves the canonical FSM configuration for an OPUS site tree.
 *
 * This loader centralizes the same site-level rules enforced by the CLI:
 * generated OWASYS applications must use config/application.fsm.json, while
 * standard OPUS sites can point to a navigation FSM or legacy projection.
 */
final class FsmSiteLoader
{
    /** @var list<string> */
    private const FALLBACK_FSM_FILES = [
        'config/application.fsm.json',
        'config/fsm.json',
        'config/owasys-navigation.fsm.json',
    ];

    /**
     * Loads a processor for a site id located below an OPUS project root.
     *
     * @param array<string,callable> $guardHandlers
     */
    public static function processorForSite(string $opusRoot, string $siteId, array $guardHandlers = []): FsmProcessor
    {
        if ($siteId === '' || preg_match('/^[A-Za-z0-9_-]+$/', $siteId) !== 1) {
            throw new RuntimeException('OPUS_FSM_SITE_ID_INVALID: ' . $siteId);
        }

        return self::processorForSiteRoot(rtrim($opusRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . $siteId, $guardHandlers);
    }

    /**
     * Loads a processor for a concrete OPUS site root.
     *
     * @param array<string,callable> $guardHandlers
     */
    public static function processorForSiteRoot(string $siteRoot, array $guardHandlers = []): FsmProcessor
    {
        $resolved = self::resolve($siteRoot);
        return FsmProcessor::fromJsonFile($resolved['fsm_path'], $guardHandlers);
    }

    /**
     * Resolves site metadata and the FSM path without constructing a processor.
     *
     * @return array{site_id:string,site_root:string,role:string,fsm_path:string,fsm_relative_path:string,site_config:array<string,mixed>}
     */
    public static function resolve(string $siteRoot): array
    {
        $siteRoot = rtrim($siteRoot, DIRECTORY_SEPARATOR);
        if (!is_dir($siteRoot)) {
            throw new RuntimeException('OPUS_FSM_SITE_ROOT_MISSING: ' . $siteRoot);
        }

        $siteConfigFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json';
        $siteConfig = self::readJson($siteConfigFile, 'OPUS_FSM_SITE_JSON_INVALID: ' . $siteRoot);
        $siteId = (string) ($siteConfig['site_id'] ?? basename($siteRoot));
        $role = (string) ($siteConfig['role'] ?? '');

        self::assertStateTreeContract($siteRoot, $siteId, $siteConfig);

        $candidates = [];
        $navigation = is_array($siteConfig['navigation'] ?? null) ? $siteConfig['navigation'] : [];
        $navigationFsm = str_replace('\\', '/', (string) ($navigation['fsm'] ?? ''));
        if ($navigationFsm !== '') {
            self::assertSafeRelativePath($navigationFsm, 'OPUS_FSM_SITE_NAVIGATION_PATH_INVALID: ' . $siteId);
            $candidates[] = trim($navigationFsm, '/');
        }

        foreach (self::FALLBACK_FSM_FILES as $fallback) {
            $candidates[] = $fallback;
        }
        $candidates = array_values(array_unique($candidates));

        if ($role === 'generated-opus-application') {
            $canonical = 'config/application.fsm.json';
            if (($siteConfig['application_fsm'] ?? null) !== $canonical) {
                throw new RuntimeException('OPUS_FSM_GENERATED_APPLICATION_POINTER_INVALID: ' . $siteId);
            }
            if (!is_file($siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $canonical))) {
                throw new RuntimeException('OPUS_FSM_GENERATED_APPLICATION_FSM_MISSING: ' . $siteId);
            }
            $candidates = [$canonical];
        }

        foreach ($candidates as $relative) {
            self::assertSafeRelativePath($relative, 'OPUS_FSM_SITE_FSM_PATH_INVALID: ' . $siteId);
            $absolute = $siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, trim($relative, '/'));
            if (is_file($absolute)) {
                return [
                    'site_id' => $siteId,
                    'site_root' => $siteRoot,
                    'role' => $role,
                    'fsm_path' => $absolute,
                    'fsm_relative_path' => trim($relative, '/'),
                    'site_config' => $siteConfig,
                ];
            }
        }

        throw new RuntimeException('OPUS_FSM_SITE_FSM_MISSING: ' . $siteId);
    }

    /** @return array<string,mixed> */
    private static function readJson(string $path, string $error): array
    {
        if (!is_file($path)) {
            throw new RuntimeException($error);
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new RuntimeException($error);
        }
        return $decoded;
    }

    /** @param array<string,mixed> $siteConfig */
    private static function assertStateTreeContract(string $siteRoot, string $siteId, array $siteConfig): void
    {
        if (($siteConfig['states_root'] ?? null) !== 'application/states') {
            throw new RuntimeException('OPUS_FSM_SITE_STATES_ROOT_INVALID: ' . $siteId);
        }

        if (($siteConfig['dispatch_model'] ?? null) !== 'state-first') {
            throw new RuntimeException('OPUS_FSM_SITE_DISPATCH_MODEL_INVALID: ' . $siteId);
        }

        $applicationRoot = $siteRoot . DIRECTORY_SEPARATOR . 'application';
        $statesRoot = $applicationRoot . DIRECTORY_SEPARATOR . 'states';
        if (!is_dir($statesRoot)) {
            throw new RuntimeException('OPUS_FSM_SITE_STATES_DIRECTORY_MISSING: ' . $siteId);
        }

        foreach (scandir($applicationRoot) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'default' || $entry === 'states') {
                continue;
            }
            if (is_dir($applicationRoot . DIRECTORY_SEPARATOR . $entry)) {
                throw new RuntimeException('OPUS_FSM_SITE_LEGACY_STATE_ROOT_PRESENT: ' . $siteId . ':application/' . $entry);
            }
        }
    }

    private static function assertSafeRelativePath(string $path, string $error): void
    {
        $normalized = trim(str_replace('\\', '/', $path), '/');
        if ($normalized === '' || str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) === 1 || str_contains($normalized, '..')) {
            throw new RuntimeException($error);
        }
    }
}
