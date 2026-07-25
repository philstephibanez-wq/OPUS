<?php
declare(strict_types=1);

namespace Opus\Scaffold;

use Opus\File\Json;

/** Versioned shared/front/back scaffold built from the canonical OPUS source plan. */
final class LayeredSiteScaffoldPlan implements
    ScaffoldPlanInterface,
    LayeredSiteScaffoldPlanInterface
{
    public const CONTRACT = 'OPUS_SITE_LAYERED_CONTRACT_V2';
    public const PROFILE_FRONTEND = SiteScaffoldPlan::PROFILE_FRONTEND;
    public const PROFILE_BACKEND = SiteScaffoldPlan::PROFILE_BACKEND;
    public const PROFILE_FULLSTACK = SiteScaffoldPlan::PROFILE_FULLSTACK;

    private function __construct(
        private readonly string $siteId,
        private readonly string $profile,
        private readonly SiteScaffoldPlan $source
    ) {
    }

    public static function forSite(
        string $siteId,
        string $profile = self::PROFILE_FULLSTACK
    ): self {
        $source = SiteScaffoldPlan::forSite($siteId, $profile);
        return new self(
            trim(strtolower($siteId)),
            $source->profile(),
            $source
        );
    }

    public function profile(): string
    {
        return $this->profile;
    }

    /** @return list<string> */
    public static function profiles(): array
    {
        return [
            self::PROFILE_FRONTEND,
            self::PROFILE_BACKEND,
            self::PROFILE_FULLSTACK,
        ];
    }

    public function rootRelativePath(): string
    {
        return $this->source->rootRelativePath();
    }

    /** @return list<ScaffoldEntry> */
    public function entries(): array
    {
        $sourceFiles = [];
        foreach ($this->source->entries() as $entry) {
            if ($entry->type !== ScaffoldEntry::TYPE_FILE
                || !is_string($entry->content)) {
                continue;
            }
            $sourceFiles[$entry->relativePath] = $entry->content;
        }

        $prefix = 'sites/' . $this->siteId . '/';
        $fsmPath = $prefix . 'config/application.fsm.json';
        if (!isset($sourceFiles[$fsmPath])) {
            throw new \RuntimeException(
                'OPUS_LAYERED_SCAFFOLD_FSM_SOURCE_MISSING'
            );
        }
        $fsm = Json::instance()->parse(
            $sourceFiles[$fsmPath],
            'scaffold://config/application.fsm.json'
        );
        $modules = $this->fsmModules($fsm);

        $files = [];
        foreach ($sourceFiles as $path => $content) {
            $relative = substr($path, strlen($prefix));
            if (!is_string($relative) || $relative === '') {
                continue;
            }

            if ($relative === 'opus-site.json') {
                $files[$path] = $this->layeredOpusSite($content);
                continue;
            }
            if ($relative === 'config/site.json') {
                $files[$path] = $this->layeredSiteConfig($content);
                continue;
            }
            if ($relative === 'config/routes.json') {
                $files[$path] = $this->layeredRoutes($content, $modules);
                continue;
            }
            if ($relative === 'config/menu.json') {
                $files[$path] = $this->hasFront()
                    ? $content
                    : $this->json([
                        'contract' => 'OPUS_MENU_REGISTRY_V1',
                        'items' => [],
                    ]);
                continue;
            }
            if (str_starts_with($relative, 'config/')) {
                $files[$path] = $content;
                continue;
            }
            if ($relative === 'www/index.php') {
                $files[$path] = $this->frontController();
                continue;
            }
            if (str_starts_with($relative, 'www/asset/')) {
                if ($this->hasFront()) {
                    $files[$path] = $content;
                }
                continue;
            }
            if ($relative === 'application/default/Application.php') {
                $files[$prefix . 'application/shared/runtime/Application.php'] =
                    $this->layeredApplicationClass($content);
                continue;
            }
            if ($relative === 'application/default/bootstrap.php') {
                $files[$prefix . 'application/shared/runtime/bootstrap.php'] =
                    $this->layeredBootstrap($content);
                continue;
            }
            if (str_starts_with($relative, 'application/default/local/')) {
                $suffix = substr(
                    $relative,
                    strlen('application/default/local/')
                );
                $files[$prefix . 'application/shared/i18n/default/' . $suffix] =
                    $content;
                continue;
            }
            if (str_starts_with($relative, 'application/default/')) {
                $this->mapDefaultFile($files, $prefix, $relative, $content);
                continue;
            }
            if (str_starts_with($relative, 'application/')) {
                $this->mapModuleFile($files, $prefix, $relative, $content);
                continue;
            }
        }

        $files[$prefix . 'application/shared/contracts/application-layout.json'] =
            $this->json([
                'contract' => 'OPUS_APPLICATION_LAYER_LAYOUT_V1',
                'profile' => $this->profile,
                'composition' => $this->runtimeModes(),
                'shared' => 'application/shared',
                'front' => $this->hasFront() ? 'application/front' : null,
                'back' => $this->hasBack() ? 'application/back' : null,
                'full_directory_forbidden' => true,
            ]);

        if ($this->hasBack()) {
            foreach ($modules as $module) {
                $files[$prefix . 'application/back/modules/' . $module
                    . '/module.json'] = $this->json([
                        'contract' => 'OPUS_BACK_MODULE_V1',
                        'module' => $module,
                        'runtime_mode' => 'back',
                        'commands_via_composer' => true,
                        'rest_required' => true,
                        'acl_default' => 'deny',
                    ]);
            }
        }

        $directories = $this->directoriesFor(array_keys($files), $modules);
        $entries = [];
        foreach ($directories as $directory) {
            $entries[] = ScaffoldEntry::directory($directory);
        }
        foreach ($files as $path => $content) {
            $entries[] = ScaffoldEntry::file($path, $content);
        }

        return $entries;
    }

    /** @param array<string,string> $files */
    private function mapDefaultFile(
        array &$files,
        string $prefix,
        string $relative,
        string $content
    ): void {
        $suffix = substr($relative, strlen('application/default/'));
        if (!is_string($suffix) || $suffix === '') {
            return;
        }

        if (preg_match('#^(layouts|navigation|templates|views)/#', $suffix) === 1) {
            if ($this->hasFront()) {
                $files[$prefix . 'application/front/default/' . $suffix] =
                    $content;
            }
            return;
        }

        if (preg_match('#^(helpers|models)/#', $suffix) === 1) {
            $files[$prefix . 'application/shared/' . $suffix] = $content;
        }
    }

    /** @param array<string,string> $files */
    private function mapModuleFile(
        array &$files,
        string $prefix,
        string $relative,
        string $content
    ): void {
        $tail = substr($relative, strlen('application/'));
        if (!is_string($tail)
            || preg_match('#^([a-z][a-z0-9_-]*)/(.+)$#', $tail, $matches) !== 1) {
            return;
        }
        $module = $matches[1];
        $suffix = $matches[2];

        if (str_starts_with($suffix, 'local/')) {
            $files[$prefix . 'application/shared/i18n/modules/'
                . $module . '/' . substr($suffix, strlen('local/'))] =
                $content;
            return;
        }
        if (str_starts_with($suffix, 'acl/')) {
            $files[$prefix . 'application/shared/acl/modules/'
                . $module . '/' . substr($suffix, strlen('acl/'))] =
                $content;
            return;
        }
        if (str_starts_with($suffix, 'helpers/')) {
            $files[$prefix . 'application/shared/modules/'
                . $module . '/helpers/'
                . substr($suffix, strlen('helpers/'))] = $content;
            return;
        }
        if (str_starts_with($suffix, 'models/')) {
            if ($this->hasBack()) {
                $files[$prefix . 'application/back/modules/'
                    . $module . '/models/'
                    . substr($suffix, strlen('models/'))] = $content;
            }
            return;
        }
        if (preg_match('#^(templates|views|javascript)/#', $suffix) === 1
            && $this->hasFront()) {
            $files[$prefix . 'application/front/modules/'
                . $module . '/' . $suffix] = $content;
        }
    }

    private function layeredOpusSite(string $source): string
    {
        $data = Json::instance()->parse($source, 'scaffold://opus-site.json');
        $data['contract'] = self::CONTRACT;
        $data['application_profile'] = $this->profile;
        $data['application_layout'] = 'shared-front-back';
        return $this->json($data);
    }

    private function layeredSiteConfig(string $source): string
    {
        $data = Json::instance()->parse(
            $source,
            'scaffold://config/site.json'
        );
        $data['contract'] = self::CONTRACT;
        $data['application_profile']['contract'] =
            'OPUS_APPLICATION_PROFILE_V2';
        $data['application_profile']['composition'] = $this->runtimeModes();
        $data['application_layers'] = [
            'contract' => 'OPUS_APPLICATION_LAYER_LAYOUT_V1',
            'shared' => 'application/shared',
            'front' => $this->hasFront() ? 'application/front' : null,
            'back' => $this->hasBack() ? 'application/back' : null,
        ];
        $data['runtime_modes'] = $this->runtimeModes();
        $data['default_root'] = $this->hasFront()
            ? 'application/front/default'
            : null;
        $data['deployment'] = [
            'contract' => 'OPUS_REVERSE_PROXY_DEPLOYMENT_V1',
            'public_https_port' => 443,
            'front_internal_port_default' => 8000,
            'back_internal_port_default' => 8792,
            'ports_configurable' => true,
            'back_publicly_exposed' => false,
            'reverse_proxy_required' => true,
        ];
        $data['runtime'] = [
            'contract' => 'OPUS_APPLICATION_SINGLETON_V1',
            'architecture' => 'singleton',
            'class' => $this->applicationClassName(),
            'file' => 'application/shared/runtime/Application.php',
            'bootstrap' => 'application/shared/runtime/bootstrap.php',
            'entrypoint' => 'www/index.php',
            'factory' => 'instance',
            'runner' => 'run',
            'mode_environment' => 'OPUS_APPLICATION_RUNTIME_MODE',
        ];
        return $this->json($data);
    }

    private function layeredRoutes(string $source, array $modules): string
    {
        $legacy = Json::instance()->parse(
            $source,
            'scaffold://config/routes.json'
        );
        $routes = [];

        if ($this->hasFront()) {
            foreach ((array) ($legacy['routes'] ?? []) as $route) {
                if (!is_array($route)) {
                    continue;
                }
                $module = (string) ($route['module'] ?? '');
                $route['id'] = 'front.' . (string) ($route['id'] ?? 'route');
                $route['runtime_mode'] = 'front';
                $route['representation'] = 'score';
                $route['template'] = 'modules/' . $module
                    . '/templates/index.score';
                $route['view'] = 'modules/' . $module
                    . '/views/index.php';
                $routes[] = $route;
            }
        }

        if ($this->hasBack()) {
            foreach ($modules as $index => $module) {
                $routes[] = [
                    'id' => 'back.' . $module . '.index',
                    'path' => $module === 'home'
                        ? '/api/v1/status'
                        : '/api/v1/' . $module,
                    'state' => $module,
                    'module' => $module,
                    'action' => 'index',
                    'runtime_mode' => 'back',
                    'representation' => 'json',
                    'acl' => 'authenticated',
                    'fsm_state' => $module,
                    'dispatch_action' => 'backend_route',
                    'show_in_menu' => false,
                    'order' => ($index + 1) * 10,
                ];
            }
        }

        return $this->json([
            'contract' => 'OPUS_LAYERED_ROUTE_REGISTRY_V2',
            'dispatch_model' => 'fsm-module-first',
            'routes' => $routes,
        ]);
    }

    private function layeredApplicationClass(string $source): string
    {
        $source = str_replace(
            'use Opus\\Application\\Runtime\\GeneratedSiteRuntime;',
            'use Opus\\Application\\Runtime\\LayeredGeneratedSiteRuntime;',
            $source
        );
        $source = str_replace(
            'private readonly GeneratedSiteRuntime $runtime;',
            'private readonly LayeredGeneratedSiteRuntime $runtime;',
            $source
        );
        $source = str_replace(
            '$this->runtime = new GeneratedSiteRuntime($siteRoot);',
            '$runtimeMode = getenv(\'OPUS_APPLICATION_RUNTIME_MODE\');' . "\n"
            . '        $this->runtime = new LayeredGeneratedSiteRuntime(' . "\n"
            . '            $siteRoot,' . "\n"
            . '            is_string($runtimeMode) ? $runtimeMode : null' . "\n"
            . '        );',
            $source
        );
        $source = str_replace(
            'throw $error;',
            'return $this->runtime->failureResponse($error, $traceId);',
            $source
        );
        return $source;
    }

    private function layeredBootstrap(string $source): string
    {
        return str_replace(
            '$siteRoot = dirname(__DIR__, 2);',
            '$siteRoot = dirname(__DIR__, 3);',
            $source
        );
    }

    private function frontController(): string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/application/shared/runtime/bootstrap.php';
PHP;
    }

    /** @return list<string> */
    private function fsmModules(array $fsm): array
    {
        $modules = [];
        foreach ((array) ($fsm['states'] ?? []) as $state) {
            if (!is_array($state)) {
                continue;
            }
            $module = trim((string) (
                $state['module'] ?? $state['id'] ?? ''
            ));
            if (preg_match('/^[a-z][a-z0-9_-]*$/', $module) === 1) {
                $modules[$module] = true;
            }
        }
        if ($modules === []) {
            throw new \RuntimeException(
                'OPUS_LAYERED_SCAFFOLD_MODULES_MISSING'
            );
        }
        return array_keys($modules);
    }

    /** @param list<string> $filePaths @param list<string> $modules @return list<string> */
    private function directoriesFor(array $filePaths, array $modules): array
    {
        $root = 'sites/' . $this->siteId;
        $directories = [
            $root,
            $root . '/application',
            $root . '/application/shared',
            $root . '/application/shared/contracts',
            $root . '/application/shared/domain',
            $root . '/application/shared/dto',
            $root . '/application/shared/helpers',
            $root . '/application/shared/models',
            $root . '/application/shared/logging',
            $root . '/application/shared/profiling',
            $root . '/application/shared/runtime',
            $root . '/application/shared/i18n',
            $root . '/application/shared/i18n/default',
            $root . '/application/shared/i18n/modules',
            $root . '/application/shared/acl',
            $root . '/application/shared/acl/modules',
            $root . '/application/shared/modules',
        ];
        if ($this->hasFront()) {
            $directories[] = $root . '/application/front';
            $directories[] = $root . '/application/front/default';
            $directories[] = $root . '/application/front/modules';
        }
        if ($this->hasBack()) {
            $directories[] = $root . '/application/back';
            $directories[] = $root . '/application/back/modules';
            $directories[] = $root . '/application/back/api';
            $directories[] = $root . '/application/back/services';
            $directories[] = $root . '/application/back/providers';
            $directories[] = $root . '/application/back/commands';
        }
        foreach ($modules as $module) {
            $directories[] = $root . '/application/shared/i18n/modules/'
                . $module;
            $directories[] = $root . '/application/shared/acl/modules/'
                . $module;
            $directories[] = $root . '/application/shared/modules/'
                . $module;
            if ($this->hasFront()) {
                $directories[] = $root . '/application/front/modules/'
                    . $module;
            }
            if ($this->hasBack()) {
                $directories[] = $root . '/application/back/modules/'
                    . $module;
            }
        }
        foreach ($filePaths as $path) {
            $parent = dirname(str_replace('\\', '/', $path));
            while ($parent !== '.'
                && $parent !== '/'
                && str_starts_with($parent, $root)) {
                $directories[] = $parent;
                if ($parent === $root) {
                    break;
                }
                $parent = dirname($parent);
            }
        }
        $directories = array_values(array_unique($directories));
        usort(
            $directories,
            static fn (string $a, string $b): int =>
                substr_count($a, '/') <=> substr_count($b, '/')
                ?: strcmp($a, $b)
        );
        return $directories;
    }

    /** @return list<string> */
    private function runtimeModes(): array
    {
        return match ($this->profile) {
            self::PROFILE_FRONTEND => ['front'],
            self::PROFILE_BACKEND => ['back'],
            self::PROFILE_FULLSTACK => ['front', 'back'],
            default => throw new \LogicException(
                'OPUS_LAYERED_PROFILE_UNREACHABLE:' . $this->profile
            ),
        };
    }

    private function hasFront(): bool
    {
        return in_array('front', $this->runtimeModes(), true);
    }

    private function hasBack(): bool
    {
        return in_array('back', $this->runtimeModes(), true);
    }

    private function applicationClassName(): string
    {
        $parts = preg_split('/[^a-z0-9]+/i', $this->siteId) ?: [];
        $name = implode('', array_map(
            static fn (string $part): string => ucfirst(strtolower($part)),
            array_filter($parts, static fn (string $part): bool => $part !== '')
        ));
        if ($name === ''
            || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \RuntimeException(
                'OPUS_LAYERED_APPLICATION_CLASS_NAME_INVALID'
            );
        }
        return $name . 'Application';
    }

    /** @param array<string,mixed> $data */
    private function json(array $data): string
    {
        return Json::instance()->encode($data, true);
    }
}
