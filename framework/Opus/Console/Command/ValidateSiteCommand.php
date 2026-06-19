<?php
declare(strict_types=1);

namespace Opus\Console\Command;

use Opus\Console\OpusConsoleException;

/**
 * Validates the generated OPUS site contract.
 *
 * Contract:
 * - read-only command;
 * - validates site/module/route/content/template/i18n structure;
 * - fails loudly on missing required contract pieces;
 * - forbids extra public PHP pages beside public/index.php.
 */
final class ValidateSiteCommand implements OpusConsoleCommandInterface
{
    /** @var list<string> */
    private array $checks = [];

    public function __construct(private readonly string $opusRoot)
    {
    }

    public function name(): string
    {
        return 'validate:site';
    }

    /**
     * @param list<string> $arguments
     */
    public function run(array $arguments): int
    {
        $siteId = (string)($arguments[0] ?? '');
        if ($siteId === '') {
            throw new OpusConsoleException('OPUS_VALIDATE_SITE_MISSING_SITE_ID');
        }

        if (count($arguments) > 1) {
            throw new OpusConsoleException('OPUS_VALIDATE_SITE_TOO_MANY_ARGUMENTS');
        }

        if (!preg_match('/^[a-z][a-z0-9-]*$/', $siteId)) {
            throw new OpusConsoleException('OPUS_VALIDATE_SITE_INVALID_SITE_ID: ' . $siteId);
        }

        $siteRoot = $this->absolutePath('sites/' . $siteId);
        if (!is_dir($siteRoot)) {
            throw new OpusConsoleException('OPUS_VALIDATE_SITE_NOT_FOUND: sites/' . $siteId);
        }

        $this->requireFile($siteRoot, 'README.md');
        $this->requireFile($siteRoot, 'START_HERE.md');
        $this->requireFile($siteRoot, 'opus-site.json');
        $this->requireFile($siteRoot, 'public/index.php');
        $this->requireDirectory($siteRoot, 'public/assets/css');
        $this->requireDirectory($siteRoot, 'application/config');
        $this->requireDirectory($siteRoot, 'application/common/templates');
        $this->requireDirectory($siteRoot, 'application/modules');
        $this->requireDirectory($siteRoot, 'resources/content');
        $this->requireDirectory($siteRoot, 'resources/i18n');

        $this->forbidPublicPhpFilesExceptFrontController($siteRoot);

        $siteConfig = $this->readJson($siteRoot, 'application/config/site.json', 'OPUS_VALIDATE_SITE_JSON_INVALID');
        $modulesConfig = $this->readJson($siteRoot, 'application/config/modules.json', 'OPUS_VALIDATE_MODULES_JSON_INVALID');
        $routesConfig = $this->readJson($siteRoot, 'application/config/routes.json', 'OPUS_VALIDATE_ROUTES_JSON_INVALID');
        $this->readJson($siteRoot, 'application/config/fsm.json', 'OPUS_VALIDATE_FSM_JSON_INVALID');

        $defaultLocale = (string)($siteConfig['default_locale'] ?? '');
        if ($defaultLocale === '') {
            throw new OpusConsoleException('OPUS_VALIDATE_DEFAULT_LOCALE_MISSING');
        }

        $locales = $siteConfig['locales'] ?? [];
        if (!is_array($locales) || $locales === []) {
            throw new OpusConsoleException('OPUS_VALIDATE_LOCALES_CONTRACT_INVALID');
        }

        foreach ($locales as $locale) {
            if (!is_string($locale) || !preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $locale)) {
                throw new OpusConsoleException('OPUS_VALIDATE_LOCALE_INVALID: ' . (string)$locale);
            }
            $this->requireFile($siteRoot, 'resources/i18n/' . $locale . '.json');
            $this->readJson($siteRoot, 'resources/i18n/' . $locale . '.json', 'OPUS_VALIDATE_I18N_JSON_INVALID: ' . $locale);
        }

        if (!in_array($defaultLocale, $locales, true)) {
            throw new OpusConsoleException('OPUS_VALIDATE_DEFAULT_LOCALE_NOT_REGISTERED: ' . $defaultLocale);
        }

        $modules = $modulesConfig['modules'] ?? [];
        if (!is_array($modules) || $modules === []) {
            throw new OpusConsoleException('OPUS_VALIDATE_MODULES_CONTRACT_INVALID');
        }

        $registeredModuleIds = [];
        foreach ($modules as $module) {
            if (!is_array($module)) {
                throw new OpusConsoleException('OPUS_VALIDATE_MODULE_ENTRY_INVALID');
            }
            $moduleId = (string)($module['id'] ?? '');
            if ($moduleId === '' || !preg_match('/^[A-Z][A-Za-z0-9]*$/', $moduleId)) {
                throw new OpusConsoleException('OPUS_VALIDATE_MODULE_ID_INVALID: ' . $moduleId);
            }
            $registeredModuleIds[$moduleId] = true;
            $moduleRoot = 'application/modules/' . $moduleId;
            $this->requireDirectory($siteRoot, $moduleRoot);
            $this->requireFile($siteRoot, $moduleRoot . '/README.md');
            $this->requireFile($siteRoot, $moduleRoot . '/module.json');
            $this->requireFile($siteRoot, $moduleRoot . '/templates/layout.score');
            $this->requireFile($siteRoot, $moduleRoot . '/templates/pages/index.score');
            $this->readJson($siteRoot, $moduleRoot . '/module.json', 'OPUS_VALIDATE_MODULE_JSON_INVALID: ' . $moduleId);
        }

