<?php
declare(strict_types=1);

/* P7_OPS_SMOKE_OUTPUT_BUFFER_HEADER_LOCK */
ob_start();

echo 'P7_OPS_I18N_EN_VISIBLE_LEAK_LOCK_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$publicDir = $root . '/sites/opus-p7-ops/public';
$languageFile = $publicDir . '/language.php';

if (!is_file($languageFile)) {
    throw new RuntimeException('EN_VISIBLE_LEAK_LANGUAGE_FILE_MISSING');
}

$languageSource = file_get_contents($languageFile);
if ($languageSource === false) {
    throw new RuntimeException('EN_VISIBLE_LEAK_LANGUAGE_READ_FAILED');
}

foreach ([
    'P7_OPS_I18N_EN_VISIBLE_LEAK_LOCK_CORE',
    'Table détaillée avec source/destination résumées.',
    'Detailed table with summarized source/destination.',
    'Les structures longues sont wrappées et confinées dans le panel.',
    'Long structures are wrapped and confined in the panel.',
] as $marker) {
    if (!str_contains($languageSource, $marker)) {
        throw new RuntimeException('EN_VISIBLE_LEAK_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_EN_VISIBLE_LEAK_MARKERS=OK' . PHP_EOL;

require_once $languageFile;

$_GET = ['site' => 'site-alpha', 'lang' => 'en'];
$sample = implode("\n", [
    'Table détaillée avec source/destination résumées. Les structures longues sont wrappées et confinées dans le panel.',
    'Compteurs OPS',
    'Synthèse',
    'Prochaines étapes',
    'Opération Source Destination Action',
    'Actif Prêt Bloqué prêt',
]);

$translated = p7ops_i18n_translate_html($sample);

foreach ([
    'Detailed table with summarized source/destination. Long structures are wrapped and confined in the panel.',
    'OPS counters',
    'Summary',
    'Next steps',
    'Operation Source Destination Action',
    'Active Ready Blocked ready',
] as $marker) {
    if (!str_contains($translated, $marker)) {
        throw new RuntimeException('EN_VISIBLE_LEAK_DIRECT_TRANSLATION_MISSING: ' . $marker . ' IN ' . $translated);
    }
}

echo 'CHECK_P7_OPS_EN_VISIBLE_LEAK_DIRECT=OK' . PHP_EOL;

$render = static function (string $file, string $uri): string {
    $_SERVER['REQUEST_URI'] = $uri;
    $_GET = ['site' => 'site-alpha', 'lang' => 'en'];
    ob_start();
    (static function (string $__file): void {
        require $__file;
    })($file);
    $html = ob_get_clean();

    return is_string($html) ? p7ops_i18n_translate_html($html) : '';
};

$pages = [
    'index' => [$publicDir . '/index.php', '/english/operations?site=site-alpha&lang=en'],
    'action' => [$publicDir . '/action.php', '/opus-lstsar-manager/action?site=site-alpha&lang=en'],
    'command' => [$publicDir . '/command.php', '/opus-lstsar-manager/command-center?site=site-alpha&lang=en'],
    'navigation' => [$publicDir . '/navigation.php', '/opus-lstsar-manager/navigation?site=site-alpha&lang=en'],
    'diagnostics' => [$publicDir . '/diagnostics.php', '/opus-lstsar-manager/diagnostics?site=site-alpha&lang=en'],
    'health' => [$publicDir . '/health.php', '/opus-lstsar-manager/health?site=site-alpha&lang=en'],
];

$rendered = '';
foreach ($pages as $name => [$file, $uri]) {
    if (!is_file($file)) {
        throw new RuntimeException('EN_VISIBLE_LEAK_PAGE_FILE_MISSING: ' . $name);
    }

    $html = $render($file, $uri);
    if ($html === '') {
        throw new RuntimeException('EN_VISIBLE_LEAK_EMPTY_RENDER: ' . $name);
    }

    $rendered .= "\n<!-- PAGE " . $name . " -->\n" . $html;
}

$visible = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $rendered);
$visible = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', is_string($visible) ? $visible : $rendered);
$visible = preg_replace('/<!--.*?-->/s', ' ', is_string($visible) ? $visible : $rendered);
$visible = html_entity_decode(strip_tags(is_string($visible) ? $visible : $rendered), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$visible = preg_replace('/\s+/u', ' ', is_string($visible) ? $visible : '');

foreach ([
    'Detailed table with summarized source/destination. Long structures are wrapped and confined in the panel.',
    'OPS counters',
    'Operations console',
    'Operation',
    'Status',
    'Source summary',
    'Destination summary',
    'Actions',
] as $marker) {
    if (!str_contains($visible, $marker)) {
        throw new RuntimeException('EN_VISIBLE_LEAK_EN_MARKER_MISSING: ' . $marker);
    }
}

foreach ([
    'Table détaillée',
    'résumées',
    'wrappées',
    'confinées',
    'Les structures longues',
    'Compteurs OPS',
    'Synthèse',
    'Prochaines étapes',
    'Vue courte des opérations',
    'sans JSON brut',
    'colonnes techniques',
    'Ouvre Operations pour',
    'Ouvre Opérations pour',
    'pour le détail',
    'matrice globale',
    'Tableau de bord',
    'Centre de commande',
    'Centre de santé',
    'Diagnostics d’exécution',
    'Console détaillée séparée',
    'État global',
    'Navigation directe',
    'Opérations',
    'Opération',
    'Statut',
    'Aperçu',
    'Simulation',
    'Ouvrir',
    'Exécuter',
    'Détails',
    'Contrôles',
    'Fichiers',
    'Avertissement',
    'Erreur',
    'Sans effet de bord',
    'Actif',
    'Prêt',
    'Bloqué',
    'prêt',
    'bloqué',
] as $forbidden) {
    if (str_contains($visible, $forbidden)) {
        throw new RuntimeException('EN_VISIBLE_LEAK_FORBIDDEN: ' . $forbidden);
    }
}

echo 'CHECK_P7_OPS_EN_VISIBLE_LEAK_RENDER=OK' . PHP_EOL;
echo 'P7_OPS_I18N_EN_VISIBLE_LEAK_LOCK_CORE_SMOKE_OK' . PHP_EOL;
if (ob_get_level() > 0) { ob_end_flush(); }
