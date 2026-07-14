<?php
declare(strict_types=1);

namespace Opus\Owasys;

use RuntimeException;

/**
 * Read-only inspector for an OPUS application selected in OWASYS.
 *
 * This service does not mutate the inspected application. It enforces the same
 * state-first contract expected by the OPUS FSM runtime before returning a
 * structure view-model for OWASYS.
 */
final class ApplicationInspector
{
    public const CONTRACT = 'OWASYS_APPLICATION_INSPECTION_V1';

    public function __construct(private readonly string $opusRoot)
    {
    }

    public static function forOpusRoot(string $opusRoot): self
    {
        return new self(rtrim($opusRoot, DIRECTORY_SEPARATOR));
    }

    /**
     * @param array<string,mixed> $entry
     * @return array<string,mixed>
     */
    public function inspectEntry(array $entry): array
    {
        $rootPath = $this->safeRelativePath((string) ($entry['root_path'] ?? ''), 'OWASYS_APPLICATION_INSPECTION_ROOT_PATH_INVALID');
        if (!str_starts_with($rootPath, 'sites/')) {
            throw new RuntimeException('OWASYS_APPLICATION_INSPECTION_ROOT_PATH_OUT_OF_SCOPE: ' . $rootPath);
        }

        $siteRoot = $this->opusRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rootPath);
        if (!is_dir($siteRoot)) {
            throw new RuntimeException('OWASYS_APPLICATION_INSPECTION_SITE_ROOT_MISSING: ' . $rootPath);
        }

        $siteConfig = $this->readJsonFile($siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json', 'OWASYS_APPLICATION_INSPECTION_SITE_CONFIG_INVALID');
        if (($siteConfig['contract'] ?? null) !== 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL') {
            throw new RuntimeException('OWASYS_APPLICATION_INSPECTION_SITE_CONTRACT_INVALID: ' . $rootPath);
        }
        if (($siteConfig['states_root'] ?? null) !== 'application/states') {
            throw new RuntimeException('OWASYS_APPLICATION_INSPECTION_STATES_ROOT_INVALID: ' . $rootPath);
        }
        if (($siteConfig['dispatch_model'] ?? null) !== 'state-first') {
            throw new RuntimeException('OWASYS_APPLICATION_INSPECTION_DISPATCH_MODEL_INVALID: ' . $rootPath);
        }

        $statesRoot = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'states';
        if (!is_dir($statesRoot)) {
            throw new RuntimeException('OWASYS_APPLICATION_INSPECTION_STATES_DIRECTORY_MISSING: ' . $rootPath);
        }

        $legacyRoots = $this->legacyStateRoots($siteRoot);
        if ($legacyRoots !== []) {
            throw new RuntimeException('OWASYS_APPLICATION_INSPECTION_LEGACY_STATE_ROOT_PRESENT: ' . implode(',', $legacyRoots));
        }

        $routesConfig = $this->readJsonFile($siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes.json', 'OWASYS_APPLICATION_INSPECTION_ROUTES_CONFIG_INVALID');
        if (($routesConfig['dispatch_model'] ?? null) !== 'state-first') {
            throw new RuntimeException('OWASYS_APPLICATION_INSPECTION_ROUTES_DISPATCH_MODEL_INVALID: ' . $rootPath);
        }

        $fsmRelative = $this->fsmRelativePath($siteConfig);
        $fsmConfig = $this->readJsonFile($siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fsmRelative), 'OWASYS_APPLICATION_INSPECTION_FSM_CONFIG_INVALID');
        if (!is_string($fsmConfig['contract'] ?? null) || (string) $fsmConfig['contract'] === '') {
            throw new RuntimeException('OWASYS_APPLICATION_INSPECTION_FSM_CONTRACT_INVALID: ' . $rootPath);
        }

        $routes = $this->routes((array) ($routesConfig['routes'] ?? []));
        $states = $this->states($statesRoot, (array) ($fsmConfig['states'] ?? []), $routes);
        $transitions = array_values(array_filter((array) ($fsmConfig['transitions'] ?? []), 'is_array'));

        return [
            'inspection_contract' => self::CONTRACT,
            'site_id' => (string) ($siteConfig['site_id'] ?? ($entry['id'] ?? '')),
            'site_name' => (string) ($siteConfig['site_name'] ?? ($entry['name'] ?? '')),
            'root_path' => $rootPath,
            'site_contract' => (string) ($siteConfig['contract'] ?? ''),
            'routes_contract' => (string) ($routesConfig['contract'] ?? ''),
            'fsm_contract' => (string) ($fsmConfig['contract'] ?? ''),
            'fsm_relative_path' => $fsmRelative,
            'dispatch_model' => (string) ($siteConfig['dispatch_model'] ?? ''),
            'states_root' => (string) ($siteConfig['states_root'] ?? ''),
            'kind' => (string) ($siteConfig['kind'] ?? ($entry['kind'] ?? 'fullstack')),
            'role' => (string) ($siteConfig['role'] ?? ($entry['role'] ?? 'standard-opus-application')),
            'blueprint' => (string) ($siteConfig['blueprint'] ?? ($entry['blueprint'] ?? 'unknown')),
            'default_locale' => (string) ($siteConfig['default_locale'] ?? ($entry['default_locale'] ?? 'fr')),
            'public_root' => (string) ($siteConfig['public_root'] ?? ($entry['public_root'] ?? 'www')),
            'theme' => (string) ($siteConfig['theme'] ?? ($entry['theme'] ?? 'default')),
            'generated_by' => (string) ($siteConfig['generated_by'] ?? ($entry['generated_by'] ?? 'unknown')),
            'states' => $states,
            'routes' => $routes,
            'state_count' => count($states),
            'route_count' => count($routes),
            'transition_count' => count($transitions),
            'legacy_state_roots' => $legacyRoots,
            'status' => 'valid',
        ];
    }

