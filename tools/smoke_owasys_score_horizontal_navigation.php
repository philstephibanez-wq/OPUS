<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use Opus\Template\ScoreTemplateRenderer;

$site = $root . '/sites/owasys';
$renderer = new ScoreTemplateRenderer($site . '/application/default/templates');
$data = [
    'locale' => [
        'code' => 'fr',
        'action' => '/owasys/structure',
        'label' => 'Langue',
        'submit_label' => 'Appliquer',
        'current_label' => 'Français',
        'current_flag_id' => 'flag-fr',
        'flag_sprite' => '/owasys/asset/flags/locale-flags.svg',
        'preserved_query' => [],
        'options' => [[
            'code' => 'fr',
            'label' => 'Français',
            'flag_id' => 'flag-fr',
            'href' => '/owasys/structure?lang=fr',
            'selected' => true,
        ]],
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

foreach ([
    'OWASYS_GLOBAL_HEADER',
    'OWASYS_GLOBAL_NAVIGATION',
    'ow-global-nav-link',
    'score.css',
    'aria-label="Navigation principale"',
    'OWASYS_LOCALE_SWITCHER',
    'ow-locale-menu',
    '<svg class="ow-locale-flag"',
    '/owasys/asset/flags/locale-flags.svg#flag-fr',
    '/owasys/structure?lang=fr',
] as $required) {
    if (!str_contains($html, $required)) {
        throw new RuntimeException('OWASYS_SCORE_HORIZONTAL_MARKER_MISSING:' . $required);
    }
}
foreach (['ow-sidebar', 'class="ow-nav"', 'ow-shell', '<select name="lang"', '🇫🇷'] as $forbidden) {
    if (str_contains($html, $forbidden)) {
        throw new RuntimeException('OWASYS_SCORE_LEGACY_STRUCTURE_PRESENT:' . $forbidden);
    }
}

$homeData = $data;
$homeData['state']['id'] = 'home';
$homeData['page']['title'] = 'Accueil OWASYS';
$homeData['locale']['action'] = '/owasys/';
$homeData['locale']['options'][0]['href'] = '/owasys/?lang=fr';
$homeData['navigation']['current_state'] = 'home';
$homeData['content']['html'] = $renderer->render('partials/state-content.score', $homeData);
$homeHtml = $renderer->render('layouts/main.score', $homeData);
foreach (['OWASYS_LOCALE_SWITCHER', '/owasys/asset/flags/locale-flags.svg#flag-fr', '/owasys/?lang=fr'] as $homeMarker) {
    if (!str_contains($homeHtml, $homeMarker)) {
        throw new RuntimeException('OWASYS_HOME_SHARED_LOCALE_SELECTOR_MISSING:' . $homeMarker);
    }
}

$frontController = (string) file_get_contents($site . '/application/default/src/Http/FrontController.php');
if (!str_contains($frontController, "'score-page.php'")) {
    throw new RuntimeException('OWASYS_SCORE_GET_ROUTE_NOT_WIRED');
}

$scorePage = (string) file_get_contents($site . '/application/score-page.php');
foreach ([
    "'aria_label' => \$t('navigation.aria_label')",
    "'flag_id' => 'flag-fr'",
    "'flag_sprite' => \$flagSprite",
    "'current_flag_id' => \$currentLocalePresentation['flag_id']",
] as $sourceMarker) {
    if (!str_contains($scorePage, $sourceMarker)) {
        throw new RuntimeException('OWASYS_SCORE_VIEWMODEL_MARKER_MISSING:' . $sourceMarker);
    }
}

$localeTemplate = (string) file_get_contents($site . '/application/default/templates/partials/locale-switcher.score');
if (!str_contains($localeTemplate, '{{ locale.flag_sprite }}#{{ option.flag_id }}')) {
    throw new RuntimeException('OWASYS_SHARED_LOCALE_SPRITE_NOT_WIRED');
}

$layout = (string) file_get_contents($site . '/application/default/templates/layouts/main.score');
if (substr_count($layout, 'partials/locale-switcher.score') !== 1) {
    throw new RuntimeException('OWASYS_SHARED_LOCALE_SELECTOR_LAYOUT_INVALID');
}

$flagSprite = $site . '/www/asset/flags/locale-flags.svg';
if (!is_file($flagSprite)) {
    throw new RuntimeException('OWASYS_LOCALE_FLAG_SPRITE_MISSING');
}
$sprite = (string) file_get_contents($flagSprite);
foreach (['flag-fr', 'flag-en', 'flag-de', 'flag-world'] as $flagId) {
    if (!str_contains($sprite, 'id="' . $flagId . '"')) {
        throw new RuntimeException('OWASYS_LOCALE_FLAG_SYMBOL_MISSING:' . $flagId);
    }
}

echo 'OWASYS_SCORE_HORIZONTAL_NAVIGATION_SMOKE_OK' . PHP_EOL;
