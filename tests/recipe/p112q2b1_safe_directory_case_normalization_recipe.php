<?php

declare(strict_types=1);

$frameworkRoot = 'H:\\Opus\\framework\\ASAP';

if (!is_dir($frameworkRoot)) {
    throw new RuntimeException('OPUS_FRAMEWORK_ROOT_MISSING');
}

function exactDirectorySegmentsRecipe(string $parent): array
{
    $entries = scandir($parent);

    if ($entries === false) {
        throw new RuntimeException('SCANDIR_FAILED: ' . $parent);
    }

    return array_values(array_filter($entries, static function (string $entry) use ($parent): bool {
        return $entry !== '.' && $entry !== '..' && is_dir($parent . DIRECTORY_SEPARATOR . $entry);
    }));
}

function assertExactDirectoryState(string $parent, string $old, string $new): void
{
    $segments = exactDirectorySegmentsRecipe($parent);

    if (in_array($old, $segments, true)) {
        throw new RuntimeException('OLD_DIRECTORY_SEGMENT_STILL_PRESENT: ' . $old);
    }

    if (!in_array($new, $segments, true)) {
        throw new RuntimeException('NEW_DIRECTORY_SEGMENT_MISSING: ' . $new);
    }

    echo 'PASS DIRECTORY ' . $old . ' -> ' . $new . PHP_EOL;
}

function assertRenderFinalState(string $parent): void
{
    $segments = exactDirectorySegmentsRecipe($parent);

    if (in_array('RENDER', $segments, true)) {
        throw new RuntimeException('OLD_DIRECTORY_SEGMENT_STILL_PRESENT: RENDER');
    }

    if (in_array('Render', $segments, true)) {
        echo 'PASS DIRECTORY RENDER -> Render' . PHP_EOL;
        return;
    }

    if (in_array('Renderer', $segments, true)) {
        echo 'PASS DIRECTORY Render removed after Renderer cleanup' . PHP_EOL;
        return;
    }

    throw new RuntimeException('RENDER_OR_RENDERER_DIRECTORY_MISSING');
}

assertExactDirectoryState($frameworkRoot, 'ROUTING', 'Routing');
assertExactDirectoryState($frameworkRoot, 'SITE', 'Site');
assertExactDirectoryState($frameworkRoot, 'URL', 'Url');
assertRenderFinalState($frameworkRoot);

require_once $frameworkRoot . '/Contract/ContractException.php';
require_once $frameworkRoot . '/Routing/RouteCompilerException.php';
require_once $frameworkRoot . '/Routing/RouteDefinition.php';
require_once $frameworkRoot . '/Routing/Route.php';
require_once $frameworkRoot . '/Routing/ClassIndex.php';
require_once $frameworkRoot . '/Routing/AttributeRouteProvider.php';
require_once $frameworkRoot . '/Routing/RouteManifestCompiler.php';

if (!class_exists(\ASAP\Routing\RouteDefinition::class)) {
    throw new RuntimeException('ROUTING_CLASS_NOT_LOADABLE_AFTER_DIRECTORY_NORMALIZATION');
}

if (!class_exists(\ASAP\Routing\RouteManifestCompiler::class)) {
    throw new RuntimeException('ROUTING_COMPILER_NOT_LOADABLE_AFTER_DIRECTORY_NORMALIZATION');
}

echo 'PASS ROUTING_CLASSES_LOADABLE' . PHP_EOL;
echo 'P112Q2B1_SAFE_DIRECTORY_CASE_NORMALIZATION_RECIPE_OK' . PHP_EOL;
