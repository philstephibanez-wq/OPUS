<?php
declare(strict_types=1);

namespace Opus\Console\Service;

use Opus\Console\OpusConsoleException;
use Opus\File\File;
use Opus\File\StructuredFileLoader;
use Opus\Scaffold\LayeredSiteScaffoldPlan;
use Opus\Scaffold\ScaffoldWriter;
use Opus\Log\Logger;
use Opus\Profiler\Profiler;
use Opus\Security\Runtime\RuntimeSecretStore;

/** Version-aware site command service for legacy and layered OPUS sites. */
final class LayeredSiteCommandService implements
    SiteCommandServiceInterface,
    LayeredSiteCommandServiceInterface
{
    private readonly string $opusRoot;
    private readonly File $file;
    private readonly StructuredFileLoader $loader;
    private readonly SiteCommandService $legacy;

    public function __construct(string $opusRoot)
    {
        $root = rtrim(str_replace('\\', '/', $opusRoot), '/');
        if ($root === '' || !is_dir($root)) {
            throw new OpusConsoleException('OPUS_CONSOLE_ROOT_INVALID');
        }

        $this->opusRoot = $root;
        $this->file = File::instance();
        $this->loader = StructuredFileLoader::instance();
        $this->legacy = new SiteCommandService($root);
    }

    public function create(
        string $siteId,
        bool $write,
        string $profile = 'fullstack'
    ): array {
        $siteId = $this->siteId($siteId);
        $profile = $this->profile($profile);
        $plan = LayeredSiteScaffoldPlan::forSite($siteId, $profile);
        $writer = new ScaffoldWriter($this->opusRoot);
        $writer->assertPathDoesNotExist($plan->rootRelativePath());

        $entries = array_map(
            static fn ($entry): array => [
                'type' => (string) $entry->type,
                'path' => (string) $entry->relativePath,
            ],
            $plan->entries()
        );

        if ($write) {
            $writer->writePlan($plan);
        }

        return [
            'contract' => 'OPUS_CONSOLE_LAYERED_SITE_CREATE_RESULT_V1',
            'site_id' => $siteId,
            'profile' => $profile,
            'composition' => $this->modesForProfile($profile),
            'mode' => $write ? 'write' : 'preview',
            'site_root' => 'sites/' . $siteId,
            'entries' => $entries,
            'entry_count' => count($entries),
            'written' => $write,
        ];
    }

    public function validate(string $siteId): array
    {
        $siteId = $this->siteId($siteId);
        $siteRoot = $this->siteRoot($siteId);
        $site = $this->siteConfig($siteRoot);

        if (($site['contract'] ?? null)
            !== LayeredSiteScaffoldPlan::CONTRACT) {
            return $this->legacy->validate($siteId);
        }

        $profileConfig = is_array($site['application_profile'] ?? null)
            ? $site['application_profile']
            : [];
        if (($profileConfig['contract'] ?? null)
            !== 'OPUS_APPLICATION_PROFILE_V2') {
            throw new OpusConsoleException(
                'OPUS_LAYERED_PROFILE_CONTRACT_INVALID'
            );
        }
        $profile = $this->profile((string) ($profileConfig['type'] ?? ''));
        $modes = $this->runtimeModes($site);
        if ($modes !== $this->modesForProfile($profile)) {
            throw new OpusConsoleException(
                'OPUS_LAYERED_PROFILE_RUNTIME_MODES_MISMATCH'
            );
        }

        $layers = $this->layers($site);
        $requiredDirectories = [
            'config',
            'application',
            $layers['shared'],
            $layers['shared'] . '/runtime',
            $layers['shared'] . '/i18n/default',
            'www',
            'www/asset',
            'var/logs',
            'var/profiler',
        ];
        if (in_array('front', $modes, true)) {
            $requiredDirectories[] = $layers['front'];
            $requiredDirectories[] = $layers['front'] . '/default';
            $requiredDirectories[] = $layers['front'] . '/modules';
        }
        if (in_array('back', $modes, true)) {
            $requiredDirectories[] = $layers['back'];
            $requiredDirectories[] = $layers['back'] . '/modules';
        }

        $runtime = is_array($site['runtime'] ?? null)
            ? $site['runtime']
            : [];
        $requiredFiles = [
            'config/site.json',
            'config/routes.json',
            'config/application.fsm.json',
            'config/acl.json',
            'config/sso.json',
            $this->safeRelative((string) ($runtime['file'] ?? '')),
            $this->safeRelative((string) ($runtime['bootstrap'] ?? '')),
            $this->safeRelative((string) ($runtime['entrypoint'] ?? '')),
        ];

        $missing = [];
        foreach ($requiredDirectories as $relative) {
            if (!is_dir($siteRoot . '/' . $relative)) {
                $missing[] = $relative;
            }
        }
        foreach ($requiredFiles as $relative) {
            if (!$this->file->exists($siteRoot . '/' . $relative)) {
                $missing[] = $relative;
            }
        }
        if ($missing !== []) {
            throw new OpusConsoleException(
                'OPUS_LAYERED_REQUIRED_PATH_MISSING:'
                . implode(',', array_values(array_unique($missing)))
            );
        }

        $routes = $this->loader->read($siteRoot . '/config/routes.json');
        if (($routes['contract'] ?? null)
            !== 'OPUS_LAYERED_ROUTE_REGISTRY_V2'
            || !is_array($routes['routes'] ?? null)
            || !array_is_list($routes['routes'])) {
            throw new OpusConsoleException(
                'OPUS_LAYERED_ROUTE_REGISTRY_INVALID'
            );
        }

        $frontCount = 0;
        $backCount = 0;
        foreach ($routes['routes'] as $route) {
            if (!is_array($route)) {
                throw new OpusConsoleException(
                    'OPUS_LAYERED_ROUTE_INVALID'
                );
            }
            $mode = strtolower(trim((string) (
                $route['runtime_mode'] ?? ''
            )));
            if (!in_array($mode, $modes, true)) {
                throw new OpusConsoleException(
                    'OPUS_LAYERED_ROUTE_MODE_INVALID:' . $mode
                );
            }
            if ($mode === 'front') {
                ++$frontCount;
                foreach (['template', 'view'] as $field) {
                    $relative = $this->safeRelative(
                        (string) ($route[$field] ?? '')
                    );
                    if (!$this->file->exists(
                        $siteRoot . '/' . $layers['front'] . '/' . $relative
                    )) {
                        throw new OpusConsoleException(
                            'OPUS_LAYERED_FRONT_ROUTE_'
                            . strtoupper($field)
                            . '_MISSING:' . $relative
                        );
                    }
                }
            } else {
                ++$backCount;
                $path = trim((string) ($route['path'] ?? ''));
                if (!str_starts_with($path, '/api/')
                    || ($route['representation'] ?? null) !== 'json') {
                    throw new OpusConsoleException(
                        'OPUS_LAYERED_BACK_ROUTE_INVALID:' . $path
                    );
                }
            }
        }

        $this->assertRuntimeContract($siteRoot, $runtime);

        return [
            'contract' => 'OPUS_CONSOLE_LAYERED_SITE_VALIDATE_RESULT_V1',
            'site_id' => $siteId,
            'valid' => true,
            'profile' => $profile,
            'runtime_modes' => $modes,
            'front_routes' => $frontCount,
            'back_routes' => $backCount,
            'singleton' => true,
            'layout' => 'shared-front-back',
        ];
    }

    public function addLanguage(
        string $siteId,
        string $locale,
        bool $write
    ): array {
        $siteId = $this->siteId($siteId);
        $siteRoot = $this->siteRoot($siteId);
        $site = $this->siteConfig($siteRoot);
        if (($site['contract'] ?? null)
            !== LayeredSiteScaffoldPlan::CONTRACT) {
            return $this->legacy->addLanguage($siteId, $locale, $write);
        }

        $locale = $this->locale($locale);
        $layers = $this->layers($site);
        $modules = $this->modules($siteRoot);
        $targets = [
            $siteRoot . '/' . $layers['shared']
                . '/i18n/default/' . $locale . '.json' => 'default',
        ];
        foreach ($modules as $module) {
            $targets[$siteRoot . '/' . $layers['shared']
                . '/i18n/modules/' . $module . '/' . $locale . '.json'] =
                $module;
        }

        if ($write) {
            $locales = is_array($site['locales'] ?? null)
                ? array_values(array_filter($site['locales'], 'is_string'))
                : [];
            if (!in_array($locale, $locales, true)) {
                $locales[] = $locale;
            }
            $site['locales'] = array_values(array_unique($locales));
            $this->loader->writeJson(
                $siteRoot . '/config/site.json',
                $site
            );

            $fallback = trim((string) ($site['default_locale'] ?? 'fr'));
            foreach ($targets as $target => $scope) {
                if ($this->file->exists($target)) {
                    continue;
                }
                $source = $siteRoot . '/' . $layers['shared']
                    . '/i18n/'
                    . ($scope === 'default'
                        ? 'default'
                        : 'modules/' . $scope)
                    . '/' . $fallback . '.json';
                $catalog = $this->loader->read($source);
                $messages = [];
                foreach (array_keys((array) ($catalog['messages'] ?? [])) as $key) {
                    if (is_string($key) && $key !== '') {
                        $messages[$key] = '[[' . $key . ']]';
                    }
                }
                $this->loader->writeJson($target, [
                    'contract' => 'OPUS_I18N_CATALOG_V1',
                    'locale' => $locale,
                    'scope' => $scope,
                    'messages' => $messages,
                ]);
            }
        }

        return [
            'contract' => 'OPUS_CONSOLE_LAYERED_LANGUAGE_ADD_RESULT_V1',
            'site_id' => $siteId,
            'locale' => $locale,
            'mode' => $write ? 'write' : 'preview',
            'targets' => array_map(
                fn (string $path): string => $this->relative($path),
                array_keys($targets)
            ),
            'written' => $write,
        ];
    }

    public function listRoutes(string $siteId): array
    {
        $siteId = $this->siteId($siteId);
        $siteRoot = $this->siteRoot($siteId);
        $site = $this->siteConfig($siteRoot);
        if (($site['contract'] ?? null)
            !== LayeredSiteScaffoldPlan::CONTRACT) {
            return $this->legacy->listRoutes($siteId);
        }
        $routes = $this->loader->read($siteRoot . '/config/routes.json');
        $entries = is_array($routes['routes'] ?? null)
            ? array_values(array_filter($routes['routes'], 'is_array'))
            : [];
        return [
            'contract' => 'OPUS_CONSOLE_LAYERED_ROUTE_LIST_RESULT_V1',
            'site_id' => $siteId,
            'routes' => $entries,
            'route_count' => count($entries),
        ];
    }

    public function createPage(
        string $siteId,
        string $moduleId,
        string $pageId,
        string $path,
        string $title,
        bool $write
    ): array {
        $siteId = $this->siteId($siteId);
        $siteRoot = $this->siteRoot($siteId);
        $site = $this->siteConfig($siteRoot);
        if (($site['contract'] ?? null)
            !== LayeredSiteScaffoldPlan::CONTRACT) {
            return $this->legacy->createPage(
                $siteId,
                $moduleId,
                $pageId,
                $path,
                $title,
                $write
            );
        }
        if (!in_array('front', $this->runtimeModes($site), true)) {
            throw new OpusConsoleException(
                'OPUS_LAYERED_PAGE_REQUIRES_FRONT_MODE'
            );
        }

        $moduleId = $this->identifier(
            $moduleId,
            'OPUS_PAGE_MODULE_ID_INVALID'
        );
        $pageId = $this->identifier(
            $pageId,
            'OPUS_PAGE_ID_INVALID'
        );
        if (!in_array($moduleId, $this->modules($siteRoot), true)) {
            throw new OpusConsoleException(
                'OPUS_PAGE_MODULE_UNKNOWN:' . $moduleId
            );
        }
        $path = $this->routePath($path);
        $title = trim($title) !== ''
            ? trim($title)
            : ucfirst(str_replace('-', ' ', $pageId));
        $layers = $this->layers($site);
        $routesFile = $siteRoot . '/config/routes.json';
        $config = $this->loader->read($routesFile);
        $routes = is_array($config['routes'] ?? null)
            ? $config['routes']
            : [];
        $routeId = 'front.' . $moduleId . '.' . $pageId;
        foreach ($routes as $route) {
            if (is_array($route)
                && (($route['id'] ?? null) === $routeId
                    || ($route['path'] ?? null) === $path)) {
                throw new OpusConsoleException(
                    'OPUS_PAGE_ROUTE_ALREADY_EXISTS:' . $routeId
                );
            }
        }

        $titleKey = 'page.' . $pageId . '.title';
        $subtitleKey = 'page.' . $pageId . '.subtitle';
        $route = [
            'id' => $routeId,
            'path' => $path,
            'state' => $moduleId,
            'module' => $moduleId,
            'action' => $pageId,
            'runtime_mode' => 'front',
            'representation' => 'score',
            'template' => 'modules/' . $moduleId
                . '/templates/' . $pageId . '.score',
            'view' => 'modules/' . $moduleId
                . '/views/' . $pageId . '.php',
            'label' => $titleKey,
            'title_key' => $titleKey,
            'subtitle_key' => $subtitleKey,
            'acl' => 'authenticated',
            'fsm_state' => $moduleId,
            'dispatch_action' => 'render_route',
            'show_in_menu' => false,
        ];

        if ($write) {
            $routes[] = $route;
            $config['routes'] = array_values($routes);
            $this->loader->writeJson($routesFile, $config);
            $moduleRoot = $siteRoot . '/' . $layers['front']
                . '/modules/' . $moduleId;
            $this->file->writeAtomic(
                $moduleRoot . '/templates/' . $pageId . '.score',
                '<section class="opus-card"><h2>{{ page.title }}</h2>'
                . '<p>{{ page.subtitle }}</p></section>' . "\n"
            );
            $this->file->writeAtomic(
                $moduleRoot . '/views/' . $pageId . '.php',
                "<?php\ndeclare(strict_types=1);\n\nreturn [\n"
                . "    'module' => " . var_export($moduleId, true) . ",\n"
                . "    'page' => ['title' => '', 'subtitle' => ''],\n];\n"
            );
            foreach ((array) ($site['locales'] ?? []) as $locale) {
                if (!is_string($locale) || $locale === '') {
                    continue;
                }
                $catalogFile = $siteRoot . '/' . $layers['shared']
                    . '/i18n/modules/' . $moduleId
                    . '/' . $locale . '.json';
                $catalog = $this->loader->read($catalogFile);
                $messages = is_array($catalog['messages'] ?? null)
                    ? $catalog['messages']
                    : [];
                $messages[$titleKey] = $title;
                $messages[$subtitleKey] = '[[' . $subtitleKey . ']]';
                $catalog['messages'] = $messages;
                $this->loader->writeJson($catalogFile, $catalog);
            }
        }

        return [
            'contract' => 'OPUS_CONSOLE_LAYERED_PAGE_CREATE_RESULT_V1',
            'site_id' => $siteId,
            'route' => $route,
            'mode' => $write ? 'write' : 'preview',
            'written' => $write,
        ];
    }

    public function createRubric(
        string $siteId,
        string $moduleId,
        string $path,
        string $title,
        bool $write
    ): array {
        return $this->legacy->createRubric(
            $siteId,
            $moduleId,
            $path,
            $title,
            $write
        );
    }

    public function serve(
        string $siteId,
        string $host,
        int $port,
        string $mode = 'front'
    ): int {
        $siteId = $this->siteId($siteId);
        $siteRoot = $this->siteRoot($siteId);
        $site = $this->siteConfig($siteRoot);
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['front', 'back'], true)) {
            throw new OpusConsoleException(
                'OPUS_SERVE_RUNTIME_MODE_INVALID:' . $mode
            );
        }
        if (($site['contract'] ?? null)
            !== LayeredSiteScaffoldPlan::CONTRACT) {
            return $this->legacy->serve($siteId, $host, $port, $mode);
        }
        if (!in_array($mode, $this->runtimeModes($site), true)) {
            throw new OpusConsoleException(
                'OPUS_SERVE_RUNTIME_MODE_UNAVAILABLE:' . $mode
            );
        }
        return $this->serveProcess($siteRoot, $host, $port, $mode);
    }

    private function serveProcess(
        string $siteRoot,
        string $host,
        int $port,
        string $mode
    ): int {
        if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new OpusConsoleException('OPUS_SERVE_HOST_NOT_LOCAL');
        }
        if ($port < 1024 || $port > 65535) {
            throw new OpusConsoleException('OPUS_SERVE_PORT_INVALID');
        }
        $publicRoot = $siteRoot . '/www';
        $router = $publicRoot . '/index.php';
        if (!is_dir($publicRoot) || !$this->file->exists($router)) {
            throw new OpusConsoleException(
                'OPUS_SERVE_PUBLIC_ROOT_MISSING'
            );
        }
        $environment = getenv();
        $environment = is_array($environment) ? $environment : [];
        $environment = $this->runtimeEnvironment(
            $siteRoot,
            $environment
        );
        $environment['OPUS_APPLICATION_RUNTIME_MODE'] = $mode;
        $this->recordRuntimeStart($siteRoot, $mode, $host, $port);
        $command = [
            PHP_BINARY,
            '-S',
            $host . ':' . $port,
            '-t',
            $publicRoot,
            $router,
        ];
        $process = proc_open(
            $command,
            [0 => STDIN, 1 => STDOUT, 2 => STDERR],
            $pipes,
            $this->opusRoot,
            $environment,
            ['bypass_shell' => true]
        );
        if (!is_resource($process)) {
            throw new OpusConsoleException(
                'OPUS_SERVE_PROCESS_START_FAILED'
            );
        }
        return (int) proc_close($process);
    }

    /**
     * @param array<string,string> $environment
     * @return array<string,string>
     */
    private function runtimeEnvironment(
        string $siteRoot,
        array $environment
    ): array {
        $site = $this->siteConfig($siteRoot);
        $policy = is_array($site['runtime_secrets'] ?? null)
            ? $site['runtime_secrets']
            : null;
        if ($policy === null) {
            return $environment;
        }
        if (($policy['contract'] ?? null)
            !== 'OPUS_RUNTIME_SECRET_BINDING_V1') {
            throw new OpusConsoleException(
                'OPUS_RUNTIME_SECRET_BINDING_CONTRACT_INVALID'
            );
        }
        $store = $this->safeRelative((string) ($policy['store'] ?? ''));
        $bindings = is_array($policy['bindings'] ?? null)
            ? $policy['bindings']
            : [];
        foreach (RuntimeSecretStore::forPath(
            $siteRoot . '/' . $store
        )->ensure($bindings) as $name => $value) {
            $environment[$name] = $value;
        }
        return $environment;
    }

    private function recordRuntimeStart(
        string $siteRoot,
        string $mode,
        string $host,
        int $port
    ): void {
        $site = $this->siteConfig($siteRoot);
        $diagnostics = is_array($site['runtime_diagnostics'] ?? null)
            ? $site['runtime_diagnostics']
            : null;
        if ($diagnostics === null) {
            return;
        }
        if (($diagnostics['contract'] ?? null)
            !== 'OPUS_RUNTIME_DIAGNOSTICS_V1') {
            throw new OpusConsoleException(
                'OPUS_RUNTIME_DIAGNOSTICS_CONTRACT_INVALID'
            );
        }
        $logs = is_array($diagnostics['logs'] ?? null)
            ? $diagnostics['logs']
            : [];
        $relativeLog = $this->safeRelative((string) ($logs[$mode] ?? ''));
        $relativeProfiler = $this->safeRelative((string) (
            $diagnostics['profiler'] ?? ''
        ));
        $absoluteLog = $siteRoot . '/' . $relativeLog;
        $profiler = new Profiler($siteRoot . '/' . $relativeProfiler . '/' . $mode);
        $trace = $profiler->start();
        $traceId = $trace->getTraceId();
        $context = [
            'runtime_mode' => $mode,
            'host' => $host,
            'port' => $port,
        ];
        (new Logger(dirname($absoluteLog), basename($absoluteLog)))->info(
            'opus.runtime.process',
            'process.starting',
            $context,
            $traceId
        );
        $profiler->event(
            'opus.runtime.process',
            'process.starting',
            $context
        );
        $profiler->stop([
            'component' => self::class,
            'status' => 'starting',
            'runtime_mode' => $mode,
        ]);
    }

    /** @return array<string,mixed> */
    private function siteConfig(string $siteRoot): array
    {
        $path = $siteRoot . '/config/site.json';
        if (!$this->file->exists($path)) {
            throw new OpusConsoleException(
                'OPUS_SITE_REQUIRED_PATH_MISSING:config/site.json'
            );
        }
        return $this->loader->read($path);
    }

    /** @param array<string,mixed> $site @return array{shared:string,front:string,back:string} */
    private function layers(array $site): array
    {
        $layers = is_array($site['application_layers'] ?? null)
            ? $site['application_layers']
            : [];
        $shared = $this->safeRelative((string) ($layers['shared'] ?? ''));
        $frontRaw = $layers['front'] ?? null;
        $backRaw = $layers['back'] ?? null;
        return [
            'shared' => $shared,
            'front' => is_string($frontRaw) && trim($frontRaw) !== ''
                ? $this->safeRelative($frontRaw)
                : '',
            'back' => is_string($backRaw) && trim($backRaw) !== ''
                ? $this->safeRelative($backRaw)
                : '',
        ];
    }

    /** @param array<string,mixed> $site @return list<string> */
    private function runtimeModes(array $site): array
    {
        $modes = is_array($site['runtime_modes'] ?? null)
            ? array_values(array_filter($site['runtime_modes'], 'is_string'))
            : [];
        $modes = array_values(array_unique(array_map(
            static fn (string $mode): string => strtolower(trim($mode)),
            $modes
        )));
        if ($modes === []
            || array_diff($modes, ['front', 'back']) !== []) {
            throw new OpusConsoleException(
                'OPUS_LAYERED_RUNTIME_MODES_INVALID'
            );
        }
        return $modes;
    }

    /** @return list<string> */
    private function modules(string $siteRoot): array
    {
        $fsm = $this->loader->read(
            $siteRoot . '/config/application.fsm.json'
        );
        $modules = [];
        foreach ((array) ($fsm['states'] ?? []) as $state) {
            if (!is_array($state)) {
                continue;
            }
            $module = $this->identifier(
                (string) ($state['module'] ?? $state['id'] ?? ''),
                'OPUS_SITE_FSM_MODULE_INVALID'
            );
            $modules[$module] = true;
        }
        if ($modules === []) {
            throw new OpusConsoleException(
                'OPUS_SITE_FSM_MODULES_MISSING'
            );
        }
        return array_keys($modules);
    }

    /** @param array<string,mixed> $runtime */
    private function assertRuntimeContract(
        string $siteRoot,
        array $runtime
    ): void {
        foreach ([
            'contract' => 'OPUS_APPLICATION_SINGLETON_V1',
            'architecture' => 'singleton',
            'factory' => 'instance',
            'runner' => 'run',
            'mode_environment' => 'OPUS_APPLICATION_RUNTIME_MODE',
        ] as $key => $expected) {
            if (($runtime[$key] ?? null) !== $expected) {
                throw new OpusConsoleException(
                    'OPUS_LAYERED_RUNTIME_CONTRACT_INVALID:' . $key
                );
            }
        }
        $class = trim((string) ($runtime['class'] ?? ''));
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class) !== 1) {
            throw new OpusConsoleException(
                'OPUS_APPLICATION_SINGLETON_CLASS_INVALID'
            );
        }
        $source = $this->file->read(
            $siteRoot . '/' . $this->safeRelative(
                (string) ($runtime['file'] ?? '')
            )
        );
        foreach ([
            'final class ' . $class,
            'private static ?self $instance',
            'public static function instance(',
            'public function run(): void',
            'LayeredGeneratedSiteRuntime',
        ] as $needle) {
            if (!str_contains($source, $needle)) {
                throw new OpusConsoleException(
                    'OPUS_LAYERED_SINGLETON_SOURCE_INVALID'
                );
            }
        }
    }

    private function siteId(string $siteId): string
    {
        $siteId = strtolower(trim($siteId));
        if (preg_match('/^[a-z][a-z0-9-]*$/', $siteId) !== 1) {
            throw new OpusConsoleException(
                'OPUS_SITE_ID_INVALID:' . $siteId
            );
        }
        return $siteId;
    }

    private function siteRoot(string $siteId): string
    {
        $root = $this->opusRoot . '/sites/' . $siteId;
        if (!is_dir($root)) {
            throw new OpusConsoleException(
                'OPUS_SITE_ROOT_MISSING:' . $siteId
            );
        }
        return $root;
    }

    private function profile(string $profile): string
    {
        $profile = strtolower(trim($profile));
        if (!in_array(
            $profile,
            LayeredSiteScaffoldPlan::profiles(),
            true
        )) {
            throw new OpusConsoleException(
                'OPUS_APPLICATION_PROFILE_INVALID:' . $profile
            );
        }
        return $profile;
    }

    /** @return list<string> */
    private function modesForProfile(string $profile): array
    {
        return match ($profile) {
            'frontend' => ['front'],
            'backend' => ['back'],
            'fullstack' => ['front', 'back'],
            default => throw new OpusConsoleException(
                'OPUS_APPLICATION_PROFILE_INVALID:' . $profile
            ),
        };
    }

    private function locale(string $locale): string
    {
        $locale = trim(str_replace('_', '-', $locale));
        if (preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})?$/', $locale) !== 1) {
            throw new OpusConsoleException(
                'OPUS_LOCALE_INVALID:' . $locale
            );
        }
        return strtolower(explode('-', $locale)[0]);
    }

    private function identifier(string $value, string $error): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^[a-z][a-z0-9_-]*$/', $value) !== 1) {
            throw new OpusConsoleException($error . ':' . $value);
        }
        return $value;
    }

    private function routePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        if ($path === '/' || preg_match('#^/[a-z0-9/_-]+$#', $path) !== 1) {
            throw new OpusConsoleException(
                'OPUS_ROUTE_PATH_INVALID:' . $path
            );
        }
        return $path;
    }

    private function safeRelative(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === ''
            || str_contains($path, '..')
            || str_contains($path, "\0")) {
            throw new OpusConsoleException(
                'OPUS_RELATIVE_PATH_INVALID:' . $path
            );
        }
        return $path;
    }

    private function relative(string $path): string
    {
        $root = rtrim($this->opusRoot, '/') . '/';
        $normalized = str_replace('\\', '/', $path);
        return str_starts_with($normalized, $root)
            ? substr($normalized, strlen($root))
            : $normalized;
    }
}
