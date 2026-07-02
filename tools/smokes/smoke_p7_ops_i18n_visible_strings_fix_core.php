<?php
declare(strict_types=1);

echo 'P7_OPS_I18N_VISIBLE_STRINGS_FIX_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$languageFile = $root . '/sites/opus-p7-ops/public/language.php';
$indexFile = $root . '/sites/opus-p7-ops/public/index.php';
$readmeFile = $root . '/sites/opus-p7-ops/README.md';

foreach ([$languageFile, $indexFile, $readmeFile] as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('VISIBLE_TRANSLATION_FILE_MISSING: ' . $file);
    }
}

$source = file_get_contents($languageFile);
if ($source === false) {
    throw new RuntimeException('VISIBLE_TRANSLATION_LANGUAGE_READ_FAILED');
}

foreach ([
    'P7_OPS_I18N_VISIBLE_STRINGS_FIX_CORE',
    'Compteurs OPS',
    'Броячи OPS',
    'Prochaines étapes',
    'Следващи стъпки',
    'Dashboard overview',
    'Преглед на таблото',
    'Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.',
    'Кратък изглед на наличните операции',
] as $marker) {
    if (!str_contains($source, $marker)) {
        throw new RuntimeException('VISIBLE_TRANSLATION_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_VISIBLE_TRANSLATION_MARKERS=OK' . PHP_EOL;

require_once $languageFile;

$sample = implode("\n", [
    'Compteurs OPS',
    'Active Ready Blocked ready',
    'Dashboard overview',
    'Dashboard digest',
    'Synthèse',
    'Prochaines étapes',
    'Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.',
    'Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.',
    'Operation Source Destination Action',
]);

$cases = [
    'bg' => ['Броячи OPS', 'Готово', 'Следващи стъпки', 'Операция Източник Дестинация Действие'],
    'es' => ['Contadores OPS', 'Listo', 'Próximos pasos', 'Operación Origen Destino Acción'],
    'pt' => ['Contadores OPS', 'Pronto', 'Próximas etapas', 'Operação Origem Destino Ação'],
    'uk' => ['Лічильники OPS', 'Готово', 'Наступні кроки', 'Операція Джерело Призначення Дія'],
];

foreach ($cases as $lang => $markers) {
    $_GET = ['site' => 'site-alpha', 'lang' => $lang];
    $translated = p7ops_i18n_translate_html($sample);

    foreach ($markers as $marker) {
        if (!str_contains($translated, $marker)) {
            throw new RuntimeException('VISIBLE_TRANSLATION_DIRECT_MISSING: ' . $lang . ' => ' . $marker . ' IN ' . $translated);
        }
    }
}

echo 'CHECK_P7_OPS_VISIBLE_TRANSLATION_DIRECT=OK' . PHP_EOL;

$render = static function (string $uri, array $get) use ($indexFile): string {
    $_SERVER['REQUEST_URI'] = $uri;
    $_GET = $get;
    ob_start();
    (static function (string $__file): void {
        require $__file;
    })($indexFile);
    $out = ob_get_clean();
    return is_string($out) ? p7ops_i18n_translate_html($out) : '';
};

$bg = $render('/български/операции?site=site-alpha&lang=bg', ['site' => 'site-alpha', 'lang' => 'bg']);

foreach ([
    'Броячи OPS',
    'Преглед на таблото',
    'Обобщение на таблото',
    'Следващи стъпки',
    'Операция',
    'Източник',
    'Дестинация',
    'Действие',
    'готово',
] as $marker) {
    if (!str_contains($bg, $marker)) {
        throw new RuntimeException('VISIBLE_TRANSLATION_BG_RENDER_MISSING: ' . $marker);
    }
}

foreach ([
    'Compteurs OPS',
    'Табло overview',
    'Табло digest',
    'Prochaines étapes',
    '>Operation<',
    '>Source<',
    '>Destination<',
] as $forbidden) {
    if (str_contains($bg, $forbidden)) {
        throw new RuntimeException('VISIBLE_TRANSLATION_BG_RENDER_FORBIDDEN: ' . $forbidden);
    }
}

echo 'CHECK_P7_OPS_VISIBLE_TRANSLATION_RENDER=OK' . PHP_EOL;
echo 'P7_OPS_I18N_VISIBLE_STRINGS_FIX_CORE_SMOKE_OK' . PHP_EOL;
