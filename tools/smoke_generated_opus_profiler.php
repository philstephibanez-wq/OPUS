<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\ApplicationCreator;

$root = dirname(__DIR__);
$siteId = 'owasys-profiler-smoke-demo';
$siteRoot = 'sites/' . $siteId;
$absoluteRoot = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $siteRoot);

$remove = static function (string $path): void {
    if (!file_exists($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
};

$request = [
    'id' => $siteId,
    'slug' => $siteId,
    'name' => 'OWASYS Profiler Smoke Demo',
    'kind' => 'fullstack',
    'root_path' => $siteRoot,
    'blueprint' => 'opus-site-standard',
    'default_locale' => 'fr',
    'theme' => 'starter',
    'controllers' => ['home'],
    'routes' => [['id' => 'home.index', 'path' => '/', 'state' => 'home', 'controller' => 'home']],
    'datasources' => [],
    'security_profiles' => [],
    'workflows' => [],
];

$remove($absoluteRoot);

try {
    $creator = new ApplicationCreator($root);
    $dryRun = $creator->create($request, false, false);
    if (($dryRun['profiler']['enabled'] ?? null) !== true
        || ($dryRun['profiler']['mandatory'] ?? null) !== true
        || ($dryRun['profiler']['production_available'] ?? null) !== false
        || ($dryRun['profiler']['mode'] ?? null) !== 'dry-run') {
        throw new RuntimeException('OWASYS_GENERATED_PROFILER_DRY_RUN_INVALID');
    }

    $result = $creator->create($request, true, true);
    if (($result['profiler']['contract'] ?? null) !== 'OPUS_GENERATED_PROFILER_V1'
        || ($result['validation']['profiler'] ?? null) !== 'OPUS_GENERATED_PROFILER_V1'
        || ($result['validation']['profiler_mandatory'] ?? null) !== true
        || ($result['validation']['profiler_production_available'] ?? null) !== false) {
        throw new RuntimeException('OWASYS_GENERATED_PROFILER_RESULT_INVALID');
    }

    foreach (['config/profiler.json', 'application/default/helpers/GeneratedProfiler.php', 'www/asset/css/profiler.css', 'www/asset/js/profiler.js'] as $relative) {
        if (!is_file($absoluteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative))) {
            throw new RuntimeException('OWASYS_GENERATED_PROFILER_FILE_MISSING:' . $relative);
        }
    }

    $front = (string) file_get_contents($absoluteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'index.php');
    $runtimeFile = $absoluteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'GeneratedProfiler.php';
    $runtime = (string) file_get_contents($runtimeFile);
    $config = (string) file_get_contents($absoluteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'profiler.json');
    foreach (['OPUS_GENERATED_PROFILER_BOOTSTRAP', "\$_GET['profiler']", "['dev', 'local', 'development']", 'OPUS_GENERATED_PROFILER_V1', 'profiler=1', 'profiler=0', '"mandatory": true', '"production_available": false'] as $marker) {
        if (!str_contains($front . $runtime . $config, $marker)) {
            throw new RuntimeException('OWASYS_GENERATED_PROFILER_MARKER_MISSING:' . $marker);
        }
    }

    require_once $runtimeFile;
    $_GET['profiler'] = '1';
    putenv('OPUS_ENV=prod');
    $_SERVER['OPUS_ENV'] = 'prod';
    if (\OpusGenerated\GeneratedProfiler::boot($absoluteRoot) !== null) {
        throw new RuntimeException('OWASYS_GENERATED_PROFILER_AVAILABLE_IN_PRODUCTION');
    }

    putenv('OPUS_ENV=dev');
    $_SERVER['OPUS_ENV'] = 'dev';
    if (!(\OpusGenerated\GeneratedProfiler::boot($absoluteRoot) instanceof \OpusGenerated\GeneratedProfiler)) {
        throw new RuntimeException('OWASYS_GENERATED_PROFILER_UNAVAILABLE_IN_DEV');
    }

    $owasysFront = (string) file_get_contents($root . '/sites/owasys/www/index.php');
    if (str_contains($owasysFront, 'OPUS_GENERATED_PROFILER_BOOTSTRAP')) {
        throw new RuntimeException('OWASYS_MUST_NOT_BOOT_GENERATED_PROFILER');
    }

    echo 'OWASYS_GENERATED_PROFILER_SMOKE_OK' . PHP_EOL;
} finally {
    putenv('OPUS_ENV');
    unset($_GET['profiler'], $_SERVER['OPUS_ENV']);
    $remove($absoluteRoot);
}
