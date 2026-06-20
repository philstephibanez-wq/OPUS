<?php
declare(strict_types=1);

namespace Opus\Console\Command;

use Opus\Console\OpusConsoleException;

/**
 * Internal support service for generated-site authoring commands.
 *
 * Internal contract:
 * - keeps filesystem mutations inside sites/<site-id>;
 * - centralizes JSON reads/writes and identifier validation;
 * - does not render HTML and does not replace the Score template engine;
 * - throws explicit OpusConsoleException errors on contract violations.
 */
final class SiteScaffoldCommandSupport
{
    public function __construct(private readonly string $opusRoot)
    {
    }

    public function siteRoot(string $siteId): string
    {
        $this->assertSiteId($siteId, 'OPUS_SITE_COMMAND_INVALID_SITE_ID');
        $siteRoot = $this->absolutePath('sites/' . $siteId);
        if (!is_dir($siteRoot)) {
            throw new OpusConsoleException('OPUS_SITE_COMMAND_SITE_NOT_FOUND: sites/' . $siteId);
        }
        return $siteRoot;
    }

    public function assertSiteId(string $siteId, string $errorCode): void
    {
        if ($siteId === '' || !preg_match('/^[a-z][a-z0-9-]*$/', $siteId)) {
            throw new OpusConsoleException($errorCode . ': ' . $siteId);
        }
    }

    public function assertModuleId(string $moduleId, string $errorCode): void
    {
        if ($moduleId === '' || !preg_match('/^[A-Z][A-Za-z0-9]*$/', $moduleId)) {
            throw new OpusConsoleException($errorCode . ': ' . $moduleId);
        }
    }

    public function assertPageId(string $pageId, string $errorCode): void
    {
        if ($pageId === '' || !preg_match('/^[a-z][a-z0-9-]*$/', $pageId)) {
            throw new OpusConsoleException($errorCode . ': ' . $pageId);
        }
    }

    public function assertRoutePath(string $path, string $errorCode): void
    {
        if ($path === '' || $path[0] !== '/' || str_contains($path, '..') || str_contains($path, '\\')) {
            throw new OpusConsoleException($errorCode . ': ' . $path);
        }
    }

    /**
     * @param list<string> $arguments
     */
    public function optionValue(array $arguments, string $name, ?string $default = null): ?string
    {
        $count = count($arguments);
        for ($i = 0; $i < $count; $i++) {
            if (($arguments[$i] ?? '') === $name) {
                $value = $arguments[$i + 1] ?? null;
                if ($value === null || str_starts_with($value, '--')) {
                    throw new OpusConsoleException('OPUS_SITE_COMMAND_OPTION_VALUE_MISSING: ' . $name);
                }
                return (string)$value;
            }
        }
        return $default;
    }

    /**
     * @param list<string> $arguments
     */
    public function hasFlag(array $arguments, string $name): bool
    {
        return in_array($name, $arguments, true);
    }

    /**
     * @param list<string> $arguments
     * @return list<string>
     */
    public function positionalArguments(array $arguments): array
    {
        $positionals = [];
        $count = count($arguments);
        for ($i = 0; $i < $count; $i++) {
            $arg = (string)$arguments[$i];
            if ($arg === '--write') {
                continue;
            }
            if ($arg === '--title') {
                $i++;
                continue;
            }
            if (str_starts_with($arg, '--')) {
                throw new OpusConsoleException('OPUS_SITE_COMMAND_UNKNOWN_OPTION: ' . $arg);
            }
            $positionals[] = $arg;
        }
        return $positionals;
    }

    public function requireWrite(bool $write, string $planMessage): void
    {
        if (!$write) {
            echo $planMessage . "\n";
            throw new OpusConsoleException('OPUS_SITE_COMMAND_WRITE_FLAG_REQUIRED');
        }
    }

