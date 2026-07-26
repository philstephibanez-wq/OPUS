<?php
declare(strict_types=1);

namespace Opus\Console\Service;

use Opus\Console\OpusConsoleException;
use Opus\Console\Development\DevelopmentServerRegistry;
use Opus\Console\Development\DevelopmentServerRegistryInterface;
use Opus\File\File;
use Opus\File\StructuredFileLoader;
use Opus\Scaffold\SiteScaffoldPlan;
use Opus\Scaffold\ScaffoldWriter;
use Opus\Log\Logger;
use Opus\Profiler\Profiler;
use Opus\Security\Runtime\RuntimeSecretStore;

/**
 * Canonical user-command service for OPUS sites and applications.
 *
 * Configuration is read through StructuredFileLoader and therefore through
 * the canonical File plus JSON/YAML/XML parser boundary.
 */
final class SiteCommandService implements SiteCommandServiceInterface
{
    private readonly string $opusRoot;
    private readonly File $file;
    private readonly StructuredFileLoader $loader;

    public function __construct(string $opusRoot)
    {
        $root = rtrim(str_replace('\\', '/', $opusRoot), '/');
        if ($root === '' || !is_dir($root)) {
            throw new OpusConsoleException('OPUS_CONSOLE_ROOT_INVALID');
        }

        $this->opusRoot = $root;
        $this->file = File::instance();
        $this->loader = StructuredFileLoader::instance();
    }

