<?php
declare(strict_types=1);

namespace Opus\Fsm;

use Opus\File\StructuredFileLoader;
use Opus\Profiler\ProfilerInterface;
use RuntimeException;

/**
 * Resolves the canonical FSM configuration for an OPUS site tree.
 *
 * OPUS applications use application/default plus application/<module>.
 * application/states is forbidden.
 *
 * Functional modules are derived from the FSM states. site.json contains
 * site-wide infrastructure metadata and is not a second module registry.
 */
final class FsmSiteLoader implements FsmSiteLoaderInterface
{
    /** @var list<string> */
    private const FALLBACK_FSM_FILES = [
        'config/application.fsm.json',
        'config/fsm.json',
    ];

    /**
     * @param array<string,callable> $guardHandlers
     */
    public static function processorForSite(
        string $opusRoot,
        string $siteId,
        array $guardHandlers = [],
        ?ProfilerInterface $profiler = null,
        ?string $parentSpanId = null
    ): FsmProcessor {
        if ($siteId === '' || preg_match('/^[A-Za-z0-9_-]+$/', $siteId) !== 1) {
            throw new RuntimeException('OPUS_FSM_SITE_ID_INVALID: ' . $siteId);
        }

        return self::processorForSiteRoot(
            rtrim($opusRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'sites'
            . DIRECTORY_SEPARATOR . $siteId,
            $guardHandlers,
            $profiler,
            $parentSpanId
        );
    }

    /**
     * @param array<string,callable> $guardHandlers
     */
    public static function processorForSiteRoot(
        string $siteRoot,
        array $guardHandlers = [],
        ?ProfilerInterface $profiler = null,
        ?string $parentSpanId = null
    ): FsmProcessor {
        $resolved = self::resolve($siteRoot);

        return FsmProcessor::fromJsonFile(
            $resolved['fsm_path'],
            $guardHandlers,
            $profiler,
            $parentSpanId
        );
    }

    /**
     * Creates a processor for one named EFSM registered by the application.
     * The browser/UI never supplies a source path; only the semantic EFSM id
     * crosses the boundary and this loader resolves the canonical path.
     *
     * @param array<string,callable> $guardHandlers
     */
    public static function processorForSiteRootEfsm(
        string $siteRoot,
        string $efsmId,
        array $guardHandlers = [],
        ?ProfilerInterface $profiler = null,
        ?string $parentSpanId = null
    ): FsmProcessor {
        $resolved = self::resolveEfsm($siteRoot, $efsmId);

        return FsmProcessor::fromJsonFile(
            $resolved['fsm_path'],
            $guardHandlers,
            $profiler,
            $parentSpanId
        );
    }

    /**
     * @return array{
     *   site_id:string,
     *   site_root:string,
     *   role:string,
     *   efsm_id:string,
     *   fsm_path:string,
     *   fsm_relative_path:string,
     *   modules:list<string>,
     *   site_config:array<string,mixed>
     * }
     */
    public static function resolveEfsm(string $siteRoot, string $efsmId): array
    {
        $efsmId = strtolower(trim($efsmId));
        if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $efsmId) !== 1) {
            throw new RuntimeException('OPUS_EFSM_ID_INVALID:' . $efsmId);
        }

        $siteRoot = rtrim($siteRoot, DIRECTORY_SEPARATOR);
        if (!is_dir($siteRoot)) {
            throw new RuntimeException('OPUS_FSM_SITE_ROOT_MISSING: ' . $siteRoot);
        }

        $siteConfigFile = $siteRoot . DIRECTORY_SEPARATOR
            . 'config' . DIRECTORY_SEPARATOR . 'site.json';
        $siteConfig = self::readStructured(
            $siteConfigFile,
            'OPUS_EFSM_SITE_JSON_INVALID: ' . $siteRoot
        );
        $siteId = (string) ($siteConfig['site_id'] ?? basename($siteRoot));
        $role = (string) ($siteConfig['role'] ?? '');
        self::assertApplicationTreeContract($siteRoot, $siteId, $siteConfig);

        $registry = is_array($siteConfig['efsms'] ?? null)
            ? $siteConfig['efsms']
            : [];
        $relative = trim(str_replace(
            '\\',
            '/',
            (string) ($registry[$efsmId] ?? '')
        ), '/');

