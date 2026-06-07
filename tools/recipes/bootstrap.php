<?php

declare(strict_types=1);

$recipeRoot = __DIR__;
$requires = [
    'src/RecipeAssertionFailedException.php',
    'src/RecipeResult.php',
    'src/RecipeInterface.php',
    'src/RecipeContext.php',
    'src/RecipeSuite.php',
    'src/RecipeReport.php',
    'life/RobotActor.php',
    'life/RobotSession.php',
    'life/RobotStep.php',
    'life/RobotScenario.php',
    'life/LifeScenarioRunner.php',
    'recipes/PreflightRecipe.php',
    'recipes/GitStructureRecipe.php',
    'recipes/NamingRecipe.php',
    'recipes/PhpLintRecipe.php',
    'recipes/DocsRecipe.php',
    'recipes/FeatureManifestRecipe.php',
    'recipes/AutoloadCacheRecipe.php',
    'recipes/CoreAutoloadRecipe.php',
    'recipes/DatabaseRecipe.php',
    'recipes/FsmRecipe.php',
    'recipes/AclRecipe.php',
    'recipes/I18nRecipe.php',
    'recipes/RoutingRecipe.php',
    'recipes/TemplateRecipe.php',
    'recipes/MailRecipe.php',
    'recipes/RealFeatureBindingRecipe.php',
    'recipes/LstsaRecipe.php',
    'life/scenarios/PublicSiteLifecycleScenario.php',
    'life/scenarios/AclAccessLifecycleScenario.php',
    'life/scenarios/I18nLifecycleScenario.php',
    'life/scenarios/DatabaseLifecycleScenario.php',
    'life/scenarios/LstsarBackgroundLifecycleScenario.php',
    'life/scenarios/LstsarFailureLifecycleScenario.php',
    'life/scenarios/LstsarConcurrencyLifecycleScenario.php',
    'life/scenarios/MaintenanceLifecycleScenario.php',
    'life/scenarios/HttpMailLifeRobotScenario.php',
    'src/RecipeManifest.php',
];

foreach ($requires as $relative) {
    require_once $recipeRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}