    /** @param array<string,mixed> $siteConfig */
    private function fsmRelativePath(array $siteConfig): string
    {
        $relative = $siteConfig['application_fsm'] ?? null;
        if (!is_string($relative) || trim($relative) === '') {
            $navigation = is_array($siteConfig['navigation'] ?? null) ? $siteConfig['navigation'] : [];
            $relative = $navigation['fsm'] ?? null;
        }
        if (!is_string($relative) || trim($relative) === '') {
            throw new RuntimeException('OWASYS_APPLICATION_INSPECTION_FSM_POINTER_MISSING');
        }

        return $this->safeRelativePath($relative, 'OWASYS_APPLICATION_INSPECTION_FSM_PATH_INVALID');
    }

    /** @return array<string,mixed> */
    private function readJsonFile(string $file, string $error): array
    {
        if (!is_file($file)) {
            throw new RuntimeException($error . '_MISSING: ' . $this->relativeFromOpusRoot($file));
        }
        $decoded = json_decode((string) file_get_contents($file), true);
        if (!is_array($decoded)) {
            throw new RuntimeException($error . ': ' . $this->relativeFromOpusRoot($file));
        }

        return $decoded;
    }

    /** @param list<mixed> $routeRows @return list<array<string,string>> */
    private function routes(array $routeRows): array
    {
        $routes = [];
        foreach ($routeRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $state = (string) ($row['state'] ?? ($row['fsm_state'] ?? ''));
            if ($state === '') {
                throw new RuntimeException('OWASYS_APPLICATION_INSPECTION_ROUTE_STATE_MISSING');
            }
            $view = (string) ($row['view'] ?? '');
            if ($view !== '' && !str_starts_with(str_replace('\\', '/', $view), 'application/states/')) {
                throw new RuntimeException('OWASYS_APPLICATION_INSPECTION_ROUTE_VIEW_INVALID: ' . $view);
            }
            $routes[] = [
                'id' => (string) ($row['id'] ?? ''),
                'path' => (string) ($row['path'] ?? ''),
                'state' => $state,
                'view' => $view,
            ];
        }

        return $routes;
    }

    /** @param list<mixed> $fsmStates @param list<array<string,string>> $routes @return list<array<string,mixed>> */
    private function states(string $statesRoot, array $fsmStates, array $routes): array
    {
        $stateIndex = [];
        foreach (scandir($statesRoot) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_dir($statesRoot . DIRECTORY_SEPARATOR . $entry) && preg_match('/^[a-z0-9_-]+$/', $entry) === 1) {
                $stateIndex[$entry] = [
                    'id' => $entry,
                    'directory' => 'application/states/' . $entry,
                    'in_fsm' => false,
                    'in_routes' => false,
                    'routes' => [],
                ];
            }
        }

        foreach ($fsmStates as $state) {
            if (!is_array($state)) {
                continue;
            }
            $id = (string) ($state['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $stateIndex[$id] ??= [
                'id' => $id,
                'directory' => 'application/states/' . $id,
                'in_fsm' => false,
                'in_routes' => false,
                'routes' => [],
            ];
            $stateIndex[$id]['in_fsm'] = true;
            if (isset($state['view'])) {
                $stateIndex[$id]['view'] = (string) $state['view'];
            }
        }

        foreach ($routes as $route) {
            $stateId = $route['state'];
            $stateIndex[$stateId] ??= [
                'id' => $stateId,
                'directory' => 'application/states/' . $stateId,
                'in_fsm' => false,
                'in_routes' => false,
                'routes' => [],
            ];
            $stateIndex[$stateId]['in_routes'] = true;
            $stateIndex[$stateId]['routes'][] = $route['path'];
        }

        ksort($stateIndex);
        return array_values($stateIndex);
    }

    /** @return list<string> */
    private function legacyStateRoots(string $siteRoot): array
    {
        $applicationRoot = $siteRoot . DIRECTORY_SEPARATOR . 'application';
        $legacy = [];
        foreach (scandir($applicationRoot) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'default' || $entry === 'states') {
                continue;
            }
            if (is_dir($applicationRoot . DIRECTORY_SEPARATOR . $entry)) {
                $legacy[] = 'application/' . $entry;
            }
        }

        return $legacy;
    }

    private function safeRelativePath(string $path, string $error): string
    {
        $normalized = trim(str_replace('\\', '/', $path), '/');
        if ($normalized === '' || str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) === 1 || str_contains($normalized, '..')) {
            throw new RuntimeException($error . ': ' . $path);
        }

        return $normalized;
    }

    private function relativeFromOpusRoot(string $path): string
    {
        $root = rtrim(str_replace('\\', '/', realpath($this->opusRoot) ?: $this->opusRoot), '/') . '/';
        $normalized = str_replace('\\', '/', realpath($path) ?: $path);
        return str_starts_with($normalized, $root) ? substr($normalized, strlen($root)) : $normalized;
    }
}
