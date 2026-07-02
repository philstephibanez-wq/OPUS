<?php
declare(strict_types=1);

echo 'P7_OPS_I18N_PAGE_TRANSLATIONS_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$publicDir = $root . '/sites/opus-p7-ops/public';

$files = [
    'language' => $publicDir . '/language.php',
    'index' => $publicDir . '/index.php',
    'router' => $publicDir . '/router.php',
    'css' => $publicDir . '/ops-ui.css',
    'readme' => $root . '/sites/opus-p7-ops/README.md',
];

$combined = '';
foreach ($files as $label => $file) {
    if (!is_file($file)) {
        throw new RuntimeException('PAGE_TRANSLATIONS_FILE_MISSING: ' . $label);
    }
    $source = file_get_contents($file);
    if ($source === false) {
        throw new RuntimeException('PAGE_TRANSLATIONS_READ_FAILED: ' . $label);
    }
    $combined .= $source . PHP_EOL;
}

foreach ([
    'P7_OPS_I18N_PAGE_TRANSLATIONS_CORE',
    'p7ops_i18n_page_translation_dictionary',
    'p7ops_i18n_translate_html',
    'p7ops_i18n_translation_map',
    'p7ops_i18n_begin',
    'Tableau de bord',
    'Panel',
    'Painel',
    'Přehled',
    'Панель',
    'Opérations',
    'Operaciones',
    'Operações',
    'Operace',
    'Операції',
    'Центр стану',
    'data-scope-contract="P7_OPS_I18N_PAGE_TRANSLATIONS_CORE"',
] as $marker) {
    if (!str_contains($combined, $marker)) {
        throw new RuntimeException('PAGE_TRANSLATIONS_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_PAGE_TRANSLATION_MARKERS=OK' . PHP_EOL;

require_once $files['language'];

$options = p7ops_language_options();
if (count($options) !== 25) {
    throw new RuntimeException('PAGE_TRANSLATIONS_LANGUAGE_SCOPE_INVALID: ' . count($options));
}

foreach (['fr', 'es', 'pt', 'cs', 'ro', 'uk'] as $code) {
    $map = p7ops_i18n_translation_map($code);
    if ($map === []) {
        throw new RuntimeException('PAGE_TRANSLATIONS_MAP_EMPTY: ' . $code);
    }
}

$cases = [
    ['fr', 'OPUS OPS Dashboard', 'Tableau de bord OPUS OPS'],
    ['es', 'OPUS OPS Dashboard', 'Panel OPUS OPS'],
    ['pt', 'Operations digest', 'Resumo das operações'],
    ['cs', 'Operations detail', 'Detail operací'],
    ['ro', 'Health snapshot', 'Instantaneu stare'],
    ['uk', 'OPUS OPS Operations Console', 'Консоль операцій OPUS OPS'],
];

foreach ($cases as [$lang, $source, $expected]) {
    $_GET = ['site' => 'site-alpha', 'lang' => $lang];
    $translated = p7ops_i18n_translate_html($source);
    if (!str_contains($translated, $expected)) {
        throw new RuntimeException('PAGE_TRANSLATIONS_DIRECT_INVALID: ' . $lang . ' => ' . $translated);
    }
}

echo 'CHECK_P7_OPS_PAGE_TRANSLATION_MAPS=OK' . PHP_EOL;

if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
}

$render = static function (string $file, string $uri, array $get): string {
    $_SERVER['REQUEST_URI'] = $uri;
    $_GET = $get;
    ob_start();
    (static function (string $__file): void {
        require $__file;
    })($file);
    $out = ob_get_clean();
    return is_string($out) ? p7ops_i18n_translate_html($out) : '';
};

$fr = $render($files['index'], '/français/opérations?site=site-alpha&lang=fr', ['site' => 'site-alpha', 'lang' => 'fr']);
foreach (['Tableau de bord OPUS OPS', 'Synthèse des opérations', 'État de santé', 'Accès rapide'] as $marker) {
    if (!str_contains($fr, $marker)) {
        throw new RuntimeException('PAGE_TRANSLATIONS_FR_RENDER_MISSING: ' . $marker);
    }
}

$es = $render($files['index'], '/español/panel?site=site-alpha&lang=es', ['site' => 'site-alpha', 'lang' => 'es']);
foreach (['Panel OPUS OPS', 'Resumen de operaciones', 'Instantánea de estado', 'Acceso rápido'] as $marker) {
    if (!str_contains($es, $marker)) {
        throw new RuntimeException('PAGE_TRANSLATIONS_ES_RENDER_MISSING: ' . $marker);
    }
}

$uk = $render($files['index'], '/українська/операції?site=site-alpha&lang=uk', ['site' => 'site-alpha', 'lang' => 'uk']);
foreach (['Панель OPUS OPS', 'Зведення операцій', 'Знімок стану', 'Швидкий доступ'] as $marker) {
    if (!str_contains($uk, $marker)) {
        throw new RuntimeException('PAGE_TRANSLATIONS_UK_RENDER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_PAGE_TRANSLATION_RENDER=OK' . PHP_EOL;
echo 'P7_OPS_I18N_PAGE_TRANSLATIONS_CORE_SMOKE_OK' . PHP_EOL;
