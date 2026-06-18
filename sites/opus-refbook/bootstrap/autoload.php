<?php
declare(strict_types=1);

/*
 * OPUS_REF_BOOK bootstrap autoloader.
 *
 * Visibility: INTERNAL
 * Scope: OPUS_REF_BOOK application bootstrap only.
 * Role:
 *   Load Composer when available and register the local RefBook namespace.
 * Contract:
 *   - Composer is the normal dependency resolver.
 *   - OPUS_ROOT is accepted only as an explicit runtime/development pointer.
 *   - When OPUS_REF_BOOK is mounted inside OPUS/sites/<site>, the OPUS root is
 *     resolved deterministically from the application location.
 *   - No workstation-specific default path is allowed.
 *   - No obsolete renderer emulation and no fallback renderer.
 *   - Twig files are forbidden in this OPUS MVC application.
 */

$refbookRoot = dirname(__DIR__);

/**
 * INTERNAL BOOT GUARD
 *
 * Role:
 *   Fail immediately when obsolete Twig templates are present in the active
 *   RefBook application tree.
 *
 * Contract:
 *   OPUS_REF_BOOK views are ScoreTemplate `.score` templates only. This guard
 *   does not clean or migrate files silently; it exposes a hard contract error.
 *
 * @throws RuntimeException when a `.twig` file is found.
 */
function opus_refbook_assert_no_twig_templates(string $root): void
{
    if (!is_dir($root)) {
        throw new RuntimeException('OPUS_REFBOOK_TEMPLATE_SCAN_ROOT_MISSING=' . $root);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        if (str_ends_with(strtolower($file->getFilename()), '.twig')) {
            throw new RuntimeException('OPUS_REFBOOK_TWIG_FILE_PRESENT=' . $file->getPathname());
        }
    }
}

opus_refbook_assert_no_twig_templates($refbookRoot . DIRECTORY_SEPARATOR . 'application');

$opusRootEnv = getenv('OPUS_ROOT');
$opusRoot = is_string($opusRootEnv) ? trim($opusRootEnv) : '';

$refbookVendor = $refbookRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (is_file($refbookVendor)) {
    require_once $refbookVendor;
}

$prefixes = [
    'OpusRefBook\\Reference\\' => $refbookRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'reference',
];

$resolvedOpusRoot = '';

if ($opusRoot !== '') {
    $explicitOpusRoot = rtrim($opusRoot, "\\/");
    $opusFramework = $explicitOpusRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus';
    if (!is_dir($opusFramework)) {
        throw new RuntimeException('OPUS_REFBOOK_OPUS_ROOT_INVALID=' . $opusRoot);
    }

    $explicitOpusAutoload = $explicitOpusRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (is_file($explicitOpusAutoload)) {
        require_once $explicitOpusAutoload;
    }

    $resolvedOpusRoot = $explicitOpusRoot;
} else {
    $integratedOpusRoot = dirname($refbookRoot, 2);
    $integratedOpusFramework = $integratedOpusRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus';
    $integratedOpusComposer = $integratedOpusRoot . DIRECTORY_SEPARATOR . 'composer.json';
    $integratedOpusAutoload = $integratedOpusRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

    if (is_dir($integratedOpusFramework) && is_file($integratedOpusComposer)) {
        if (!is_file($integratedOpusAutoload)) {
            throw new RuntimeException('OPUS_REFBOOK_INTEGRATED_OPUS_VENDOR_MISSING=' . $integratedOpusAutoload);
        }

        require_once $integratedOpusAutoload;
        $resolvedOpusRoot = $integratedOpusRoot;
    }
}

if ($resolvedOpusRoot !== '') {
    $prefixes['Opus\\'] = $resolvedOpusRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus';
}

spl_autoload_register(static function (string $class) use ($prefixes): void {
    foreach ($prefixes as $prefix => $root) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $file = $root . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

        if (is_file($file)) {
            require_once $file;
        }

        return;
    }
});

if (!class_exists(\Opus\Application\Application::class)) {
    throw new RuntimeException('OPUS_REFBOOK_OPUS_DEPENDENCY_MISSING: run Composer install, mount OPUS_REF_BOOK inside OPUS/sites, or define OPUS_ROOT explicitly');
}
