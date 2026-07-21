<?php
declare(strict_types=1);

namespace Opus\Fsm;

use RuntimeException;

/**
 * Resolves the canonical FSM configuration for an OPUS site tree.
 *
 * OPUS applications use application/default plus application/<module>.
 * application/states is forbidden.
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
     * @param array<string,callable> $guardHandlers
     */
    public static function processorForSite(string $opusRoot, string $siteId, array $guardHandlers = []): FsmProcessor
    {
        if ($siteId === '' || preg_match('/^[A-Za-z0-9_-]+$/', $siteId) !== 1) {
            throw new RuntimeException('OPUS_FSM_SITE_ID_INVALID: ' . $siteId);
        }

        return self::processorForSiteRoot(
            rtrim($opusRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'sites'
            . DIRECTORY_SEPARATOR . $siteId,
            $guardHandlers
        );
    }

    /**
     * @param array<string,callable> $guardHandlers
     */
    public static function processorForSiteRoot(string $siteRoot, array $guardHandlers = []): FsmProcessor
    {
        $resolved = self::resolve($siteRoot);

        return FsmProcessor::fromJsonFile($resolved['fsm_path'], $guardHandlers);
    }

    /**
     * @return array{
     *   site_id:string,
     *   site_root:string,
     *   role:string,
     *   fsm_path:string,
     *   fsm_relative_path:string,
     *   site_config:array<string,mixed>
     * }
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

        self::assertModuleTreeContract($siteRoot, $siteId, $siteConfig);

        $candidates = [];
        $navigation = is_array($siteConfig['navigation'] ?? null) ? $siteConfig['navigation'] : [];
        $navigationFsm = str_replace('\\', '/', (string) ($navigation['fsm'] ?? ''));

        if ($navigationFsm !== '') {
            self::assertSafeRelativePath(
                $navigationFsm,
                'OPUS_FSM_SITE_NAVIGATION_PATH_INVALID: ' . $siteId
            );
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

            $absolute = $siteRoot . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, trim($relative, '/'));

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
    private static function assertModuleTreeContract(string $siteRoot, string $siteId, array $siteConfig): void
    {
        if (($siteConfig['application_root'] ?? null) !== 'application') {
            throw new RuntimeException('OPUS_FSM_SITE_APPLICATION_ROOT_INVALID: ' . $siteId);
        }

        if (($siteConfig['default_root'] ?? null) !== 'application/default') {
            throw new RuntimeException('OPUS_FSM_SITE_DEFAULT_ROOT_INVALID: ' . $siteId);
        }

        if (($siteConfig['dispatch_model'] ?? null) !== 'fsm-module-first') {
            throw new RuntimeException('OPUS_FSM_SITE_DISPATCH_MODEL_INVALID: ' . $siteId);
        }

        $applicationRoot = $siteRoot . DIRECTORY_SEPARATOR . 'application';
        $defaultRoot = $applicationRoot . DIRECTORY_SEPARATOR . 'default';
        $forbiddenStatesRoot = $applicationRoot . DIRECTORY_SEPARATOR . 'states';

        if (!is_dir($applicationRoot)) {
            throw new RuntimeException('OPUS_FSM_SITE_APPLICATION_DIRECTORY_MISSING: ' . $siteId);
        }

        if (!is_dir($defaultRoot)) {
            throw new RuntimeException('OPUS_FSM_SITE_DEFAULT_MODULE_MISSING: ' . $siteId);
        }

        if (is_dir($forbiddenStatesRoot)) {
            throw new RuntimeException('OPUS_FSM_SITE_FORBIDDEN_STATES_DIRECTORY: ' . $siteId);
        }

        $modules = $siteConfig['modules'] ?? null;
        if (!is_array($modules) || $modules === []) {
            throw new RuntimeException('OPUS_FSM_SITE_MODULES_MISSING: ' . $siteId);
        }

        foreach ($modules as $module) {
            if (!is_string($module) || preg_match('/^[a-z][a-z0-9_-]*$/', $module) !== 1) {
                throw new RuntimeException('OPUS_FSM_SITE_MODULE_NAME_INVALID: ' . $siteId);
            }

            if (!is_dir($applicationRoot . DIRECTORY_SEPARATOR . $module)) {
                throw new RuntimeException('OPUS_FSM_SITE_MODULE_DIRECTORY_MISSING: ' . $siteId . ':' . $module);
            }
        }
    }

    private static function assertSafeRelativePath(string $path, string $error): void
    {
        $normalized = trim(str_replace('\\', '/', $path), '/');

        if (
            $normalized === ''
            || str_starts_with($normalized, '/')
            || preg_match('/^[A-Za-z]:\//', $normalized) === 1
            || str_contains($normalized, '..')
        ) {
            throw new RuntimeException($error);
        }
    }
}
