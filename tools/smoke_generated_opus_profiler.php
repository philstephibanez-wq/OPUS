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
    'profiler' => true,
];

$remove($absoluteRoot);

try {
    $creator = new ApplicationCreator($root);
    $dryRun = $creator->create($request, false, false);
    if (($dryRun['profiler']['enabled'] ?? null) !== true || ($dryRun['profiler']['mode'] ?? null) !== 'dry-run') {
        throw new RuntimeException('OWASYS_GENERATED_PROFILER_DRY_RUN_INVALID');
    }

    $result = $creator->create($request, true, true);
    if (($result['profiler']['contract'] ?? null) !== 'OPUS_GENERATED_PROFILER_V1' || ($result['validation']['profiler'] ?? null) !== 'OPUS_GENERATED_PROFILER_V1') {
        throw new RuntimeException('OWASYS_GENERATED_PROFILER_RESULT_INVALID');
    }

    foreach (['config/profiler.json', 'application/default/helpers/GeneratedProfiler.php', 'www/asset/css/profiler.css', 'www/asset/js/profiler.js'] as $relative) {
        if (!is_file($absoluteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative))) {
            throw new RuntimeException('OWASYS_GENERATED_PROFILER_FILE_MISSING:' . $relative);
        }
    }

    $front = (string) file_get_contents($absoluteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'index.php');
    $runtime = (string) file_get_contents($absoluteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'GeneratedProfiler.php');
    $config = (string) file_get_contents($absoluteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'profiler.json');
    foreach (['OPUS_GENERATED_PROFILER_BOOTSTRAP', "\$_GET['profiler']", "\$flag === '1'", "['dev', 'local', 'development']", 'OPUS_GENERATED_PROFILER_V1', '"query_enable": "profiler=1"', '"query_disable": "profiler=0"'] as $marker) {
        if (!str_contains($front . $runtime . $config, $marker)) {
            throw new RuntimeException('OWASYS_GENERATED_PROFILER_MARKER_MISSING:' . $marker);
        }
    }

    $owasysFront = (string) file_get_contents($root . '/sites/owasys/www/index.php');
    if (str_contains($owasysFront, 'OPUS_GENERATED_PROFILER_BOOTSTRAP')) {
        throw new RuntimeException('OWASYS_MUST_NOT_BOOT_GENERATED_PROFILER');
    }

    echo 'OWASYS_GENERATED_PROFILER_SMOKE_OK' . PHP_EOL;
} finally {
    $remove($absoluteRoot);
}
