<?php

declare(strict_types=1);

namespace Opus\Recipe\Recipes;

use ASAP\Recipe\RecipeContext;
use ASAP\Recipe\RecipeInterface;

/** PUBLIC RECIPE: lint all active Opus PHP sources included in the framework and recipes. */
final class PhpLintRecipe implements RecipeInterface
{
    public function name(): string { return 'php_lint'; }

    public function run(RecipeContext $context): array
    {
        $files = $context->phpFiles(['framework/Opus', 'tools/automation', 'tests/recipe', 'tools/recipes']);
        $context->assert($files !== [], 'OPUS_PHP_LINT_NO_FILES');
        foreach ($files as $file) {
            $cmd = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file);
            $context->runCommand($cmd, 'OPUS_PHP_LINT_FAILED ' . $context->relativePath($file));
        }

        return ['OPUS_PHP_LINT_OK'];
    }
}
