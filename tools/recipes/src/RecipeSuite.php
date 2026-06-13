<?php

declare(strict_types=1);

namespace Opus\Recipe;

use Throwable;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Execute a deterministic ordered set of Opus recipes.
 *
 * Responsibility:
 *   Stop on first failure, preserve results, emit console markers and produce a
 *   final machine-readable status for CI or local validation.
 *
 * Contract:
 *   Recipes are registered explicitly by the manifest. No implicit filesystem
 *   discovery is used for validation-critical checks.
 */
final class RecipeSuite
{
    /** @var RecipeInterface[] */
    private array $recipes = [];

    /** PUBLIC API: add one recipe to the ordered suite. */
    public function add(RecipeInterface $recipe): void
    {
        $this->recipes[] = $recipe;
    }

    /**
     * PUBLIC API
     *
     * @return RecipeResult[] Ordered results.
     */
    public function run(RecipeContext $context): array
    {
        $results = [];
        foreach ($this->recipes as $recipe) {
            $started = microtime(true);
            try {
                $markers = $recipe->run($context);
                $diagnostics = $context->pullDiagnostics();
                foreach ($markers as $marker) {
                    echo $marker . PHP_EOL;
                }
                foreach ($diagnostics as $diagnostic) {
                    echo 'OPUS_RECIPE_DIAGNOSTIC=' . $diagnostic . PHP_EOL;
                }
                $results[] = new RecipeResult($recipe->name(), 'OK', microtime(true) - $started, $markers, $diagnostics);
            } catch (Throwable $exception) {
                $diagnostics = $context->pullDiagnostics();
                $message = $recipe->name() . ' :: ' . $exception->getMessage();
                echo 'OPUS_GLOBAL_RECIPE_FAILED=' . $message . PHP_EOL;
                $results[] = new RecipeResult($recipe->name(), 'FAILED', microtime(true) - $started, [], array_merge($diagnostics, [$message]));
                break;
            }
        }

        return $results;
    }
}
