<?php
declare(strict_types=1);

namespace Opus\Console\Command;

use Opus\Console\OpusConsoleException;

/**
 * Lists configured modules for an existing OPUS site.
 *
 * Public contract:
 * - read-only inspection command;
 * - reads application/config/modules.json and module.json files;
 * - prints module -> root -> default template mappings;
 * - fails explicitly on missing/invalid module contracts;
 * - does not create, modify, or repair project files.
 */
final class ListModulesCommand implements OpusConsoleCommandInterface
{
    public function __construct(private readonly string $opusRoot)
    {
    }

    public function name(): string
    {
        return 'list:modules';
    }

    /**
     * @param list<string> $arguments
     */
    public function run(array $arguments): int
    {
        $siteId = (string)($arguments[0] ?? '');
        if ($siteId === '') {
            throw new OpusConsoleException('OPUS_LIST_MODULES_MISSING_SITE_ID');
        }

        if (count($arguments) > 1) {
            throw new OpusConsoleException('OPUS_LIST_MODULES_TOO_MANY_ARGUMENTS');
        }

        if (!preg_match('/^[a-z][a-z0-9-]*$/', $siteId)) {
            throw new OpusConsoleException('OPUS_LIST_MODULES_INVALID_SITE_ID: ' . $siteId);
        }

        $siteRoot = $this->absolutePath('sites/' . $siteId);
        if (!is_dir($siteRoot)) {
            throw new OpusConsoleException('OPUS_LIST_MODULES_SITE_NOT_FOUND: sites/' . $siteId);
        }

        $modulesConfig = $this->readJson($siteRoot, 'application/config/modules.json', 'OPUS_LIST_MODULES_MODULES_JSON_INVALID');
        $modules = $modulesConfig['modules'] ?? [];
        if (!is_array($modules) || $modules === []) {
            throw new OpusConsoleException('OPUS_LIST_MODULES_MODULES_CONTRACT_INVALID');
        }

        echo "OPUS_LIST_MODULES: {$siteId}\n";
        foreach ($modules as $module) {
            if (!is_array($module)) {
                throw new OpusConsoleException('OPUS_LIST_MODULES_MODULE_ENTRY_INVALID');
            }

            $moduleId = (string)($module['id'] ?? '');
            if ($moduleId === '' || !preg_match('/^[A-Z][A-Za-z0-9]*$/', $moduleId)) {
                throw new OpusConsoleException('OPUS_LIST_MODULES_MODULE_ID_INVALID: ' . $moduleId);
            }

            $moduleRoot = 'application/modules/' . $moduleId;
            $moduleJson = $this->readJson($siteRoot, $moduleRoot . '/module.json', 'OPUS_LIST_MODULES_MODULE_JSON_INVALID: ' . $moduleId);
            $enabled = (bool)($module['enabled'] ?? true);
            $title = (string)($moduleJson['title'] ?? $module['title'] ?? $moduleId);

            $this->requireDirectory($siteRoot, $moduleRoot, 'OPUS_LIST_MODULES_MODULE_ROOT_MISSING: ' . $moduleId);
            $this->requireFile($siteRoot, $moduleRoot . '/templates/pages/index.score', 'OPUS_LIST_MODULES_DEFAULT_TEMPLATE_MISSING: ' . $moduleId);

            echo '[MODULE] ' . $moduleId
                . ' enabled=' . ($enabled ? 'yes' : 'no')
                . ' root=' . $moduleRoot
                . ' default_template=' . $moduleRoot . '/templates/pages/index.score'
                . ' title="' . $title . '"'
                . "\n";
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
            throw new OpusConsoleException('OPUS_LIST_MODULES_REQUIRED_FILE_MISSING: ' . $relativePath);
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new OpusConsoleException('OPUS_LIST_MODULES_JSON_READ_FAILED: ' . $relativePath);
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new OpusConsoleException($errorCode . ': ' . $relativePath);
        }

        return $decoded;
    }

    private function requireDirectory(string $siteRoot, string $relativePath, string $errorCode): void
    {
        $path = $siteRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        if (!is_dir($path)) {
            throw new OpusConsoleException($errorCode . ': ' . $relativePath);
        }
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