    public function create(
        string $siteId,
        bool $write,
        string $profile = 'fullstack'
    ): array {
        $siteId = $this->siteId($siteId);
        $profile = $this->applicationProfile($profile);
        $plan = SiteScaffoldPlan::forSite($siteId, $profile);
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
            'contract' => 'OPUS_CONSOLE_SITE_CREATE_RESULT_V1',
            'site_id' => $siteId,
            'profile' => $profile,
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
        $siteConfigFile = $siteRoot . '/config/site.json';

        if (!$this->file->exists($siteConfigFile)) {
            throw new OpusConsoleException(
                'OPUS_SITE_REQUIRED_PATH_MISSING:config/site.json'
            );
        }

        $site = $this->loader->read($siteConfigFile);
        $fsmRelative = $this->fsmRelativePath($site);
        $standardLayout = $this->standardLayout($site);
        $surface = $this->applicationSurface($site);

        if ($standardLayout !== null) {
            $runtime = is_array($site['runtime'] ?? null)
                ? $site['runtime']
                : [];
            $bootstraps = is_array($runtime['bootstraps'] ?? null)
                ? $runtime['bootstraps']
                : [];
            $requiredDirectories = [
                'config',
                'application',
                $standardLayout['shared'],
                $standardLayout['shared_i18n'],
                $standardLayout['shared_i18n'] . '/default',
                $standardLayout['shared_i18n'] . '/modules',
                $standardLayout['front'],
                $standardLayout['front_modules'],
                $standardLayout['front'] . '/default',
                $standardLayout['front'] . '/default/layouts',
                $standardLayout['front'] . '/default/local',
                $standardLayout['front'] . '/default/templates',
                $standardLayout['back'],
                $standardLayout['back_modules'],
                'www',
                'www/asset',
                'var/logs',
                'var/profiler',
            ];
            $requiredFiles = [
                'config/site.json',
                'config/routes.json',
                $fsmRelative,
                'config/acl.json',
                'config/sso.json',
                $this->safeRelative((string) ($runtime['file'] ?? '')),
                $this->safeRelative((string) ($bootstraps['front'] ?? '')),
                $this->safeRelative((string) ($bootstraps['back'] ?? '')),
                $standardLayout['front'] . '/default/layouts/layout.score',
                'www/index.php',
            ];
        } else {
            $requiredDirectories = [
                'config',
                'application',
                'application/default',
                'application/default/local',
                'www',
                'var/logs',
                'var/profiler',
            ];
            $requiredFiles = [
                'config/site.json',
                'config/routes.json',
                $fsmRelative,
                'config/acl.json',
                'config/sso.json',
                'application/default/Application.php',
                'application/default/ApplicationInterface.php',
                'application/default/bootstrap.php',
                'www/index.php',
            ];
            if ($surface === 'frontend') {
                $requiredDirectories[] = 'application/default/layouts';
                $requiredDirectories[] = 'application/default/templates';
                $requiredDirectories[] = 'www/asset';
                $requiredFiles[] = 'application/default/layouts/layout.score';
            } else {
                $requiredDirectories[] = 'application/api';
            }
        }

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
                'OPUS_SITE_REQUIRED_PATH_MISSING:' . implode(',', $missing)
            );
        }
        if (is_dir($siteRoot . '/application/states')) {
            throw new OpusConsoleException(
                'OPUS_SITE_FORBIDDEN_STATES_DIRECTORY'
            );
        }

        $routes = $this->loader->read($siteRoot . '/config/routes.json');
        $fsm = $this->loader->read($siteRoot . '/' . $fsmRelative);
        $acl = $this->loader->read($siteRoot . '/config/acl.json');
        $sso = $this->loader->read($siteRoot . '/config/sso.json');

        if (($site['site_id'] ?? null) !== $siteId) {
            throw new OpusConsoleException('OPUS_SITE_CONFIG_ID_MISMATCH');
        }
        if (($site['contract'] ?? null) !== 'OPUS_SITE_STANDARD_CONTRACT_CORE') {
            throw new OpusConsoleException(
                'OPUS_SITE_STANDARD_CONTRACT_INVALID'
            );
        }
        $role = (string) ($site['role'] ?? '');
        if (!in_array(
            $role,
            ['generated-opus-application', 'standard-opus-application'],
            true
        )) {
            throw new OpusConsoleException('OPUS_SITE_ROLE_INVALID');
        }
        $profile = null;
        if ($role === 'generated-opus-application') {
            $profileConfig = is_array($site['application_profile'] ?? null)
                ? $site['application_profile']
                : [];
            if (($profileConfig['contract'] ?? null)
                !== 'OPUS_APPLICATION_PROFILE_V1') {
                throw new OpusConsoleException(
                    'OPUS_APPLICATION_PROFILE_CONTRACT_INVALID'
                );
            }
            $profile = $this->applicationProfile(
                (string) ($profileConfig['type'] ?? '')
            );
            if (($site['kind'] ?? null) !== $profile) {
                throw new OpusConsoleException(
                    'OPUS_APPLICATION_PROFILE_KIND_MISMATCH'
                );
            }
        }

        if (($site['dispatch_model'] ?? null) !== 'fsm-module-first') {
            throw new OpusConsoleException(
                'OPUS_SITE_DISPATCH_MODEL_INVALID'
            );
        }
        $expectedDefaultRoot = $standardLayout !== null
            ? $standardLayout['front'] . '/default'
            : 'application/default';
        if (($site['application_root'] ?? null) !== 'application'
            || ($site['default_root'] ?? null) !== $expectedDefaultRoot
            || ($site['public_root'] ?? null) !== 'www') {
            throw new OpusConsoleException('OPUS_SITE_ROOT_CONTRACT_INVALID');
        }

        $routeEntries = $this->routeEntries($routes, $role);
        $fsmContract = (string) ($fsm['contract'] ?? '');
        if (preg_match('/^[A-Z][A-Z0-9_]*_FSM_V[0-9]+$/', $fsmContract) !== 1
            || !is_array($fsm['states'] ?? null)
            || $fsm['states'] === []) {
            throw new OpusConsoleException('OPUS_SITE_FSM_CONTRACT_INVALID');
        }

        $aclContract = (string) ($acl['contract'] ?? '');
        if (!in_array(
            $aclContract,
            ['OPUS_GENERATED_APPLICATION_ACL_V1', 'OPUS_ACL_POLICY_V1'],
            true
        ) || ($acl['default'] ?? null) !== 'deny') {
            throw new OpusConsoleException('OPUS_SITE_ACL_CONTRACT_INVALID');
        }

        $ssoContract = (string) ($sso['contract'] ?? '');
        if (!in_array(
            $ssoContract,
            ['OPUS_GENERATED_APPLICATION_SSO_V1', 'OPUS_SSO_CONFIGURATION_V1'],
            true
        )) {
            throw new OpusConsoleException('OPUS_SITE_SSO_CONTRACT_INVALID');
        }

        $modules = $this->modules($fsm);
        $moduleRoot = $standardLayout !== null
            ? $standardLayout['front_modules']
            : 'application';
        foreach ($modules as $module) {
            if (!is_dir($siteRoot . '/' . $moduleRoot . '/' . $module)) {
                throw new OpusConsoleException(
                    'OPUS_SITE_MODULE_DIRECTORY_MISSING:' . $module
                );
            }
        }

        if ($role === 'generated-opus-application') {
            foreach ($routeEntries as $route) {
                $module = $this->identifier(
                    (string) ($route['module'] ?? $route['state'] ?? ''),
                    'OPUS_SITE_ROUTE_MODULE_INVALID'
                );
                if (!in_array($module, $modules, true)) {
                    throw new OpusConsoleException(
                        'OPUS_SITE_ROUTE_MODULE_UNKNOWN:' . $module
                    );
                }
                foreach (['template', 'view'] as $field) {
                    $relative = $this->safeRelative(
                        (string) ($route[$field] ?? '')
                    );
                    if (!$this->file->exists(
                        $siteRoot . '/application/' . $relative
                    )) {
                        throw new OpusConsoleException(
                            'OPUS_SITE_ROUTE_' . strtoupper($field)
                            . '_MISSING:' . $relative
                        );
                    }
                }
            }
        }

        $this->assertSingletonRuntime($siteRoot, $site);

        return [
            'contract' => 'OPUS_CONSOLE_SITE_VALIDATE_RESULT_V1',
            'site_id' => $siteId,
            'valid' => true,
            'routes' => count($routeEntries),
            'modules' => count($modules),
            'singleton' => true,
            'dispatch_model' => 'fsm-module-first',
            'role' => $role,
            'profile' => $profile,
            'fsm' => $fsmRelative,
        ];
    }

    public function addLanguage(string $siteId, string $locale, bool $write): array
    {
        $siteId = $this->siteId($siteId);
        $locale = $this->locale($locale);
        $siteRoot = $this->siteRoot($siteId);
        $siteConfigFile = $siteRoot . '/config/site.json';
        $site = $this->loader->read($siteConfigFile);
        $fsmFile = $siteRoot . '/' . $this->fsmRelativePath($site);
        $fsm = $this->loader->read($fsmFile);
        $locales = is_array($site['locales'] ?? null)
            ? array_values(array_filter($site['locales'], 'is_string'))
            : [];
        if (!in_array($locale, $locales, true)) {
            $locales[] = $locale;
        }

        $layout = $this->standardLayout($site);
        $applicationRoot = $layout !== null
            ? $siteRoot . '/' . $layout['shared_i18n']
            : $siteRoot . '/application';
        $targets = [
            $applicationRoot . ($layout !== null ? '/default/' : '/default/local/')
                . $locale . '.json' => 'default',
        ];
        foreach ($this->modules($fsm) as $module) {
            $targets[$applicationRoot
                . ($layout !== null ? '/modules/' : '/')
                . $module
                . ($layout !== null ? '/' : '/local/')
                . $locale
                . '.json'] = $module;
        }

        if ($write) {
            $site['locales'] = array_values(array_unique($locales));
            $this->loader->writeJson($siteConfigFile, $site);
            foreach ($targets as $target => $scope) {
                if ($this->file->exists($target)) {
                    continue;
                }
                $source = $this->catalogSource($siteRoot, $scope, $site);
                $messages = [];
                foreach (array_keys((array) ($source['messages'] ?? [])) as $key) {
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
            'contract' => 'OPUS_CONSOLE_LANGUAGE_ADD_RESULT_V1',
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
        $site = $this->loader->read($siteRoot . '/config/site.json');
        $registry = $this->loader->read($siteRoot . '/config/routes.json');
        $routes = $this->routeEntries(
            $registry,
            (string) ($site['role'] ?? '')
        );

        return [
            'contract' => 'OPUS_CONSOLE_ROUTE_LIST_RESULT_V1',
            'site_id' => $siteId,
            'routes' => $routes,
            'route_count' => count($routes),
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
        $moduleId = $this->identifier($moduleId, 'OPUS_PAGE_MODULE_ID_INVALID');
        $pageId = $this->identifier($pageId, 'OPUS_PAGE_ID_INVALID');
        $path = $this->routePath($path);
        $title = trim($title) !== '' ? trim($title) : ucfirst(str_replace('-', ' ', $pageId));

        $siteRoot = $this->siteRoot($siteId);
        $site = $this->loader->read($siteRoot . '/config/site.json');
        if (($site['role'] ?? null) !== 'generated-opus-application') {
            throw new OpusConsoleException(
                'OPUS_PAGE_COMMAND_REQUIRES_GENERATED_SITE'
            );
        }
        $fsm = $this->loader->read(
            $siteRoot . '/' . $this->fsmRelativePath($site)
        );
        if (!in_array($moduleId, $this->modules($fsm), true)) {
            throw new OpusConsoleException('OPUS_PAGE_MODULE_UNKNOWN:' . $moduleId);
        }

        $routesFile = $siteRoot . '/config/routes.json';
        $routesConfig = $this->loader->read($routesFile);
        $routes = is_array($routesConfig['routes'] ?? null) ? $routesConfig['routes'] : [];
        $routeId = $moduleId . '.' . $pageId;

        foreach ($routes as $route) {
            if (is_array($route)
                && (($route['id'] ?? null) === $routeId || ($route['path'] ?? null) === $path)) {
                throw new OpusConsoleException('OPUS_PAGE_ROUTE_ALREADY_EXISTS:' . $routeId);
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
            'template' => $moduleId . '/templates/' . $pageId . '.score',
            'view' => $moduleId . '/views/' . $pageId . '.php',
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
            $routesConfig['routes'] = array_values($routes);
            $this->loader->writeJson($routesFile, $routesConfig);
            $this->file->writeAtomic(
                $siteRoot . '/application/' . $moduleId . '/templates/' . $pageId . '.score',
                "<section class=\"opus-card\"><h2>{{ page.title }}</h2><p>{{ page.subtitle }}</p></section>\n"
            );
            $view = "<?php\ndeclare(strict_types=1);\n\nreturn [\n"
                . "    'module' => " . var_export($moduleId, true) . ",\n"
                . "    'page' => ['title' => '', 'subtitle' => ''],\n];\n";
            $this->file->writeAtomic(
                $siteRoot . '/application/' . $moduleId . '/views/' . $pageId . '.php',
                $view
            );
            foreach ((array) ($site['locales'] ?? []) as $locale) {
                if (!is_string($locale) || $locale === '') {
                    continue;
                }
                $catalogFile = $siteRoot . '/application/' . $moduleId . '/local/' . $locale . '.json';
                $catalog = $this->loader->read($catalogFile);
                $messages = is_array($catalog['messages'] ?? null) ? $catalog['messages'] : [];
                $messages[$titleKey] = $title;
                $messages[$subtitleKey] = '[[' . $subtitleKey . ']]';
                $catalog['messages'] = $messages;
                $this->loader->writeJson($catalogFile, $catalog);
            }
        }

        return [
            'contract' => 'OPUS_CONSOLE_PAGE_CREATE_RESULT_V1',
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
        $siteId = $this->siteId($siteId);
        $moduleId = $this->identifier($moduleId, 'OPUS_RUBRIC_MODULE_ID_INVALID');
        $path = $this->routePath($path);
        $title = trim($title) !== '' ? trim($title) : ucfirst(str_replace('-', ' ', $moduleId));

        $siteRoot = $this->siteRoot($siteId);
        $site = $this->loader->read($siteRoot . '/config/site.json');
        if (($site['role'] ?? null) !== 'generated-opus-application') {
            throw new OpusConsoleException(
                'OPUS_RUBRIC_COMMAND_REQUIRES_GENERATED_SITE'
            );
        }
        $fsm = $this->loader->read(
            $siteRoot . '/' . $this->fsmRelativePath($site)
        );
        if (!in_array($moduleId, $this->modules($fsm), true)) {
            throw new OpusConsoleException('OPUS_RUBRIC_MODULE_UNKNOWN:' . $moduleId);
        }

        $rubricsFile = $siteRoot . '/config/rubrics.json';
        $config = $this->loader->read($rubricsFile);
        $rubrics = is_array($config['rubrics'] ?? null) ? $config['rubrics'] : [];

        foreach ($rubrics as $rubric) {
            if (is_array($rubric)
                && (($rubric['module'] ?? $rubric['state'] ?? null) === $moduleId
                    || ($rubric['path'] ?? null) === $path)) {
                throw new OpusConsoleException('OPUS_RUBRIC_ALREADY_EXISTS:' . $moduleId);
            }
        }

        $rubric = [
            'module' => $moduleId,
            'route' => $moduleId . '.index',
            'path' => $path,
            'title' => $title,
        ];

        if ($write) {
            $rubrics[] = $rubric;
            $config['rubrics'] = array_values($rubrics);
            $this->loader->writeJson($rubricsFile, $config);
        }

        return [
            'contract' => 'OPUS_CONSOLE_RUBRIC_CREATE_RESULT_V1',
            'site_id' => $siteId,
            'rubric' => $rubric,
            'mode' => $write ? 'write' : 'preview',
            'written' => $write,
        ];
    }

    public function devServer(
        string $applicationId,
        string $host,
        int $port
    ): int {
        $applicationId = $this->siteId($applicationId);
        if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new OpusConsoleException(
                'OPUS_DEV_SERVER_HOST_NOT_LOCAL:' . $host
            );
        }
        if ($port < 1024 || $port > 65535) {
            throw new OpusConsoleException('OPUS_DEV_SERVER_PORT_INVALID');
        }

        $siteRoot = $this->siteRoot($applicationId);
        $site = $this->loader->read($siteRoot . '/config/site.json');
        $development = is_array($site['development_server'] ?? null)
            ? $site['development_server']
            : [];
        if (($development['contract'] ?? null)
            !== 'OPUS_DEVELOPMENT_SERVER_V1'
            || ($development['enabled'] ?? false) !== true) {
            throw new OpusConsoleException(
                'OPUS_DEV_SERVER_NOT_ENABLED:' . $applicationId
            );
        }

        $publicRoot = $siteRoot . '/www';
        $router = $publicRoot . '/index.php';
        if (!is_dir($publicRoot) || !$this->file->exists($router)) {
            throw new OpusConsoleException(
                'OPUS_DEV_SERVER_PUBLIC_ROOT_MISSING'
            );
        }

        $registry = $this->developmentServerRegistry($development);
        $environment = getenv();
        $environment = is_array($environment) ? $environment : [];
        $environment = $this->developmentEnvironment(
            $development,
            $environment,
            $registry
        );
        $registry->register($applicationId, $host, $port);
        try {
            $this->recordDevelopmentServerStart(
                $applicationId,
                $siteRoot,
                $development,
                $host,
                $port
            );
            $process = proc_open(
                [
                    PHP_BINARY,
                    '-S',
                    $host . ':' . $port,
                    '-t',
                    $publicRoot,
                    $router,
                ],
                [0 => STDIN, 1 => STDOUT, 2 => STDERR],
                $pipes,
                $this->opusRoot,
                $environment,
                ['bypass_shell' => true]
            );
            if (!is_resource($process)) {
                throw new OpusConsoleException(
                    'OPUS_DEV_SERVER_PROCESS_START_FAILED'
                );
            }
            return (int) proc_close($process);
        } finally {
            $registry->unregister($applicationId);
        }
    }

    public function serve(
        string $siteId,
        string $host,
        int $port,
        string $mode = 'front'
    ): int {
        $siteId = $this->siteId($siteId);
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['front', 'back'], true)) {
            throw new OpusConsoleException(
                'OPUS_SERVE_RUNTIME_MODE_INVALID:' . $mode
            );
        }
        if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new OpusConsoleException('OPUS_SERVE_HOST_NOT_LOCAL');
        }
        if ($port < 1024 || $port > 65535) {
            throw new OpusConsoleException('OPUS_SERVE_PORT_INVALID');
        }

        $siteRoot = $this->siteRoot($siteId);
        $publicRoot = $siteRoot . '/www';
        $router = $publicRoot . '/index.php';
        if (!is_dir($publicRoot) || !$this->file->exists($router)) {
            throw new OpusConsoleException('OPUS_SERVE_PUBLIC_ROOT_MISSING');
        }

        $command = [PHP_BINARY, '-S', $host . ':' . $port, '-t', $publicRoot, $router];
        $environment = getenv();
        $environment = is_array($environment) ? $environment : [];
        $environment = $this->runtimeEnvironment(
            $siteRoot,
            $environment
        );
        $environment['OPUS_APPLICATION_RUNTIME_MODE'] = $mode;
        $this->recordRuntimeStart($siteRoot, $mode, $host, $port);
        $descriptors = [0 => STDIN, 1 => STDOUT, 2 => STDERR];
        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            $this->opusRoot,
            $environment,
            ['bypass_shell' => true]
        );
        if (!is_resource($process)) {
            throw new OpusConsoleException('OPUS_SERVE_PROCESS_START_FAILED');
        }

        return (int) proc_close($process);
    }

    /** @param array<string,mixed> $development */
    private function developmentServerRegistry(
        array $development
    ): DevelopmentServerRegistryInterface {
        $registry = is_array($development['registry'] ?? null)
            ? $development['registry']
            : [];
        if (($registry['contract'] ?? null)
            !== DevelopmentServerRegistry::CONTRACT) {
            throw new OpusConsoleException(
                'OPUS_DEV_SERVER_REGISTRY_CONFIG_INVALID'
            );
        }
        $relative = $this->safeRelative((string) ($registry['file'] ?? ''));
        return DevelopmentServerRegistry::forFile(
            $this->opusRoot . '/' . $relative
        );
    }

    /**
     * @param array<string,mixed> $development
     * @param array<string,string> $environment
     * @return array<string,string>
     */
    private function developmentEnvironment(
        array $development,
        array $environment,
        DevelopmentServerRegistryInterface $registry
    ): array {
        $peers = is_array($development['peers'] ?? null)
            ? $development['peers']
            : [];
        foreach ($peers as $environmentName => $peer) {
            if (!is_string($environmentName)
                || preg_match('/^[A-Z][A-Z0-9_]{2,127}$/', $environmentName) !== 1
                || !is_array($peer)) {
                throw new OpusConsoleException(
                    'OPUS_DEV_SERVER_PEER_BINDING_INVALID'
                );
            }
            $environment[$environmentName] = $registry->endpoint(
                (string) ($peer['application_id'] ?? ''),
                (string) ($peer['path'] ?? '')
            );
        }

        $policy = is_array($development['secrets'] ?? null)
            ? $development['secrets']
            : null;
        if ($policy === null) {
            return $environment;
        }
        if (($policy['contract'] ?? null)
            !== 'OPUS_RUNTIME_SECRET_BINDING_V1'
            || ($policy['scope'] ?? null) !== 'opus-root') {
            throw new OpusConsoleException(
                'OPUS_DEV_SERVER_SECRET_BINDING_INVALID'
            );
        }
        $store = $this->safeRelative((string) ($policy['store'] ?? ''));
        $bindings = is_array($policy['bindings'] ?? null)
            ? $policy['bindings']
            : [];
        $resolved = RuntimeSecretStore::forPath(
            $this->opusRoot . '/' . $store
        )->ensure($bindings);
        foreach ($resolved as $name => $value) {
            $environment[$name] = $value;
        }
        return $environment;
    }

    /** @param array<string,mixed> $development */
    private function recordDevelopmentServerStart(
        string $applicationId,
        string $siteRoot,
        array $development,
        string $host,
        int $port
    ): void {
        $diagnostics = is_array($development['diagnostics'] ?? null)
            ? $development['diagnostics']
            : [];
        $relativeLog = $this->safeRelative((string) (
            $diagnostics['log'] ?? ''
        ));
        $relativeProfiler = $this->safeRelative((string) (
            $diagnostics['profiler'] ?? ''
        ));
        $absoluteLog = $siteRoot . '/' . $relativeLog;
        $profiler = new Profiler($siteRoot . '/' . $relativeProfiler);
        $trace = $profiler->start();
        $traceId = $trace->getTraceId();
        $context = [
            'application_id' => $applicationId,
            'server_type' => 'development',
            'host' => $host,
            'port' => $port,
        ];
        (new Logger(dirname($absoluteLog), basename($absoluteLog)))->info(
            'opus.dev-server',
            'development_server.starting',
            $context,
            $traceId
        );
        $profiler->event(
            'opus.dev-server',
            'development_server.starting',
            $context
        );
        $profiler->stop([
            'component' => self::class,
            'status' => 'starting',
            'application_id' => $applicationId,
            'server_type' => 'development',
        ]);
    }

    /**
     * @param array<string,string> $environment
     * @return array<string,string>
     */
    private function runtimeEnvironment(
        string $siteRoot,
        array $environment
    ): array {
        $site = $this->loader->read($siteRoot . '/config/site.json');
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
        $resolved = RuntimeSecretStore::forPath(
            $siteRoot . '/' . $store
        )->ensure($bindings);
        foreach ($resolved as $name => $value) {
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
        $site = $this->loader->read($siteRoot . '/config/site.json');
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

    /** @param array<string,mixed> $site */
    private function applicationSurface(array $site): string
    {
        $surface = is_array($site['application_surface'] ?? null)
            ? $site['application_surface']
            : null;
        if ($surface === null) {
            return 'frontend';
        }
        if (($surface['contract'] ?? null)
            !== 'OPUS_APPLICATION_SURFACE_V1') {
            throw new OpusConsoleException(
                'OPUS_APPLICATION_SURFACE_CONTRACT_INVALID'
            );
        }
        $type = strtolower(trim((string) ($surface['type'] ?? '')));
        if (!in_array($type, ['frontend', 'backend'], true)) {
            throw new OpusConsoleException(
                'OPUS_APPLICATION_SURFACE_TYPE_INVALID:' . $type
            );
        }
        return $type;
    }

    /**
     * @param array<string,mixed> $site
     * @return array{
     *   shared:string,
     *   front:string,
     *   back:string,
     *   front_modules:string,
     *   back_modules:string,
     *   shared_i18n:string
     * }|null
     */
    private function standardLayout(array $site): ?array
    {
        $layers = is_array($site['application_layers'] ?? null)
            ? $site['application_layers']
            : null;
        if ($layers === null) {
            return null;
        }
        if (($layers['contract'] ?? null)
            !== 'OPUS_APPLICATION_LAYER_LAYOUT_V1'
            || ($layers['full_directory_forbidden'] ?? false) !== true
            || ($layers['fullstack'] ?? null) !== 'composition') {
            throw new OpusConsoleException(
                'OPUS_APPLICATION_LAYER_LAYOUT_CONTRACT_INVALID'
            );
        }
        $modes = is_array($site['runtime_modes'] ?? null)
            ? array_values(array_filter($site['runtime_modes'], 'is_string'))
            : [];
        sort($modes, SORT_STRING);
        if ($modes !== ['back', 'front']) {
            throw new OpusConsoleException(
                'OPUS_APPLICATION_LAYOUT_RUNTIME_MODES_INVALID'
            );
        }
        $shared = $this->safeRelative((string) ($layers['shared'] ?? ''));
        $front = $this->safeRelative((string) ($layers['front'] ?? ''));
        $back = $this->safeRelative((string) ($layers['back'] ?? ''));
        return [
            'shared' => $shared,
            'front' => $front,
            'back' => $back,
            'front_modules' => $front . '/modules',
            'back_modules' => $back . '/modules',
            'shared_i18n' => $shared . '/i18n',
        ];
    }

    private function applicationProfile(string $profile): string
    {
        $profile = strtolower(trim($profile));
        if (!in_array($profile, SiteScaffoldPlan::profiles(), true)) {
            throw new OpusConsoleException(
                'OPUS_APPLICATION_PROFILE_INVALID:' . $profile
            );
        }
        return $profile;
    }

    /** @param array<string,mixed> $site */
    private function fsmRelativePath(array $site): string
    {
        $navigation = is_array($site['navigation'] ?? null)
            ? $site['navigation']
            : [];
        $relative = (string) (
            $navigation['fsm']
            ?? $site['application_fsm']
            ?? 'config/application.fsm.json'
        );
        return $this->safeRelative($relative);
    }

    /**
     * @param array<string,mixed> $routes
     * @return list<array<string,mixed>>
     */
    private function routeEntries(array $routes, string $role): array
    {
        $entries = $routes['routes'] ?? null;

        if ($role === 'generated-opus-application') {
            if (!is_array($entries)
                || !array_is_list($entries)
                || ($routes['dispatch_model'] ?? null) !== 'fsm-module-first') {
                throw new OpusConsoleException(
                    'OPUS_SITE_ROUTE_REGISTRY_INVALID'
                );
            }
            return array_values(array_filter($entries, 'is_array'));
        }

        if (($routes['contract'] ?? null) !== 'OPUS_SIGNAL_ROUTES_V2'
            || !is_array($entries)
            || array_is_list($entries)) {
            throw new OpusConsoleException(
                'OPUS_SITE_SIGNAL_ROUTE_REGISTRY_INVALID'
            );
        }

        $result = [];
        foreach ((array) ($routes['system_routes'] ?? []) as $path => $event) {
            if (!is_string($path) || !is_string($event)
                || trim($path) === '' || trim($event) === '') {
                throw new OpusConsoleException(
                    'OPUS_SITE_SIGNAL_ROUTE_INVALID'
                );
            }
            $result[] = ['path' => $path, 'event' => $event];
        }
        foreach ($entries as $path => $event) {
            if (!is_string($path) || !is_string($event)
                || trim($path) === '' || trim($event) === '') {
                throw new OpusConsoleException(
                    'OPUS_SITE_SIGNAL_ROUTE_INVALID'
                );
            }
            $result[] = ['path' => $path, 'event' => $event];
        }
        return $result;
    }

    /** @param array<string,mixed> $fsm @return list<string> */
    private function modules(array $fsm): array
    {
        $modules = [];
        foreach ((array) ($fsm['states'] ?? []) as $state) {
            if (!is_array($state)) {
                throw new OpusConsoleException('OPUS_SITE_FSM_STATE_INVALID');
            }
            $module = $this->identifier(
                (string) ($state['module'] ?? $state['id'] ?? ''),
                'OPUS_SITE_FSM_MODULE_INVALID'
            );
            if ($module === 'default') {
                throw new OpusConsoleException('OPUS_SITE_FSM_DEFAULT_MODULE_FORBIDDEN');
            }
            $modules[$module] = true;
        }
        if ($modules === []) {
            throw new OpusConsoleException('OPUS_SITE_FSM_MODULES_MISSING');
        }
        return array_keys($modules);
    }

    /** @param array<string,mixed> $site */
    private function assertSingletonRuntime(string $siteRoot, array $site): void
    {
        $runtime = is_array($site['runtime'] ?? null) ? $site['runtime'] : [];
        $contract = (string) ($runtime['contract'] ?? '');
        if (!in_array($contract, [
            'OPUS_APPLICATION_SINGLETON_V1',
            'OPUS_APPLICATION_SINGLETON_V2',
        ], true)) {
            throw new OpusConsoleException(
                'OPUS_APPLICATION_SINGLETON_CONTRACT_INVALID:contract'
            );
        }
        foreach ([
            'architecture' => 'singleton',
            'factory' => 'instance',
            'runner' => 'run',
        ] as $key => $value) {
            if (($runtime[$key] ?? null) !== $value) {
                throw new OpusConsoleException(
                    'OPUS_APPLICATION_SINGLETON_CONTRACT_INVALID:' . $key
                );
            }
        }

        $class = trim((string) ($runtime['class'] ?? ''));
        $classFile = $this->safeRelative((string) ($runtime['file'] ?? ''));
        $entrypoint = $this->safeRelative((string) ($runtime['entrypoint'] ?? ''));
        if ($class === ''
            || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class) !== 1) {
            throw new OpusConsoleException(
                'OPUS_APPLICATION_SINGLETON_CLASS_INVALID'
            );
        }

        $bootstraps = [];
        if ($contract === 'OPUS_APPLICATION_SINGLETON_V1') {
            $bootstraps['default'] = $this->safeRelative(
                (string) ($runtime['bootstrap'] ?? '')
            );
        } else {
            $declared = is_array($runtime['bootstraps'] ?? null)
                ? $runtime['bootstraps']
                : [];
            foreach (['front', 'back'] as $mode) {
                $bootstraps[$mode] = $this->safeRelative(
                    (string) ($declared[$mode] ?? '')
                );
            }
        }

        foreach ([$classFile, $entrypoint, ...array_values($bootstraps)] as $relative) {
            if (!$this->file->exists($siteRoot . '/' . $relative)) {
                throw new OpusConsoleException(
                    'OPUS_APPLICATION_SINGLETON_FILE_MISSING:' . $relative
                );
            }
        }

        $classSource = $this->file->read($siteRoot . '/' . $classFile);
        $entrySource = $this->file->read($siteRoot . '/' . $entrypoint);
        $quotedClass = preg_quote($class, '/');
        $checks = [
            preg_match('/final\s+class\s+' . $quotedClass . '\b/', $classSource) === 1,
            str_contains($classSource, 'private static ?self $instance'),
            preg_match('/private\s+function\s+__construct\s*\(/', $classSource) === 1,
            preg_match('/public\s+static\s+function\s+instance\s*\(/', $classSource) === 1,
            preg_match('/public\s+function\s+run\s*\(/', $classSource) === 1,
            !str_contains($entrySource, 'echo '),
            !str_contains($entrySource, '<html'),
        ];
        foreach ($bootstraps as $relative) {
            $bootstrapSource = $this->file->read($siteRoot . '/' . $relative);
            $checks[] = str_contains($bootstrapSource, $class . '::instance(');
            $checks[] = str_contains($bootstrapSource, ')->run();');
            $checks[] = str_contains(
                str_replace('\\', '/', $entrySource),
                str_replace('\\', '/', $relative)
            );
        }
        if (in_array(false, $checks, true)) {
            throw new OpusConsoleException(
                'OPUS_APPLICATION_SINGLETON_IMPLEMENTATION_INVALID'
            );
        }
    }

    /** @param array<string,mixed> $site @return array<string,mixed> */
    private function catalogSource(string $siteRoot, string $scope, array $site): array
    {
        $default = trim((string) ($site['default_locale'] ?? ''));
        if ($default === '') {
            throw new OpusConsoleException('OPUS_I18N_DEFAULT_LOCALE_MISSING');
        }
        $layout = $this->standardLayout($site);
        $applicationRoot = $layout !== null
            ? $siteRoot . '/' . $layout['shared_i18n']
            : $siteRoot . '/application';
        $directory = $scope === 'default'
            ? $applicationRoot . ($layout !== null ? '/default' : '/default/local')
            : $applicationRoot
                . ($layout !== null ? '/modules/' : '/')
                . $scope
                . ($layout !== null ? '' : '/local');
        return $this->loader->read($directory . '/' . $default . '.json');
    }

    private function siteRoot(string $siteId): string
    {
        $root = $this->opusRoot . '/sites/' . $siteId;
        if (!is_dir($root)) {
            throw new OpusConsoleException('OPUS_SITE_NOT_FOUND:' . $siteId);
        }
        return $root;
    }

    private function siteId(string $value): string
    {
        return $this->identifier($value, 'OPUS_SITE_ID_INVALID');
    }

    private function identifier(string $value, string $error): string
    {
        $value = trim(strtolower($value));
        if (preg_match('/^[a-z][a-z0-9-]*$/', $value) !== 1) {
            throw new OpusConsoleException($error . ':' . $value);
        }
        return $value;
    }

    private function locale(string $value): string
    {
        $value = str_replace('_', '-', trim($value));
        if (preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})?$/', $value) !== 1) {
            throw new OpusConsoleException('OPUS_LOCALE_INVALID:' . $value);
        }
        $parts = explode('-', $value, 2);
        return strtolower($parts[0]) . (isset($parts[1]) ? '-' . strtoupper($parts[1]) : '');
    }

    private function routePath(string $value): string
    {
        $value = '/' . ltrim(trim($value), '/');
        if (preg_match('#^/[A-Za-z0-9/_-]*$#', $value) !== 1 || str_contains($value, '..')) {
            throw new OpusConsoleException('OPUS_ROUTE_PATH_INVALID:' . $value);
        }
        return $value;
    }

    private function safeRelative(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            throw new OpusConsoleException('OPUS_RELATIVE_PATH_INVALID:' . $path);
        }
        return $path;
    }

    private function relative(string $path): string
    {
        $prefix = $this->opusRoot . '/';
        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }
}