        if ($relative === '' && $efsmId === 'navigation') {
            $resolved = self::resolve($siteRoot);
            $resolved['efsm_id'] = 'navigation';
            return $resolved;
        }
        if ($relative === '') {
            throw new RuntimeException(
                'OPUS_EFSM_SITE_REGISTRY_ENTRY_MISSING:'
                . $siteId . ':' . $efsmId
            );
        }

        self::assertSafeRelativePath(
            $relative,
            'OPUS_EFSM_SITE_PATH_INVALID:' . $siteId . ':' . $efsmId
        );
        $absolute = $siteRoot . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($absolute)) {
            throw new RuntimeException(
                'OPUS_EFSM_SITE_FILE_MISSING:' . $siteId . ':' . $efsmId
            );
        }

        $fsm = self::readStructured(
            $absolute,
            'OPUS_EFSM_SITE_JSON_INVALID:' . $siteId . ':' . $efsmId
        );
        $modules = self::modulesFromFsm($fsm, $siteId);
        self::assertFsmModuleDirectories($siteRoot, $siteId, $modules);

        return [
            'site_id' => $siteId,
            'site_root' => $siteRoot,
            'role' => $role,
            'efsm_id' => $efsmId,
            'fsm_path' => $absolute,
            'fsm_relative_path' => $relative,
            'modules' => $modules,
            'site_config' => $siteConfig,
        ];
    }
    /**
     * @return array{
     *   site_id:string,
     *   site_root:string,
     *   role:string,
     *   fsm_path:string,
     *   fsm_relative_path:string,
     *   modules:list<string>,
     *   site_config:array<string,mixed>
     * }
     */
    public static function resolve(string $siteRoot): array
    {
        $siteRoot = rtrim($siteRoot, DIRECTORY_SEPARATOR);
        if (!is_dir($siteRoot)) {
            throw new RuntimeException('OPUS_FSM_SITE_ROOT_MISSING: ' . $siteRoot);
        }

        $siteConfigFile = $siteRoot . DIRECTORY_SEPARATOR
            . 'config' . DIRECTORY_SEPARATOR . 'site.json';
        $siteConfig = self::readStructured(
            $siteConfigFile,
            'OPUS_FSM_SITE_JSON_INVALID: ' . $siteRoot
        );
        $siteId = (string) ($siteConfig['site_id'] ?? basename($siteRoot));
        $role = (string) ($siteConfig['role'] ?? '');

        self::assertApplicationTreeContract($siteRoot, $siteId, $siteConfig);

        $candidates = [];
        $navigation = is_array($siteConfig['navigation'] ?? null)
            ? $siteConfig['navigation']
            : [];
        $navigationFsm = str_replace(
            '\\',
            '/',
            (string) ($navigation['fsm'] ?? '')
        );

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
                throw new RuntimeException(
                    'OPUS_FSM_GENERATED_APPLICATION_POINTER_INVALID: ' . $siteId
                );
            }

            if (!is_file(
                $siteRoot . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $canonical)
            )) {
                throw new RuntimeException(
                    'OPUS_FSM_GENERATED_APPLICATION_FSM_MISSING: ' . $siteId
                );
            }

            $candidates = [$canonical];
        }

        foreach ($candidates as $relative) {
            self::assertSafeRelativePath(
                $relative,
                'OPUS_FSM_SITE_FSM_PATH_INVALID: ' . $siteId
            );

            $absolute = $siteRoot . DIRECTORY_SEPARATOR
                . str_replace(
                    '/',
                    DIRECTORY_SEPARATOR,
                    trim($relative, '/')
                );

            if (!is_file($absolute)) {
                continue;
            }

            $fsm = self::readStructured(
                $absolute,
                'OPUS_FSM_SITE_FSM_JSON_INVALID: ' . $siteId
            );
            $modules = self::modulesFromFsm($fsm, $siteId);
            self::assertFsmModuleDirectories($siteRoot, $siteId, $modules);

            return [
                'site_id' => $siteId,
                'site_root' => $siteRoot,
                'role' => $role,
                'fsm_path' => $absolute,
                'fsm_relative_path' => trim($relative, '/'),
                'modules' => $modules,
                'site_config' => $siteConfig,
            ];
        }

        throw new RuntimeException('OPUS_FSM_SITE_FSM_MISSING: ' . $siteId);
    }

    /** @return array<string,mixed> */
    private static function readStructured(string $path, string $error): array
    {
        try {
            return StructuredFileLoader::instance()->read($path);
        } catch (\Throwable $cause) {
            throw new RuntimeException($error, 0, $cause);
        }
    }

    /** @param array<string,mixed> $siteConfig */
    private static function assertApplicationTreeContract(
        string $siteRoot,
        string $siteId,
        array $siteConfig
    ): void {
        if (($siteConfig['application_root'] ?? null) !== 'application') {
            throw new RuntimeException(
                'OPUS_FSM_SITE_APPLICATION_ROOT_INVALID: ' . $siteId
            );
        }

        if (($siteConfig['default_root'] ?? null) !== 'application/default') {
            throw new RuntimeException(
                'OPUS_FSM_SITE_DEFAULT_ROOT_INVALID: ' . $siteId
            );
        }

        if (($siteConfig['dispatch_model'] ?? null) !== 'fsm-module-first') {
            throw new RuntimeException(
                'OPUS_FSM_SITE_DISPATCH_MODEL_INVALID: ' . $siteId
            );
        }

        $applicationRoot = $siteRoot . DIRECTORY_SEPARATOR . 'application';
        $defaultRoot = $applicationRoot . DIRECTORY_SEPARATOR . 'default';
        $forbiddenStatesRoot = $applicationRoot . DIRECTORY_SEPARATOR . 'states';

        if (!is_dir($applicationRoot)) {
            throw new RuntimeException(
                'OPUS_FSM_SITE_APPLICATION_DIRECTORY_MISSING: ' . $siteId
            );
        }

        if (!is_dir($defaultRoot)) {
            throw new RuntimeException(
                'OPUS_FSM_SITE_DEFAULT_MODULE_MISSING: ' . $siteId
            );
        }

        if (is_dir($forbiddenStatesRoot)) {
            throw new RuntimeException(
                'OPUS_FSM_SITE_FORBIDDEN_STATES_DIRECTORY: ' . $siteId
            );
        }
    }

    /**
     * @param array<string,mixed> $fsm
     * @return list<string>
     */
    private static function modulesFromFsm(array $fsm, string $siteId): array
    {
        $states = $fsm['states'] ?? null;
        if (!is_array($states) || $states === []) {
            throw new RuntimeException('OPUS_FSM_SITE_STATES_MISSING: ' . $siteId);
        }

        $modules = [];

        foreach ($states as $state) {
            if (!is_array($state)) {
                throw new RuntimeException('OPUS_FSM_SITE_STATE_INVALID: ' . $siteId);
            }

            $stateId = trim((string) ($state['id'] ?? ''));
            if ($stateId === '') {
                throw new RuntimeException(
                    'OPUS_FSM_SITE_STATE_ID_INVALID: ' . $siteId
                );
            }

            /*
             * A pure EFSM state is an engine object, not an implicit
             * application module. Only an explicit module field participates
             * in the application directory contract.
             */
            if (!array_key_exists('module', $state)) {
                continue;
            }
            $module = trim((string) $state['module']);
            if (preg_match('/^[a-z][a-z0-9_-]*$/', $module) !== 1) {
                throw new RuntimeException(
                    'OPUS_FSM_SITE_MODULE_NAME_INVALID: '
                    . $siteId . ':' . $module
                );
            }

            if ($module === 'default') {
                throw new RuntimeException(
                    'OPUS_FSM_SITE_DEFAULT_STATE_MODULE_FORBIDDEN: ' . $siteId
                );
            }

            $modules[$module] = true;
        }

        return array_keys($modules);
    }

    /** @param list<string> $modules */
    private static function assertFsmModuleDirectories(
        string $siteRoot,
        string $siteId,
        array $modules
    ): void {
        $applicationRoot = $siteRoot . DIRECTORY_SEPARATOR . 'application';

        foreach ($modules as $module) {
            if (!is_dir($applicationRoot . DIRECTORY_SEPARATOR . $module)) {
                throw new RuntimeException(
                    'OPUS_FSM_SITE_MODULE_DIRECTORY_MISSING: '
                    . $siteId . ':' . $module
                );
            }
        }
    }

    private static function assertSafeRelativePath(
        string $path,
        string $error
    ): void {
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
