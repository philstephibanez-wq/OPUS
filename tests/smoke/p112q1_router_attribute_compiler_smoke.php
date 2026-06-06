<?php

declare(strict_types=1);

require_once __DIR__ . '/../../framework/Asap/Contract/ContractException.php';
require_once __DIR__ . '/../../framework/Asap/Routing/RouteCompilerException.php';
require_once __DIR__ . '/../../framework/Asap/Routing/RouteDefinition.php';
require_once __DIR__ . '/../../framework/Asap/Routing/Route.php';
require_once __DIR__ . '/../../framework/Asap/Routing/ClassIndex.php';
require_once __DIR__ . '/../../framework/Asap/Routing/AttributeRouteProvider.php';
require_once __DIR__ . '/../../framework/Asap/Routing/RouteManifestCompiler.php';
require_once __DIR__ . '/../fixtures/P112Q1/DemoRouteController.php';
require_once __DIR__ . '/../fixtures/P112Q1/DuplicateRouteController.php';

use ASAP\Routing\AttributeRouteProvider;
use ASAP\Routing\ClassIndex;
use ASAP\Routing\RouteCompilerException;
use ASAP\Routing\RouteManifestCompiler;
use ASAP\Tests\Fixtures\P112Q1\DemoRouteController;
use ASAP\Tests\Fixtures\P112Q1\DuplicateRouteController;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('ASSERT_FAILED: ' . $message);
    }
}

$classIndex = new ClassIndex([
    DemoRouteController::class => __DIR__ . '/../fixtures/P112Q1/DemoRouteController.php',
]);

assertTrue($classIndex->pathForClass(DemoRouteController::class) !== null, 'ClassIndex pathForClass');
assertTrue($classIndex->classesInNamespace('ASAP\\Tests\\Fixtures\\P112Q1') === [DemoRouteController::class], 'ClassIndex namespace filter');

$provider = new AttributeRouteProvider($classIndex);
$routes = $provider->routes('ASAP\\Tests\\Fixtures\\P112Q1');

assertTrue(count($routes) === 2, 'AttributeRouteProvider route count');
assertTrue($routes[0]->name === 'kb.item', 'Priority sorting');
assertTrue($routes[0]->acl === 'kb.read', 'ACL metadata');
assertTrue($routes[0]->source === 'attribute:' . DemoRouteController::class . '::item', 'Source metadata');

$compiler = new RouteManifestCompiler();
$manifest = $compiler->compile($routes);

assertTrue(isset($manifest['kb.search']), 'Manifest contains kb.search');
assertTrue($manifest['kb.search']['methods'] === ['GET'], 'Manifest methods');
assertTrue($manifest['kb.search']['controller'] === DemoRouteController::class, 'Manifest controller');
assertTrue($manifest['kb.search']['acl'] === 'kb.read', 'Manifest acl');

$target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'asap_p112q1_routes_' . getmypid() . '.php';
$compiler->writePhpManifest($manifest, $target);
$loaded = $compiler->loadPhpManifest($target);

assertTrue($loaded === $manifest, 'Manifest roundtrip');
@unlink($target);

$duplicateProvider = new AttributeRouteProvider(new ClassIndex([
    DemoRouteController::class,
    DuplicateRouteController::class,
]));

try {
    $compiler->compile($duplicateProvider->routes('ASAP\\Tests\\Fixtures\\P112Q1'));
    throw new RuntimeException('ASSERT_FAILED: duplicate path+method should fail');
} catch (RouteCompilerException $exception) {
    assertTrue(str_contains($exception->getMessage(), 'ASAP_ROUTE_PATH_METHOD_DUPLICATE'), 'Duplicate path+method detection');
}

echo 'P112Q1_ROUTER_ATTRIBUTE_COMPILER_SMOKE_OK' . PHP_EOL;
