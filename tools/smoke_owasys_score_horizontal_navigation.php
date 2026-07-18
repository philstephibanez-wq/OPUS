<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use Opus\Template\ScoreTemplateRenderer;

$site = $root . '/sites/owasys';
$renderer = new ScoreTemplateRenderer($site . '/application/default/templates');
$data = [
    'locale' => [
        'code' => 'fr', 'action' => '/owasys/structure', 'label' => 'Langue', 'submit_label' => 'Appliquer',
        'preserved_query' => [], 'options' => [['code' => 'fr', 'label' => 'Français', 'selected' => true]],
    ],
    'page' => ['title' => 'Structure', 'summary' => 'Résumé'],
    'state' => ['id' => 'structure'],
    'brand' => ['name' => 'OWASYS', 'long_name' => 'OPUS Web Application System'],
    'routes' => ['home' => '/owasys/', 'applications' => '/owasys/applications'],
    'assets' => ['theme_css' => '/owasys/asset/css/score.css', 'theme_js' => '/owasys/asset/js/owasys.js'],
    'auth' => ['authenticated' => true, 'label' => 'admin', 'profile' => 'admin'],
    'security' => ['csrf' => 'test-token'],
    'navigation' => [
        'action' => '/owasys/',
        'current_state' => 'structure',
        'aria_label' => 'Navigation principale',
        'items' => [['event' => 'open_home', 'label' => 'Accueil', 'current' => false]],
    ],
    'current_application' => [
        'present' => false, 'name' => '', 'id' => '', 'kind' => '', 'root_path' => '', 'status' => '',
        'working_label' => '', 'change_label' => '', 'id_label' => '', 'type_label' => '', 'root_label' => '', 'status_label' => '',
    ],
    'content' => [
        'section_title' => 'Configuration OPUS', 'section_summary' => 'Résumé',
        'contracts_label' => 'Contrats', 'actions_label' => 'Actions',
        'has_contracts' => false, 'contracts' => [], 'has_actions' => false, 'actions' => [],
    ],
];
$data['content']['html'] = $renderer->render('partials/state-content.score', $data);
$html = $renderer->render('layouts/main.score', $data);

foreach (['OWASYS_GLOBAL_HEADER', 'OWASYS_GLOBAL_NAVIGATION', 'ow-global-nav-link', 'score.css', 'aria-label="Navigation principale"'] as $required) {
    if (!str_contains($html, $required)) {
        throw new RuntimeException('OWASYS_SCORE_HORIZONTAL_MARKER_MISSING:' . $required);
    }
}
foreach (['ow-sidebar', 'class="ow-nav"', 'ow-shell'] as $forbidden) {
    if (str_contains($html, $forbidden)) {
        throw new RuntimeException('OWASYS_SCORE_VERTICAL_NAVIGATION_PRESENT:' . $forbidden);
    }
}

$frontController = (string) file_get_contents($site . '/application/default/src/Http/FrontController.php');
if (!str_contains($frontController, "'score-page.php'")) {
    throw new RuntimeException('OWASYS_SCORE_GET_ROUTE_NOT_WIRED');
}

$scorePage = (string) file_get_contents($site . '/application/score-page.php');
if (!str_contains($scorePage, "'aria_label' => $t('navigation.aria_label')")) {
    throw new RuntimeException('OWASYS_SCORE_NAVIGATION_ARIA_NOT_WIRED');
}

echo 'OWASYS_SCORE_HORIZONTAL_NAVIGATION_SMOKE_OK' . PHP_EOL;
