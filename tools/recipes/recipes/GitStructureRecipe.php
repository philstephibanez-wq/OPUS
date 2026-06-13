<?php

declare(strict_types=1);

namespace Opus\Recipe\Recipes;

use ASAP\Recipe\RecipeContext;
use ASAP\Recipe\RecipeInterface;

/** PUBLIC RECIPE: validate repository structure and ignored runtime paths. */
final class GitStructureRecipe implements RecipeInterface
{
    public function name(): string { return 'git_structure'; }

    public function run(RecipeContext $context): array
    {
        $gitignore = file_get_contents($context->path('.gitignore')) ?: '';
        foreach (['var/lstsa/', 'var/recipes/'] as $ignored) {
            $context->assert(str_contains($gitignore, $ignored), 'OPUS_GITIGNORE_RUNTIME_RULE_MISSING', $ignored);
        }
        $frameworkRoot = $context->path('framework');
        $entries = is_dir($frameworkRoot) ? (scandir($frameworkRoot) ?: []) : [];
        $context->assert(in_array('Opus', $entries, true) && is_dir($frameworkRoot . DIRECTORY_SEPARATOR . 'Opus'), 'OPUS_FRAMEWORK_OPUS_DIRECTORY_MISSING');
        $context->assert(!in_array('ASAP', $entries, true), 'OPUS_FORBIDDEN_UPPERCASE_FRAMEWORK_DIRECTORY_PRESENT');

        if (is_dir($context->path('.git'))) {
            $output = $context->runCommand('git ls-files', 'OPUS_GIT_LS_FILES_FAILED');
            foreach ($output as $tracked) {
                $normalized = str_replace('\\', '/', trim($tracked));
                $context->assert(!str_starts_with($normalized, 'var/'), 'OPUS_RUNTIME_FILE_TRACKED', $normalized);
                $context->assert(!str_contains($normalized, '/vendor/'), 'OPUS_VENDOR_FILE_TRACKED', $normalized);
            }
        } else {
            $context->diagnostic('Git worktree metadata not available; tracked runtime file check skipped explicitly.');
        }

        return ['OPUS_GIT_STRUCTURE_OK'];
    }
}
