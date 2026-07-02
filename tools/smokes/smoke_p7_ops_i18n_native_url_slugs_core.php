<?php
declare(strict_types=1);

echo 'P7_OPS_I18N_NATIVE_URL_SLUGS_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$publicDir = $root . '/sites/opus-p7-ops/public';

$files = [
    'language' => $publicDir . '/language.php',
    'router' => $publicDir . '/router.php',
    'index' => $publicDir . '/index.php',
    'css' => $publicDir . '/ops-ui.css',
    'readme' => $root . '/sites/opus-p7-ops/README.md',
];

$combined = '';
foreach ($files as $label => $file) {
    if (!is_file($file)) {
        throw new RuntimeException('NATIVE_URL_FILE_MISSING: ' . $label);
    }

    $source = file_get_contents($file);
    if ($source === false) {
        throw new RuntimeException('NATIVE_URL_READ_FAILED: ' . $label);
    }

    $combined .= $source . PHP_EOL;
}

foreach ([
    'P7_OPS_I18N_NATIVE_URL_SLUGS_CORE',
    'p7ops_native_page_slugs',
    'p7ops_native_path',
    'p7ops_native_url',
    'p7ops_resolve_native_route',
    'rawurldecode',
    'français',
    'español',
    'português',
    'čeština',
    'română',
    'Українська',
    'українська',
    'ελληνικά',
    'български',
    '/français/opérations',
    '/português/operações',
    '/čeština/přehled',
    '/українська/операції',
] as $marker) {
    if (!str_contains($combined, $marker)) {
        throw new RuntimeException('NATIVE_URL_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_NATIVE_URL_MARKERS=OK' . PHP_EOL;

require_once $files['language'];

$options = p7ops_language_options();
$expected = ['bg', 'hr', 'cs', 'da', 'nl', 'en', 'et', 'fi', 'fr', 'de', 'el', 'hu', 'ga', 'it', 'lv', 'lt', 'mt', 'pl', 'pt', 'ro', 'sk', 'sl', 'es', 'sv', 'uk'];

if (count($options) !== count($expected)) {
    throw new RuntimeException('NATIVE_URL_LANGUAGE_COUNT_INVALID: ' . count($options));
}

foreach ($expected as $code) {
    if (!array_key_exists($code, $options)) {
        throw new RuntimeException('NATIVE_URL_LANGUAGE_CODE_MISSING: ' . $code);
    }
}

foreach (['ru', 'no', 'tr', 'sr', 'sq', 'be', 'bs', 'ka', 'mk', 'is'] as $forbidden) {
    if (array_key_exists($forbidden, $options)) {
        throw new RuntimeException('NATIVE_URL_FORBIDDEN_LANGUAGE_PRESENT: ' . $forbidden);
    }
}

echo 'CHECK_P7_OPS_NATIVE_URL_LANGUAGE_SCOPE=OK' . PHP_EOL;

$cases = [
    ['/français/opérations', 'fr', '/opus-lstsar-manager/operations'],
    ['/español/panel', 'es', '/opus-lstsar-manager'],
    ['/português/operações', 'pt', '/opus-lstsar-manager/operations'],
    ['/čeština/přehled', 'cs', '/opus-lstsar-manager'],
    ['/українська/операції', 'uk', '/opus-lstsar-manager/operations'],
    ['/română/sănătate', 'ro', '/opus-lstsar-manager/health'],
];

foreach ($cases as [$path, $lang, $canonical]) {
    $resolved = p7ops_resolve_native_route($path);
    if ($resolved === null) {
        throw new RuntimeException('NATIVE_URL_RESOLVE_NULL: ' . $path);
    }

    if (($resolved['lang'] ?? null) !== $lang || ($resolved['canonical'] ?? null) !== $canonical) {
        throw new RuntimeException('NATIVE_URL_RESOLVE_INVALID: ' . $path);
    }
}

echo 'CHECK_P7_OPS_NATIVE_URL_RESOLUTION=OK' . PHP_EOL;

$_GET = ['site' => 'site-alpha', 'lang' => 'fr'];
$frOps = p7ops_native_url('/opus-lstsar-manager/operations', 'fr', 'site-alpha');
if (!str_contains($frOps, '/français/opérations?') || !str_contains($frOps, 'lang=fr')) {
    throw new RuntimeException('NATIVE_URL_FR_OPS_INVALID: ' . $frOps);
}

$_GET = ['site' => 'site-alpha', 'lang' => 'uk'];
$ukOps = p7ops_native_url('/opus-lstsar-manager/operations', 'uk', 'site-alpha');
if (!str_contains($ukOps, '/українська/операції?') || !str_contains($ukOps, 'lang=uk')) {
    throw new RuntimeException('NATIVE_URL_UK_OPS_INVALID: ' . $ukOps);
}

echo 'CHECK_P7_OPS_NATIVE_URL_GENERATION=OK' . PHP_EOL;

$_GET = ['site' => 'site-alpha', 'lang' => 'pt', 'operation' => 'lstsar.orders.import'];
$selector = p7ops_language_selector('/português/operações?site=site-alpha&lang=pt');

foreach ([
    'data-contract="P7_OPS_LANGUAGE_SELECTOR_CORE"',
    'data-scope-contract="P7_OPS_I18N_NATIVE_URL_SLUGS_CORE"',
    'data-lang-active="pt"',
    'data-native-url="/français/opérations"',
    'data-native-url="/português/operações"',
    'data-native-url="/українська/операції"',
    'site=site-alpha',
    'lang=pt',
    'Português — PT',
    'Українська — UK',
] as $marker) {
    if (!str_contains($selector, $marker)) {
        throw new RuntimeException('NATIVE_URL_SELECTOR_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_NATIVE_URL_SELECTOR=OK' . PHP_EOL;
echo 'P7_OPS_I18N_NATIVE_URL_SLUGS_CORE_SMOKE_OK' . PHP_EOL;
