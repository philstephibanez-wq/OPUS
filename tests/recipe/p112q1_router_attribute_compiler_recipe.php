<?php

declare(strict_types=1);

require_once __DIR__ . '/../../framework/ASAP/Contract/ContractException.php';
require_once __DIR__ . '/../../framework/ASAP/ROUTING/RouteCompilerException.php';
require_once __DIR__ . '/../../framework/ASAP/ROUTING/RouteDefinition.php';
require_once __DIR__ . '/../../framework/ASAP/ROUTING/Route.php';
require_once __DIR__ . '/../../framework/ASAP/ROUTING/ClassIndex.php';
require_once __DIR__ . '/../../framework/ASAP/ROUTING/AttributeRouteProvider.php';
require_once __DIR__ . '/../../framework/ASAP/ROUTING/RouteManifestCompiler.php';
require_once __DIR__ . '/../fixtures/P112Q1/DemoRouteController.php';
require_once __DIR__ . '/../fixtures/P112Q1/DuplicateRouteController.php';

use ASAP\Routing\AttributeRouteProvider;
use ASAP\Routing\ClassIndex;
use ASAP\Routing\RouteCompilerException;
use ASAP\Routing\RouteDefinition;
use ASAP\Routing\RouteManifestCompiler;
use ASAP\Tests\Fixtures\P112Q1\DemoRouteController;
use ASAP\Tests\Fixtures\P112Q1\DuplicateRouteController;

function recipeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('RECIPE_ASSERT_FAILED: ' . $message);
    }
}

function recipeStep(string $message): void
{
    echo 'PASS ' . $message . PHP_EOL;
}

$asapRoot = dirname(__DIR__, 2);
$refBookRoot = 'H:\\ASAP_REF_BOOK';

$requiredFiles = [
    $asapRoot . '/framework/ASAP/ROUTING/Route.php',
    $asapRoot . '/framework/ASAP/ROUTING/ClassIndex.php',
    $asapRoot . '/framework/ASAP/ROUTING/AttributeRouteProvider.php',
    $asapRoot . '/framework/ASAP/ROUTING/RouteManifestCompiler.php',
    $asapRoot . '/DOC/ROUTER_ATTRIBUTE_COMPILER.md',
    $asapRoot . '/DOC/P112Q1_ASAP_ROUTER_ATTRIBUTE_COMPILER_CONTRACT.md',
    $refBookRoot . '/content/markdown/router-attribute-compiler.md',
];

foreach ($requiredFiles as $file) {
    recipeAssert(is_file($file), 'Required file exists: ' . $file);
}

recipeStep('REQUIRED_FILES_PRESENT');

$classIndex = ClassIndex::fromComposerClassMap([
    DemoRouteController::class => __DIR__ . '/../fixtures/P112Q1/DemoRouteController.php',
]);

recipeAssert($classIndex->classes() === [DemoRouteController::class], 'ClassIndex classes are deterministic');
recipeAssert($classIndex->pathForClass(DemoRouteController::class) !== null, 'ClassIndex pathForClass works');
recipeAssert($classIndex->classesInNamespace('ASAP\\Tests\\Fixtures\\P112Q1') === [DemoRouteController::class], 'ClassIndex namespace filter works');

recipeStep('CLASS_INDEX_OK');

$provider = new AttributeRouteProvider($classIndex);
$routes = $provider->routes('ASAP\\Tests\\Fixtures\\P112Q1');

recipeAssert(count($routes) === 2, 'Attribute provider detects two routes');
recipeAssert($routes[0] instanceof RouteDefinition, 'Routes are RouteDefinition objects');
recipeAssert($routes[0]->name === 'kb.item', 'Priority sort puts kb.item first');
recipeAssert($routes[1]->name === 'kb.search', 'Second route is kb.search');
recipeAssert($routes[1]->path === '/kb/search', 'Route path preserved');
recipeAssert($routes[1]->normalizedMethods() === ['GET'], 'Route methods normalized');
recipeAssert($routes[1]->acl === 'kb.read', 'ACL metadata preserved');
recipeAssert($routes[1]->format === 'html', 'Default route format preserved');
recipeAssert(str_starts_with($routes[1]->source, 'attribute:'), 'Route source metadata preserved');

recipeStep('ATTRIBUTE_ROUTE_PROVIDER_OK');

$compiler = new RouteManifestCompiler();
$manifest = $compiler->compile($routes);

recipeAssert(isset($manifest['kb.search'], $manifest['kb.item']), 'Manifest contains named routes');
recipeAssert($manifest['kb.search']['path'] === '/kb/search', 'Manifest path preserved');
recipeAssert($manifest['kb.search']['methods'] === ['GET'], 'Manifest methods preserved');
recipeAssert($manifest['kb.search']['controller'] === DemoRouteController::class, 'Manifest controller preserved');
recipeAssert($manifest['kb.search']['action'] === 'search', 'Manifest action preserved');
recipeAssert($manifest['kb.search']['acl'] === 'kb.read', 'Manifest ACL preserved');
recipeAssert($manifest['kb.item']['priority'] === 10, 'Manifest priority preserved');

recipeStep('ROUTE_COMPILER_MANIFEST_OK');

$target = $asapRoot . '/var/cache/asap/routes/p112q1_recipe_routes.manifest.php';
$compiler->writePhpManifest($manifest, $target);
$loaded = $compiler->loadPhpManifest($target);

recipeAssert($loaded === $manifest, 'Manifest write/load roundtrip stable');

recipeStep('MANIFEST_WRITE_LOAD_OK');

$duplicateProvider = new AttributeRouteProvider(new ClassIndex([
    DemoRouteController::class,
    DuplicateRouteController::class,
]));

try {
    $compiler->compile($duplicateProvider->routes('ASAP\\Tests\\Fixtures\\P112Q1'));
    throw new RuntimeException('RECIPE_ASSERT_FAILED: duplicate path+method should fail');
} catch (RouteCompilerException $exception) {
    recipeAssert(str_contains($exception->getMessage(), 'ASAP_ROUTE_PATH_METHOD_DUPLICATE'), 'Duplicate path+method conflict explicit');
}

recipeStep('DUPLICATE_PATH_METHOD_BLOCKED');

$missingNamespaceRoutes = $provider->routes('ASAP\\Missing\\Namespace');

recipeAssert($missingNamespaceRoutes === [], 'Missing namespace returns an explicit empty route list');

recipeStep('MISSING_NAMESPACE_EMPTY_OK');

echo 'P112Q1_ROUTER_ATTRIBUTE_COMPILER_RECIPE_OK' . PHP_EOL;
