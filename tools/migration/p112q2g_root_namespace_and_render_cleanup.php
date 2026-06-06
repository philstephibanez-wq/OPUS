<?php

declare(strict_types=1);

/**
 * P112Q2G — Root namespace and Render cleanup.
 *
 * This migration removes the dirty final layout:
 *
 * - no PHP file directly under framework/Asap;
 * - no decorative Render directory next to Renderer;
 * - root compatibility classes moved into explicit domains;
 * - no fallback root file is kept.
 */

$asapRoot = 'H:\\ASAP';
$refBookRoot = 'H:\\ASAP_REF_BOOK';
$frameworkRoot = $asapRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap';

if (!is_dir($asapRoot)) {
    fwrite(STDERR, "ASAP_ROOT_MISSING\n");
    exit(1);
}

if (!is_dir($refBookRoot)) {
    fwrite(STDERR, "ASAP_REF_BOOK_ROOT_MISSING\n");
    exit(1);
}

if (!is_dir($frameworkRoot)) {
    fwrite(STDERR, "ASAP_FRAMEWORK_ROOT_MISSING\n");
    exit(1);
}

$moves = [
    'Acl.php' => ['Acl/Acl.php', 'ASAP\\Acl', null, []],
    'Bootstrap.php' => ['Core/Bootstrap.php', 'ASAP\\Core', null, [
        'Exception::because' => '\\ASAP\\Exception\\Exception::because',
    ]],
    'ConfigLoader.php' => ['Config/ConfigLoader.php', 'ASAP\\Config', null, [
        'Exception::because' => '\\ASAP\\Exception\\Exception::because',
    ]],
    'Configuration.php' => ['Config/Configuration.php', 'ASAP\\Config', null, [
        'Exception::because' => '\\ASAP\\Exception\\Exception::because',
    ]],
    'Debug.php' => ['Debug/Debug.php', 'ASAP\\Debug', null, []],
    'Exception.php' => ['Exception/Exception.php', 'ASAP\\Exception', null, []],
    'Fsm.php' => ['Fsm/Fsm.php', 'ASAP\\Fsm', null, []],
    'Kernel.php' => ['Core/Kernel.php', 'ASAP\\Core', null, [
        'private readonly PackageRepository $packages = new PackageRepository()' => 'private readonly \\ASAP\\Package\\PackageRepository $packages = new \\ASAP\\Package\\PackageRepository()',
        'public function getPackage(string $id): Package' => 'public function getPackage(string $id): \\ASAP\\Package\\Package',
        'Exception::because' => '\\ASAP\\Exception\\Exception::because',
    ]],
    'Package.php' => ['Package/Package.php', 'ASAP\\Package', null, [
        'Exception::because' => '\\ASAP\\Exception\\Exception::because',
    ]],
    'PackageRepository.php' => ['Package/PackageRepository.php', 'ASAP\\Package', null, [
        'Exception::because' => '\\ASAP\\Exception\\Exception::because',
    ]],
    'Response.php' => ['Response/ResponseFacade.php', 'ASAP\\Response', ['Response', 'ResponseFacade'], []],
    'SimpleXMLElementExtended.php' => ['Compatibility/SimpleXMLElementExtended.php', 'ASAP\\Compatibility', null, []],
    'Singleton.php' => ['Compatibility/Singleton.php', 'ASAP\\Compatibility', null, [
        'Exception::because' => '\\ASAP\\Exception\\Exception::because',
    ]],
    'Support.php' => ['Support/Support.php', 'ASAP\\Support', null, [
        'Exception::because' => '\\ASAP\\Exception\\Exception::because',
    ]],
    'Validator.php' => ['Validation/Validator.php', 'ASAP\\Validation', null, []],
    'View.php' => ['View/View.php', 'ASAP\\View', null, [
        'Exception::because' => '\\ASAP\\Exception\\Exception::because',
    ]],
];

