<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use ASAP\Recipe\RecipeContext;
use ASAP\Recipe\Recipes\AutoloadCacheRecipe;

$root = dirname(__DIR__, 2);
$runId = 'asap_autoload_cache_recipe_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
$runtime = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'recipes' . DIRECTORY_SEPARATOR . 'asap_autoload_cache' . DIRECTORY_SEPARATOR . $runId;

if (!is_dir($runtime) && !mkdir($runtime, 0775, true) && !is_dir($runtime)) {
    fwrite(STDERR, 'ASAP_AUTOLOADER_CACHE_RECIPE_RUNTIME_CREATE_FAILED=' . $runtime . PHP_EOL);
    exit(1);
}

$context = new RecipeContext($root, $runId, $runtime);
$context->registerAsapAutoload();

$recipe = new AutoloadCacheRecipe();
try {
    foreach ($recipe->run($context) as $marker) {
        echo $marker . PHP_EOL;
    }
    foreach ($context->pullDiagnostics() as $diagnostic) {
        echo 'ASAP_RECIPE_DIAGNOSTIC=' . $diagnostic . PHP_EOL;
    }
    echo 'ASAP_AUTOLOADER_CACHE_RECIPE_OK' . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    echo 'ASAP_AUTOLOADER_CACHE_RECIPE_FAILED=' . $exception->getMessage() . PHP_EOL;
    exit(1);
}
