<?php

declare(strict_types=1);

namespace Opus\Recipe\Recipes;

use ASAP\Recipe\RecipeContext;
use ASAP\Recipe\RecipeInterface;

/** PUBLIC RECIPE: validate active documentation and changelog contract markers. */
final class DocsRecipe implements RecipeInterface
{
    public function name(): string { return 'docs'; }

    public function run(RecipeContext $context): array
    {
        foreach ([
            'README.md',
            'DOC/DOC_GENERATION.md',
            'DOC/REFERENCE_BOOKS.md',
            'DOC/P112Q2I5_OPUS_Lstsa_FSM_BACKGROUND_STAGING.md',
            'DOC/P112Q2J_OPUS_GLOBAL_RECIPE_SUITE.md',
            'DOC/P112Q2J2_OPUS_GLOBAL_EVOLUTIVE_HTTP_MAIL_LIFE_RECIPE.md',
            'DOC/P112Q2J3_OPUS_RECIPE_LIVE_MOVIE_DASHBOARD.md',
            'DOC/P112Q2J4_OPUS_REAL_MAILPIT_LIVE_RECIPE.md',
            'DOC/P112Q2K_OPUS_REAL_FEATURES_RECIPE_BINDING.md',
            'DOC/P112Q2K1_OPUS_AUTOLOADER_CACHE_CONTRACT.md',
            'DOC/P112Q2L_OPUS_REAL_REFBOOK_HTTP_DIAGNOSTICS.md',
        ] as $file) {
            $context->assertFile($file);
        }
        $readme = file_get_contents($context->path('README.md')) ?: '';
        $suiteDoc = file_get_contents($context->path('DOC/P112Q2J_OPUS_GLOBAL_RECIPE_SUITE.md')) ?: '';
        $lifeDoc = file_get_contents($context->path('DOC/P112Q2J2_OPUS_GLOBAL_EVOLUTIVE_HTTP_MAIL_LIFE_RECIPE.md')) ?: '';
        $movieDoc = file_get_contents($context->path('DOC/P112Q2J3_OPUS_RECIPE_LIVE_MOVIE_DASHBOARD.md')) ?: '';
        $mailpitDoc = file_get_contents($context->path('DOC/P112Q2J4_OPUS_REAL_MAILPIT_LIVE_RECIPE.md')) ?: '';
        $realFeaturesDoc = file_get_contents($context->path('DOC/P112Q2K_OPUS_REAL_FEATURES_RECIPE_BINDING.md')) ?: '';
        $autoloadCacheDoc = file_get_contents($context->path('DOC/P112Q2K1_OPUS_AUTOLOADER_CACHE_CONTRACT.md')) ?: '';
        $realDiagnosticsDoc = file_get_contents($context->path('DOC/P112Q2L_OPUS_REAL_REFBOOK_HTTP_DIAGNOSTICS.md')) ?: '';
        $context->assert(str_contains($readme, 'NO DOC CONTRACT, NO PATCH'), 'OPUS_DOC_CONTRACT_MARKER_MISSING');
        foreach (['technical recipes', 'life robot', 'manifest', 'OPUS_GLOBAL_RECIPE_OK'] as $needle) {
            $context->assert(str_contains($suiteDoc, $needle), 'OPUS_Q2J_DOC_SECTION_MISSING', $needle);
        }
        foreach (['Visible dashboard', 'OPUS_RECIPE_DASHBOARD_RICH_OK', 'MailRobot', 'anti-regression', 'feature manifest'] as $needle) {
            $context->assert(str_contains($lifeDoc, $needle), 'OPUS_Q2J2_DOC_SECTION_MISSING', $needle);
        }
        foreach (['OPUS_LIVE_MOVIE_DASHBOARD_OK', 'Live timeline', 'polling', 'movie dashboard'] as $needle) {
            $context->assert(str_contains($movieDoc, $needle), 'OPUS_Q2J3_DOC_SECTION_MISSING', $needle);
        }
        foreach (['Mailpit réel', 'SMTP 127.0.0.1:1025', 'API 127.0.0.1:8025', 'OPUS_MAILPIT_RECEIVED_OK'] as $needle) {
            $context->assert(str_contains($mailpitDoc, $needle), 'OPUS_Q2J4_DOC_SECTION_MISSING', $needle);
        }
        foreach (['OPUS_REF_BOOK', 'real feature binding', 'opus-mail-recipe.php', 'auto-recipe', 'OPUS_REAL_FEATURE_BINDING_OK'] as $needle) {
            $context->assert(str_contains($realFeaturesDoc, $needle), 'OPUS_Q2K_DOC_SECTION_MISSING', $needle);
        }
        foreach (['OPUS_AUTOLOADER_CACHE_OK', 'class index', 'var/cache/asap/autoload'] as $needle) {
            $context->assert(str_contains($autoloadCacheDoc, $needle), 'OPUS_Q2K1_DOC_SECTION_MISSING', $needle);
        }
        foreach (['OPUS_REAL_FEATURE_BINDING_DIAGNOSTICS_OK', 'diagnostic JSON', 'body excerpt', 'opaque 500'] as $needle) {
            $context->assert(str_contains($realDiagnosticsDoc, $needle), 'OPUS_Q2L_DOC_SECTION_MISSING', $needle);
        }

        return ['OPUS_DOCS_OK'];
    }
}