function normalizedPath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function ensureDirectory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('DIRECTORY_CREATE_FAILED: ' . $directory);
    }
}

function readText(string $path): string
{
    $content = @file_get_contents($path);

    if ($content === false) {
        throw new RuntimeException('FILE_READ_FAILED: ' . $path);
    }

    return $content;
}

function writeText(string $path, string $content): void
{
    ensureDirectory(dirname($path));

    if (@file_put_contents($path, $content) === false) {
        throw new RuntimeException('FILE_WRITE_FAILED: ' . $path);
    }
}

function transformRootPhpFile(string $content, string $targetNamespace, ?array $classRename, array $customReplacements): string
{
    $content = preg_replace('/^namespace\s+ASAP\s*;/m', 'namespace ' . $targetNamespace . ';', $content, 1);

    if (!is_string($content)) {
        throw new RuntimeException('NAMESPACE_REWRITE_FAILED');
    }

    if ($classRename !== null) {
        [$from, $to] = $classRename;
        $content = preg_replace('/\b(final\s+class|class)\s+' . preg_quote($from, '/') . '\b/', '$1 ' . $to, $content, 1);

        if (!is_string($content)) {
            throw new RuntimeException('CLASS_RENAME_FAILED: ' . $from . ' -> ' . $to);
        }
    }

    foreach ($customReplacements as $from => $to) {
        $content = str_replace($from, $to, $content);
    }

    return $content;
}

function removeRenderDirectory(string $frameworkRoot): void
{
    $renderDir = $frameworkRoot . DIRECTORY_SEPARATOR . 'Render';
    $rendererDir = $frameworkRoot . DIRECTORY_SEPARATOR . 'Renderer';

    if (!is_dir($renderDir)) {
        return;
    }

    if (!is_dir($rendererDir)) {
        throw new RuntimeException('RENDERER_DIRECTORY_MISSING_RENDER_CANNOT_BE_REMOVED');
    }

    $allowed = ['README.md', '.gitkeep'];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($renderDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isFile() && !in_array($item->getFilename(), $allowed, true)) {
            throw new RuntimeException('RENDER_DIRECTORY_CONTAINS_UNEXPECTED_FILE: ' . $item->getPathname());
        }
    }

    foreach ($iterator as $item) {
        if ($item->isFile()) {
            if (!unlink($item->getPathname())) {
                throw new RuntimeException('RENDER_FILE_DELETE_FAILED: ' . $item->getPathname());
            }
            continue;
        }

        if ($item->isDir()) {
            if (!rmdir($item->getPathname())) {
                throw new RuntimeException('RENDER_SUBDIR_DELETE_FAILED: ' . $item->getPathname());
            }
        }
    }

    if (!rmdir($renderDir)) {
        throw new RuntimeException('RENDER_DIR_DELETE_FAILED: ' . $renderDir);
    }

    echo 'REMOVED Render' . PHP_EOL;
}

function shouldSkipReferencePath(string $path): bool
{
    $normalized = normalizedPath($path);

    return str_contains($normalized, '/.git/')
        || str_contains($normalized, '/vendor/')
        || str_contains($normalized, '/var/cache/')
        || str_contains($normalized, '/var/reports/')
        || str_contains($normalized, '/node_modules/')
        || str_contains($normalized, '/framework/Asap/')
        || str_contains(strtolower($normalized), '/tools/migration/p112q2g_')
        || str_contains($normalized, '/DOC/P112Q2G_')
        || str_contains($normalized, '/content/markdown/root-namespace-and-render-cleanup.md');
}

