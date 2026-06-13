<?php

declare(strict_types=1);

/**
 * PUBLIC SMOKE TEST
 *
 * Role:
 *   Validate the P112Q3B4 recipe patch without requiring Apache, UwAmp,
 *   Panther, SMTP, Mailpit or a browser.
 *
 * Responsibility:
 *   Check that the recipe owns a separate email-safe renderer, visible form
 *   fixtures, real POST scenarios and explicit Router method enforcement.
 *
 * Contract:
 *   This smoke is intentionally static. It must fail explicitly when a required
 *   marker disappears, and it must not claim that browser/mail/Panther runtime
 *   was executed.
 */

$root = dirname(__DIR__, 2);
$recipe = $root . '/tools/recipes/p112q3b2_secure_life_robotized_recipe.php';
$router = $root . '/framework/Opus/Routing/Router.php';

foreach ([$recipe, $router] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, 'P112Q3B4_FILE_MISSING: ' . $file . PHP_EOL);
        exit(1);
    }
}

$recipeSource = (string) file_get_contents($recipe);
$routerSource = (string) file_get_contents($router);

$requiredRecipeMarkers = [
    'function p112q3b2_build_email_safe_html_report',
    'p112q3b2_secure_life_robotized_recipe_email.html',
    '<form method="post"',
    'POST forms tested',
    'FORM_GUEST_SUBMIT',
    'FORM_EDITOR_SUBMIT',
    'FORM_ADMIN_SUBMIT',
    'form_public_fr',
    'form_editor_es',
    'form_admin_en',
    "'kind' => 'form'",
    "'method' => 'POST'",
    "'method' => 'GET'",
    'GET interdit sur formulaire POST',
];

foreach ($requiredRecipeMarkers as $marker) {
    if (!str_contains($recipeSource, $marker)) {
        fwrite(STDERR, 'P112Q3B4_RECIPE_MARKER_MISSING: ' . $marker . PHP_EOL);
        exit(1);
    }
}

$requiredRouterMarkers = [
    'OPUS_ROUTE_METHOD_NOT_ALLOWED',
    '$requestMethod = strtoupper(trim($request->method));',
    '$methodMismatches = [];',
    '$route->normalizedMethods()',
];

foreach ($requiredRouterMarkers as $marker) {
    if (!str_contains($routerSource, $marker)) {
        fwrite(STDERR, 'P112Q3B4_ROUTER_MARKER_MISSING: ' . $marker . PHP_EOL);
        exit(1);
    }
}

if (str_contains($recipeSource, '.bat') || str_contains($routerSource, '.bat')) {
    fwrite(STDERR, 'P112Q3B4_FORBIDDEN_BAT_MARKER_FOUND' . PHP_EOL);
    exit(1);
}

echo 'P112Q3B4_EMAIL_SAFE_FORMS_SMOKE_OK' . PHP_EOL;