    public function createModule(string $siteRoot, string $moduleId, string $title): void
    {
        $this->assertModuleId($moduleId, 'OPUS_CREATE_MODULE_INVALID_MODULE_ID');
        $moduleRoot = $siteRoot . DIRECTORY_SEPARATOR . $this->toPath('application/modules/' . $moduleId);
        if (is_dir($moduleRoot)) {
            throw new OpusConsoleException('OPUS_CREATE_MODULE_ALREADY_EXISTS: ' . $moduleId);
        }

        mkdir($moduleRoot . DIRECTORY_SEPARATOR . $this->toPath('templates/pages'), 0777, true);
        mkdir($moduleRoot . DIRECTORY_SEPARATOR . $this->toPath('templates/partials'), 0777, true);

        $this->writeText($moduleRoot . DIRECTORY_SEPARATOR . 'README.md', $this->moduleReadme($moduleId));
        $this->writeJson($moduleRoot . DIRECTORY_SEPARATOR . 'module.json', [
            'id' => $moduleId,
            'title' => $title,
            'enabled' => true,
            'templates' => [
                'layout' => 'templates/layout.score',
                'default' => 'templates/pages/index.score',
            ],
        ]);
        $this->writeText($moduleRoot . DIRECTORY_SEPARATOR . $this->toPath('templates/layout.score'), "[[ include:application/common/templates/layout.score ]]\n");
        $this->writeText($moduleRoot . DIRECTORY_SEPARATOR . $this->toPath('templates/pages/index.score'), $this->pageTemplate($moduleId, 'index', $title));

        $modulesPath = $siteRoot . DIRECTORY_SEPARATOR . $this->toPath('application/config/modules.json');
        $modules = $this->readJson($modulesPath, 'OPUS_CREATE_MODULE_MODULES_JSON_INVALID');
        $entries = $modules['modules'] ?? [];
        if (!is_array($entries)) {
            throw new OpusConsoleException('OPUS_CREATE_MODULE_MODULES_CONTRACT_INVALID');
        }
        foreach ($entries as $entry) {
            if (is_array($entry) && (($entry['id'] ?? '') === $moduleId)) {
                throw new OpusConsoleException('OPUS_CREATE_MODULE_ALREADY_REGISTERED: ' . $moduleId);
            }
        }
        $entries[] = ['id' => $moduleId, 'enabled' => true, 'title' => $title];
        $modules['modules'] = $entries;
        $this->writeJson($modulesPath, $modules);
        $this->upsertI18n($siteRoot, $moduleId, 'index', $title);
    }

    public function createPage(string $siteRoot, string $moduleId, string $pageId, string $path, string $title): void
    {
        $this->assertModuleId($moduleId, 'OPUS_CREATE_PAGE_INVALID_MODULE_ID');
        $this->assertPageId($pageId, 'OPUS_CREATE_PAGE_INVALID_PAGE_ID');
        $this->assertRoutePath($path, 'OPUS_CREATE_PAGE_INVALID_ROUTE_PATH');

        $moduleRoot = $siteRoot . DIRECTORY_SEPARATOR . $this->toPath('application/modules/' . $moduleId);
        if (!is_dir($moduleRoot)) {
            throw new OpusConsoleException('OPUS_CREATE_PAGE_MODULE_NOT_FOUND: ' . $moduleId);
        }

        $relativeTemplate = 'application/modules/' . $moduleId . '/templates/pages/' . $pageId . '.score';
        $absoluteTemplate = $siteRoot . DIRECTORY_SEPARATOR . $this->toPath($relativeTemplate);
        if (is_file($absoluteTemplate)) {
            throw new OpusConsoleException('OPUS_CREATE_PAGE_TEMPLATE_ALREADY_EXISTS: ' . $relativeTemplate);
        }

        $routeId = strtolower($moduleId) . '.' . $pageId;
        $this->assertRouteAvailable($siteRoot, $routeId, $path);

        $this->writeText($absoluteTemplate, $this->pageTemplate($moduleId, $pageId, $title));
        $this->appendRoute($siteRoot, $routeId, $path, $moduleId, $relativeTemplate);
        $this->upsertI18n($siteRoot, $moduleId, $pageId, $title);
    }

    public function assertRouteAvailable(string $siteRoot, string $routeId, string $path): void
    {
        $routesPath = $siteRoot . DIRECTORY_SEPARATOR . $this->toPath('application/config/routes.json');
        $routesConfig = $this->readJson($routesPath, 'OPUS_SITE_COMMAND_ROUTES_JSON_INVALID');
        $routes = $routesConfig['routes'] ?? [];
        if (!is_array($routes)) {
            throw new OpusConsoleException('OPUS_SITE_COMMAND_ROUTES_CONTRACT_INVALID');
        }
        foreach ($routes as $route) {
            if (!is_array($route)) {
                throw new OpusConsoleException('OPUS_SITE_COMMAND_ROUTE_ENTRY_INVALID');
            }
            if (($route['id'] ?? '') === $routeId) {
                throw new OpusConsoleException('OPUS_SITE_COMMAND_ROUTE_ALREADY_EXISTS: ' . $routeId);
            }
            if (($route['path'] ?? '') === $path) {
                throw new OpusConsoleException('OPUS_SITE_COMMAND_ROUTE_PATH_ALREADY_EXISTS: ' . $path);
            }
        }
    }