function replaceOutsideFramework(array $roots, array $simpleReplacements, array $regexReplacements): int
{
    $allowedExtensions = [
        'php' => true,
        'json' => true,
        'xml' => true,
        'yml' => true,
        'yaml' => true,
        'cmd' => true,
        'ps1' => true,
        'html' => true,
        'twig' => true,
        'ini' => true,
        'md' => true,
    ];

    $changed = 0;

    foreach ($roots as $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $path = $item->getPathname();

            if (shouldSkipReferencePath($path)) {
                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (!isset($allowedExtensions[$extension])) {
                continue;
            }

            $content = readText($path);
            $updated = str_replace(array_keys($simpleReplacements), array_values($simpleReplacements), $content);

            foreach ($regexReplacements as $pattern => $replacement) {
                $updated = preg_replace($pattern, $replacement, $updated);

                if (!is_string($updated)) {
                    throw new RuntimeException('REGEX_REPLACE_FAILED: ' . $pattern . ' in ' . $path);
                }
            }

            if ($updated !== $content) {
                writeText($path, $updated);
                $changed++;
            }
        }
    }

    return $changed;
}

foreach ($moves as $sourceFile => [$targetRelative, $targetNamespace, $classRename, $customReplacements]) {
    $sourcePath = $frameworkRoot . DIRECTORY_SEPARATOR . $sourceFile;
    $targetPath = $frameworkRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetRelative);

    if (!is_file($sourcePath)) {
        throw new RuntimeException('ROOT_SOURCE_FILE_MISSING: ' . $sourceFile);
    }

    if (is_file($targetPath)) {
        throw new RuntimeException('TARGET_FILE_ALREADY_EXISTS: ' . $targetRelative);
    }

    $content = transformRootPhpFile(readText($sourcePath), $targetNamespace, $classRename, $customReplacements);
    writeText($targetPath, $content);

    if (!unlink($sourcePath)) {
        throw new RuntimeException('ROOT_SOURCE_FILE_DELETE_FAILED: ' . $sourceFile);
    }

    echo 'MOVED ' . $sourceFile . ' -> ' . $targetRelative . PHP_EOL;
}

$legacySimplePath = $frameworkRoot . DIRECTORY_SEPARATOR . 'SimpleXMLElementExtended.class.php';
$legacySingletonPath = $frameworkRoot . DIRECTORY_SEPARATOR . 'Singleton.class.php';

if (!is_file($legacySimplePath)) {
    throw new RuntimeException('LEGACY_SIMPLEXML_CLASS_FILE_MISSING');
}

if (!is_file($legacySingletonPath)) {
    throw new RuntimeException('LEGACY_SINGLETON_CLASS_FILE_MISSING');
}

writeText(
    $frameworkRoot . DIRECTORY_SEPARATOR . 'Compatibility' . DIRECTORY_SEPARATOR . 'LegacySimpleXMLElementExtended.php',
    <<<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/SimpleXMLElementExtended.php';

if (!class_exists('ASAP_SimpleXMLElementExtended', false)) {
    class ASAP_SimpleXMLElementExtended extends \ASAP\Compatibility\SimpleXMLElementExtended
    {
    }
}

PHP
);

writeText(
    $frameworkRoot . DIRECTORY_SEPARATOR . 'Compatibility' . DIRECTORY_SEPARATOR . 'LegacySingleton.php',
    <<<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../Exception/Exception.php';
require_once __DIR__ . '/Singleton.php';

if (!class_exists('ASAP_Singleton', false)) {
    class ASAP_Singleton extends \ASAP\Compatibility\Singleton
    {
    }
}

PHP
);

if (!unlink($legacySimplePath)) {
    throw new RuntimeException('LEGACY_SIMPLEXML_CLASS_FILE_DELETE_FAILED');
}

if (!unlink($legacySingletonPath)) {
    throw new RuntimeException('LEGACY_SINGLETON_CLASS_FILE_DELETE_FAILED');
}

echo 'MOVED SimpleXMLElementExtended.class.php -> Compatibility/LegacySimpleXMLElementExtended.php' . PHP_EOL;
echo 'MOVED Singleton.class.php -> Compatibility/LegacySingleton.php' . PHP_EOL;

removeRenderDirectory($frameworkRoot);

