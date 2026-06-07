<?php

declare(strict_types=1);

namespace ASAP\Recipe\Recipes;

use ASAP\Recipe\RecipeContext;
use ASAP\Recipe\RecipeInterface;

/** PUBLIC RECIPE: validate evolutive anti-regression feature manifest coverage. */
final class FeatureManifestRecipe implements RecipeInterface
{
    public function name(): string { return 'feature_manifest'; }

    public function run(RecipeContext $context): array
    {
        $manifestPath = $context->path('tools/recipes/manifest/asap_feature_manifest.php');
        $context->assert(is_file($manifestPath), 'ASAP_FEATURE_MANIFEST_MISSING');
        $manifest = require $manifestPath;
        $context->assert(is_array($manifest) && $manifest !== [], 'ASAP_FEATURE_MANIFEST_EMPTY');

        $ids = [];
        foreach ($manifest as $entry) {
            $context->assert(is_array($entry), 'ASAP_FEATURE_MANIFEST_ENTRY_INVALID');
            foreach (['id', 'label', 'technical_recipe', 'docs', 'paths'] as $required) {
                $context->assert(array_key_exists($required, $entry), 'ASAP_FEATURE_MANIFEST_FIELD_MISSING', (string)($entry['id'] ?? 'unknown') . ':' . $required);
            }
            $id = (string)$entry['id'];
            $context->assert($id !== '' && preg_match('/^[a-z0-9_]+$/', $id) === 1, 'ASAP_FEATURE_MANIFEST_ID_INVALID', $id);
            $context->assert(!isset($ids[$id]), 'ASAP_FEATURE_MANIFEST_DUPLICATE_ID', $id);
            $ids[$id] = true;

            foreach ((array)$entry['docs'] as $doc) {
                $context->assert(is_file($context->path((string)$doc)), 'ASAP_FEATURE_MANIFEST_DOC_MISSING', $id . ':' . (string)$doc);
            }
            foreach ((array)$entry['paths'] as $path) {
                $absolute = $context->path((string)$path);
                $context->assert(file_exists($absolute), 'ASAP_FEATURE_MANIFEST_PATH_MISSING', $id . ':' . (string)$path);
            }
            if (($entry['life_recipe'] ?? '') !== '') {
                $context->assert(is_string($entry['life_recipe']), 'ASAP_FEATURE_MANIFEST_LIFE_RECIPE_INVALID', $id);
            }
        }

        foreach (['autoload_cache', 'core_autoload', 'database_multi_provider', 'fsm', 'acl', 'i18n', 'routing', 'template', 'mail', 'real_mailpit_life_recipe', 'real_features_recipe_binding', 'lstsar', 'live_movie_dashboard', 'global_recipe_suite'] as $requiredId) {
            $context->assert(isset($ids[$requiredId]), 'ASAP_FEATURE_MANIFEST_REQUIRED_ID_MISSING', $requiredId);
        }

        return ['ASAP_FEATURE_MANIFEST_OK', 'ASAP_ANTI_REGRESSION_MANIFEST_OK'];
    }
}
