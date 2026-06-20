<?php
declare(strict_types=1);

namespace Opus\Console\Command;

use Opus\Console\OpusConsoleException;

/**
 * Lists configured routes for an existing OPUS site.
 *
 * Public contract:
 * - read-only inspection command;
 * - reads application/config/routes.json and application/config/modules.json;
 * - prints route -> module -> template mappings;
 * - fails explicitly on missing/invalid site, route, module, or template contracts;
 * - does not create, modify, or repair project files.
 */
final class ListRoutesCommand implements OpusConsoleCommandInterface
{
    public function __construct(private readonly string $opusRoot)
    {
    }

    public function name(): string
    {
        return 'list:routes';
    }

    /**
     * @param list<string> $arguments
     */
    public function run(array $arguments): int
    {
        $siteId = (string)($arguments[0] ?? '');
        if ($siteId === '') {
            throw new OpusConsoleException('OPUS_LIST_ROUTES_MISSING_SITE_ID');
        }

        if (count($arguments) > 1) {
            throw new OpusConsoleException('OPUS_LIST_ROUTES_TOO_MANY_ARGUMENTS');
        }

        if (!preg_match('/^[a-z][a-z0-9-]*$/', $siteId)) {
            throw new OpusConsoleException('OPUS_LIST_ROUTES_INVALID_SITE_ID: ' . $siteId);
        }

        $siteRoot = $this->absolutePath('sites/' . $siteId);
        if (!is_dir($siteRoot)) {
            throw new OpusConsoleException('OPUS_LIST_ROUTES_SITE_NOT_FOUND: sites/' . $siteId);
        }

        $modulesConfig = $this->readJson($siteRoot, 'application/config/modules.json', 'OPUS_LIST_ROUTES_MODULES_JSON_INVALID');
        $routesConfig = $this->readJson($siteRoot, 'application/config/routes.json', 'OPUS_LIST_ROUTES_ROUTES_JSON_INVALID');

        $registeredModuleIds = [];
        $modules = $modulesConfig['modules'] ?? [];
        if (!is_array($modules)) {
            throw new OpusConsoleException('OPUS_LIST_ROUTES_MODULES_CONTRACT_INVALID');
        }

        foreach ($modules as $module) {
            if (!is_array($module)) {
                throw new OpusConsoleException('OPUS_LIST_ROUTES_MODULE_ENTRY_INVALID');
            }
            $moduleId = (string)($module['id'] ?? '');
            if ($moduleId === '') {
                throw new OpusConsoleException('OPUS_LIST_ROUTES_MODULE_ID_MISSING');
            }
            $registeredModuleIds[$moduleId] = true;
        }

        $routes = $routesConfig['routes'] ?? [];
        if (!is_array($routes) || $routes === []) {
            throw new OpusConsoleException('OPUS_LIST_ROUTES_ROUTES_CONTRACT_INVALID');
        }

        echo "OPUS_LIST_ROUTES: {$siteId}\n";
        foreach ($routes as $route) {
            if (!is_array($route)) {
                throw new OpusConsoleException('OPUS_LIST_ROUTES_ROUTE_ENTRY_INVALID');
            }

            $routeId = (string)($route['id'] ?? '');
            $path = (string)($route['path'] ?? '');
            $moduleId = (string)($route['module'] ?? '');
            $template = (string)($route['template'] ?? '');

            if ($routeId === '') {
                throw new OpusConsoleException('OPUS_LIST_ROUTES_ROUTE_ID_MISSING');
            }
            if ($path === '' || $path[0] !== '/') {
                throw new OpusConsoleException('OPUS_LIST_ROUTES_ROUTE_PATH_INVALID: ' . $routeId);
            }
            if (!isset($registeredModuleIds[$moduleId])) {
                throw new OpusConsoleException('OPUS_LIST_ROUTES_ROUTE_MODULE_NOT_REGISTERED: ' . $routeId . ' -> ' . $moduleId);
            }
            if ($template === '') {
                throw new OpusConsoleException('OPUS_LIST_ROUTES_ROUTE_TEMPLATE_MISSING: ' . $routeId);
            }
            $this->requireFile($siteRoot, $template, 'OPUS_LIST_ROUTES_ROUTE_TEMPLATE_NOT_FOUND: ' . $routeId);

            echo '[ROUTE] ' . $routeId . ' ' . $path . ' -> ' . $moduleId . ' :: ' . $template . "\n";
        }

        return 0;
    }

    /**
     * @return array<string,mixed>
     */
    private function readJson(string $siteRoot, string $relativePath, string $errorCode): array
    {
        $path = $siteRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($path)) {
            throw new OpusConsoleException('OPUS_LIST_ROUTES_REQUIRED_FILE_MISSING: ' . $relativePath);
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new OpusConsoleException('OPUS_LIST_ROUTES_JSON_READ_FAILED: ' . $relativePath);
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new OpusConsoleException($errorCode . ': ' . $relativePath);
        }

        return $decoded;
    }

    private function requireFile(string $siteRoot, string $relativePath, string $errorCode): void
    {
        $path = $siteRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($path)) {
            throw new OpusConsoleException($errorCode . ': ' . $relativePath);
        }
    }

    private function absolutePath(string $relativePath): string
    {
        return $this->opusRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }
}