$simpleReplacements = [
    'framework/Asap/Acl.php' => 'framework/Asap/Acl/Acl.php',
    'framework/Asap/Bootstrap.php' => 'framework/Asap/Core/Bootstrap.php',
    'framework/Asap/ConfigLoader.php' => 'framework/Asap/Config/ConfigLoader.php',
    'framework/Asap/Configuration.php' => 'framework/Asap/Config/Configuration.php',
    'framework/Asap/Debug.php' => 'framework/Asap/Debug/Debug.php',
    'framework/Asap/Exception.php' => 'framework/Asap/Exception/Exception.php',
    'framework/Asap/Fsm.php' => 'framework/Asap/Fsm/Fsm.php',
    'framework/Asap/Kernel.php' => 'framework/Asap/Core/Kernel.php',
    'framework/Asap/Package.php' => 'framework/Asap/Package/Package.php',
    'framework/Asap/PackageRepository.php' => 'framework/Asap/Package/PackageRepository.php',
    'framework/Asap/Response.php' => 'framework/Asap/Response/ResponseFacade.php',
    'framework/Asap/SimpleXMLElementExtended.php' => 'framework/Asap/Compatibility/SimpleXMLElementExtended.php',
    'framework/Asap/SimpleXMLElementExtended.class.php' => 'framework/Asap/Compatibility/LegacySimpleXMLElementExtended.php',
    'framework/Asap/Singleton.php' => 'framework/Asap/Compatibility/Singleton.php',
    'framework/Asap/Singleton.class.php' => 'framework/Asap/Compatibility/LegacySingleton.php',
    'framework/Asap/Support.php' => 'framework/Asap/Support/Support.php',
    'framework/Asap/Validator.php' => 'framework/Asap/Validation/Validator.php',
    'framework/Asap/View.php' => 'framework/Asap/View/View.php',
    'framework/Asap/Render' => 'framework/Asap/Renderer',
    'ASAP\\Render' => 'ASAP\\Renderer',
    'ASAP\\\\Render' => 'ASAP\\\\Renderer',
];

$fqcnMap = [
    'Acl' => 'Acl\\Acl',
    'Bootstrap' => 'Core\\Bootstrap',
    'ConfigLoader' => 'Config\\ConfigLoader',
    'Configuration' => 'Config\\Configuration',
    'Debug' => 'Debug\\Debug',
    'Exception' => 'Exception\\Exception',
    'Fsm' => 'Fsm\\Fsm',
    'Kernel' => 'Core\\Kernel',
    'Package' => 'Package\\Package',
    'PackageRepository' => 'Package\\PackageRepository',
    'Response' => 'Response\\ResponseFacade',
    'SimpleXMLElementExtended' => 'Compatibility\\SimpleXMLElementExtended',
    'Singleton' => 'Compatibility\\Singleton',
    'Support' => 'Support\\Support',
    'Validator' => 'Validation\\Validator',
    'View' => 'View\\View',
];

$regexReplacements = [];

foreach ($fqcnMap as $old => $new) {
    $regexReplacements['/(?<![A-Za-z0-9_\\\\])ASAP\\\\' . preg_quote($old, '/') . '(?!\\\\|[A-Za-z0-9_])/'] = 'ASAP\\' . $new;
    $regexReplacements['/(?<![A-Za-z0-9_\\\\])ASAP\\\\\\\\' . preg_quote($old, '/') . '(?!\\\\\\\\|[A-Za-z0-9_])/'] = 'ASAP\\\\' . str_replace('\\', '\\\\', $new);
}

$changedFiles = replaceOutsideFramework([$asapRoot, $refBookRoot], $simpleReplacements, $regexReplacements);

echo 'TEXT_FILES_UPDATED_OUTSIDE_FRAMEWORK=' . $changedFiles . PHP_EOL;
echo 'P112Q2G_ROOT_NAMESPACE_AND_RENDER_CLEANUP_OK' . PHP_EOL;

exit(0);