    public function appendRoute(string $siteRoot, string $routeId, string $path, string $moduleId, string $template): void
    {
        $this->assertRouteAvailable($siteRoot, $routeId, $path);

        $routesPath = $siteRoot . DIRECTORY_SEPARATOR . $this->toPath('application/config/routes.json');
        $routesConfig = $this->readJson($routesPath, 'OPUS_SITE_COMMAND_ROUTES_JSON_INVALID');
        $routes = $routesConfig['routes'] ?? [];
        if (!is_array($routes)) {
            throw new OpusConsoleException('OPUS_SITE_COMMAND_ROUTES_CONTRACT_INVALID');
        }
        $routes[] = [
            'id' => $routeId,
            'path' => $path,
            'module' => $moduleId,
            'template' => $template,
        ];
        $routesConfig['routes'] = $routes;
        $this->writeJson($routesPath, $routesConfig);
    }

    public function createRubric(string $siteRoot, string $moduleId, string $path, string $title): void
    {
        $this->assertModuleId($moduleId, 'OPUS_CREATE_RUBRIC_INVALID_MODULE_ID');
        $this->assertRoutePath($path, 'OPUS_CREATE_RUBRIC_INVALID_ROUTE_PATH');

        $routeId = strtolower($moduleId) . '.index';
        $template = 'application/modules/' . $moduleId . '/templates/pages/index.score';
        $this->assertRouteAvailable($siteRoot, $routeId, $path);

        $this->createModule($siteRoot, $moduleId, $title);
        $this->appendRoute($siteRoot, $routeId, $path, $moduleId, $template);
    }

    /**
     * @return array<string,mixed>
     */
    private function readJson(string $path, string $errorCode): array
    {
        if (!is_file($path)) {
            throw new OpusConsoleException('OPUS_SITE_COMMAND_JSON_FILE_MISSING: ' . $path);
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new OpusConsoleException('OPUS_SITE_COMMAND_JSON_READ_FAILED: ' . $path);
        }
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new OpusConsoleException($errorCode . ': ' . $path);
        }
        return $decoded;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function writeJson(string $path, array $data): void
    {
        $this->writeText($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    }

    private function writeText(string $path, string $content): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        if (file_put_contents($path, $content) === false) {
            throw new OpusConsoleException('OPUS_SITE_COMMAND_WRITE_FAILED: ' . $path);
        }
    }

    private function upsertI18n(string $siteRoot, string $moduleId, string $pageId, string $title): void
    {
        $i18nRoot = $siteRoot . DIRECTORY_SEPARATOR . $this->toPath('resources/i18n');
        if (!is_dir($i18nRoot)) {
            return;
        }
        $files = glob($i18nRoot . DIRECTORY_SEPARATOR . '*.json') ?: [];
        foreach ($files as $file) {
            $data = $this->readJson($file, 'OPUS_SITE_COMMAND_I18N_JSON_INVALID');
            if (!isset($data['generated']) || !is_array($data['generated'])) {
                $data['generated'] = [];
            }
            if (!isset($data['generated'][$moduleId]) || !is_array($data['generated'][$moduleId])) {
                $data['generated'][$moduleId] = [];
            }
            $data['generated'][$moduleId][$pageId] = [
                'title' => $title,
                'summary' => 'Generated page managed by OPUS Composer commands.',
            ];
            $this->writeJson($file, $data);
        }
    }

    private function pageTemplate(string $moduleId, string $pageId, string $title): string
    {
        return <<<SCORE
<section class="opus-page opus-page--generated">
  <header class="opus-page__header">
    <p class="opus-eyebrow">{$moduleId} / {$pageId}</p>
    <h1>{$title}</h1>
    <p>This page was generated by an OPUS Composer command. Replace this starter content with a view-model driven template.</p>
  </header>
</section>
SCORE;
    }

    private function moduleReadme(string $moduleId): string
    {
        return <<<MD
# {$moduleId} module

Generated OPUS module.

## Workflow

```text
route -> module -> .score template -> i18n/resources -> assets
```

## Main files

- `module.json`: module contract.
- `templates/pages/index.score`: default page template.
- `templates/layout.score`: module layout entry point.

Do not put business logic in `.score` templates. Use controllers/services/view-models before rendering.
MD;
    }

    private function absolutePath(string $relativePath): string
    {
        return $this->opusRoot . DIRECTORY_SEPARATOR . $this->toPath($relativePath);
    }

    private function toPath(string $relativePath): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }
}
