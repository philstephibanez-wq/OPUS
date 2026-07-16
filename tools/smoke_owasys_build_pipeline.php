<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\BuildPipeline;

$root = dirname(__DIR__);
$siteId = 'owasys-build-pipeline-smoke';
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
    'name' => 'OWASYS Build Pipeline Smoke',
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
    $pipeline = new BuildPipeline($root);
    $preview = $pipeline->run($request, 'preview');
    if (($preview['contract'] ?? null) !== BuildPipeline::CONTRACT || ($preview['mode'] ?? null) !== 'preview') {
        throw new RuntimeException('OWASYS_BUILD_PIPELINE_PREVIEW_INVALID');
    }
    if (file_exists($absoluteRoot)) {
        throw new RuntimeException('OWASYS_BUILD_PIPELINE_PREVIEW_MUTATED_DISK');
    }

    $build = $pipeline->run($request, 'build');
    if (($build['creation']['validation']['status'] ?? null) !== 'ok') {
        throw new RuntimeException('OWASYS_BUILD_PIPELINE_VALIDATION_INVALID');
    }
    if (($build['creation']['validation']['profiler'] ?? null) !== 'OPUS_GENERATED_PROFILER_V1') {
        throw new RuntimeException('OWASYS_BUILD_PIPELINE_PROFILER_INVALID');
    }
    foreach (['config/site.json', 'config/application.fsm.json', 'config/profiler.json', 'config/owasys-creation-manifest.json', 'www/index.php'] as $relative) {
        if (!is_file($absoluteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative))) {
            throw new RuntimeException('OWASYS_BUILD_PIPELINE_OUTPUT_MISSING:' . $relative);
        }
    }

    echo 'OWASYS_BUILD_PIPELINE_SMOKE_OK' . PHP_EOL;
} finally {
    $remove($absoluteRoot);
}