        $routes = $routesConfig['routes'] ?? [];
        if (!is_array($routes) || $routes === []) {
            throw new OpusConsoleException('OPUS_VALIDATE_ROUTES_CONTRACT_INVALID');
        }

        $seenPaths = [];
        foreach ($routes as $route) {
            if (!is_array($route)) {
                throw new OpusConsoleException('OPUS_VALIDATE_ROUTE_ENTRY_INVALID');
            }

            $routeId = (string)($route['id'] ?? '');
            $path = (string)($route['path'] ?? '');
            $moduleId = (string)($route['module'] ?? '');
            $template = (string)($route['template'] ?? '');
            $contentPattern = (string)($route['content'] ?? '');

            if ($routeId === '') {
                throw new OpusConsoleException('OPUS_VALIDATE_ROUTE_ID_MISSING');
            }
            if ($path === '' || $path[0] !== '/') {
                throw new OpusConsoleException('OPUS_VALIDATE_ROUTE_PATH_INVALID: ' . $routeId);
            }
            if (isset($seenPaths[$path])) {
                throw new OpusConsoleException('OPUS_VALIDATE_ROUTE_PATH_DUPLICATE: ' . $path);
            }
            $seenPaths[$path] = true;

            if (!isset($registeredModuleIds[$moduleId])) {
                throw new OpusConsoleException('OPUS_VALIDATE_ROUTE_MODULE_NOT_REGISTERED: ' . $routeId . ' -> ' . $moduleId);
            }
            if ($template === '') {
                throw new OpusConsoleException('OPUS_VALIDATE_ROUTE_TEMPLATE_MISSING: ' . $routeId);
            }
            $this->requireFile($siteRoot, $template);

            if ($contentPattern === '' || !str_contains($contentPattern, '{{lang}}')) {
                throw new OpusConsoleException('OPUS_VALIDATE_ROUTE_CONTENT_PATTERN_INVALID: ' . $routeId);
            }

            foreach ($locales as $locale) {
                $contentRelative = str_replace('{{lang}}', (string)$locale, $contentPattern);
                $this->requireFile($siteRoot, $contentRelative);
                $this->readJson($siteRoot, $contentRelative, 'OPUS_VALIDATE_CONTENT_JSON_INVALID: ' . $contentRelative);
            }
        }

        echo "OPUS_VALIDATE_SITE_OK: {$siteId}\n";
        foreach ($this->checks as $check) {
            echo '[OK] ' . $check . "\n";
        }

        return 0;
    }

    private function requireDirectory(string $siteRoot, string $relativePath): void
    {
        $path = $siteRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        if (!is_dir($path)) {
            throw new OpusConsoleException('OPUS_VALIDATE_REQUIRED_DIRECTORY_MISSING: ' . $relativePath);
        }
        $this->checks[] = $relativePath;
    }

    private function requireFile(string $siteRoot, string $relativePath): void
    {
        $path = $siteRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($path)) {
            throw new OpusConsoleException('OPUS_VALIDATE_REQUIRED_FILE_MISSING: ' . $relativePath);
        }
        $this->checks[] = $relativePath;
    }

    /**
     * @return array<string,mixed>
     */
    private function readJson(string $siteRoot, string $relativePath, string $errorCode): array
    {
        $path = $siteRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $content = file_get_contents($path);
        if ($content === false) {
            throw new OpusConsoleException('OPUS_VALIDATE_JSON_READ_FAILED: ' . $relativePath);
        }
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new OpusConsoleException($errorCode . ': ' . $relativePath);
        }
        $this->checks[] = $relativePath . ' JSON';
        return $decoded;
    }

    private function forbidPublicPhpFilesExceptFrontController(string $siteRoot): void
    {
        $publicRoot = $siteRoot . DIRECTORY_SEPARATOR . 'public';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($publicRoot, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }
            $path = $fileInfo->getPathname();
            if (strtolower($fileInfo->getExtension()) !== 'php') {
                continue;
            }
            $relative = str_replace($siteRoot . DIRECTORY_SEPARATOR, '', $path);
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            if ($relative !== 'public/index.php') {
                throw new OpusConsoleException('OPUS_VALIDATE_PUBLIC_PHP_FORBIDDEN: ' . $relative);
            }
        }
        $this->checks[] = 'public PHP surface';
    }

    private function absolutePath(string $relativePath): string
    {
        return $this->opusRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }
}
