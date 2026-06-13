<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--root=')) {
        $root = substr($arg, 7);
    }
}

$builderFile = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus'
    . DIRECTORY_SEPARATOR . 'Autoload' . DIRECTORY_SEPARATOR . 'ClassMapBuilder.php';
$cacheFile = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus'
    . DIRECTORY_SEPARATOR . 'Autoload' . DIRECTORY_SEPARATOR . 'AutoloadCache.php';

require_once $builderFile;
require_once $cacheFile;

use ASAP\Autoload\AutoloadCache;
use ASAP\Autoload\ClassMapBuilder;

$out = AutoloadCache::defaultCacheFile($root);
$assert = false;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--out=')) {
        $out = substr($arg, 6);
    }
    if ($arg === '--assert') {
        $assert = true;
    }
}

$builder = new ClassMapBuilder();
$map = $builder->build($root);
$builder->write($map, $out);

echo 'OPUS_AUTOLOADER_CACHE_FILE=' . $out . PHP_EOL;
echo 'OPUS_AUTOLOADER_CACHE_CLASS_COUNT=' . (string)$map['class_count'] . PHP_EOL;
echo 'OPUS_AUTOLOADER_CACHE_BUILD_OK' . PHP_EOL;

if ($assert) {
    (new AutoloadCache($root, $out))->register();

    $required = [
        \ASAP\Autoload\AutoloadCache::class,
        \ASAP\Autoload\ClassMapBuilder::class,
        \ASAP\Core\Bootstrap::class,
        \ASAP\Application\Application::class,
        \ASAP\Routing\ClassIndex::class,
        \ASAP\Lstsa\LstsaRunner::class,
    ];

    foreach ($required as $class) {
        if (!class_exists($class)) {
            fwrite(STDERR, 'OPUS_AUTOLOADER_CACHE_CLASS_NOT_LOADABLE=' . $class . PHP_EOL);
            exit(1);
        }
    }

    echo 'OPUS_AUTOLOADER_CACHE_ASSERT_OK' . PHP_EOL;
}
