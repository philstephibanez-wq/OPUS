<?php

declare(strict_types=1);

namespace Opus\Recipe\Recipes;

use ASAP\Recipe\RecipeContext;
use ASAP\Recipe\RecipeInterface;

/** PUBLIC RECIPE: validate routing objects and no implicit route fallback. */
final class RoutingRecipe implements RecipeInterface
{
    public function name(): string { return 'routing'; }

    public function run(RecipeContext $context): array
    {
        $sandbox = $context->sandbox('routing');
        $routes = $sandbox . DIRECTORY_SEPARATOR . 'routes.xml';
        $security = $sandbox . DIRECTORY_SEPARATOR . 'security.xml';
        file_put_contents($routes, '<routes><route name="home" path="/"><target controllerClass="DemoController" action="home" /></route><route name="page" path="/{slug}"><target controllerClass="DemoController" action="page" /></route></routes>');
        file_put_contents($security, '<security></security>');
        $site = new \ASAP\Site\SiteDefinition('demo', '/demo', $routes, $security);
        $router = \ASAP\Routing\Router::fromXml($routes);
        $home = $router->match(new \ASAP\Http\Request('/demo', 'GET'), $site);
        $context->assert($home->name === 'home', 'OPUS_ROUTING_HOME_MATCH_FAILED');
        $page = $router->match(new \ASAP\Http\Request('/demo/about', 'GET'), $site);
        $context->assert($page->params['slug'] === 'about', 'OPUS_ROUTING_SLUG_MATCH_FAILED');
        try {
            $router->match(new \ASAP\Http\Request('/outside', 'GET'), $site);
            $context->assert(false, 'OPUS_ROUTING_OUTSIDE_SITE_DID_NOT_FAIL');
        } catch (\ASAP\Contract\ContractException) {
        }

        return ['OPUS_ROUTING_OK'];
    }
}
