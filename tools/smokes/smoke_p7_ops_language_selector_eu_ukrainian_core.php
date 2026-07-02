<?php
declare(strict_types=1);

echo 'P7_OPS_LANGUAGE_SELECTOR_EU_UKRAINIAN_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$language = $root . '/sites/opus-p7-ops/public/language.php';

if (!is_file($language)) {
    throw new RuntimeException('EU_UKRAINIAN_LANGUAGE_SELECTOR_FILE_MISSING');
}

$source = file_get_contents($language);
if ($source === false) {
    throw new RuntimeException('EU_UKRAINIAN_LANGUAGE_SELECTOR_READ_FAILED');
}

foreach ([
    'P7_OPS_LANGUAGE_SELECTOR_CORE',
    'P7_OPS_I18N_NATIVE_URL_SLUGS_CORE',
    'p7ops_language_options',
    'EU official languages + Ukrainian',
    'UE + Ukrainian',
    'Français',
    'English',
    'Deutsch',
    'Español',
    'Italiano',
    'Português',
    'Nederlands',
    'Polski',
    'Українська',
] as $marker) {
    if (!str_contains($source, $marker)) {
        throw new RuntimeException('EU_UKRAINIAN_LANGUAGE_SELECTOR_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_LANGUAGE_SELECTOR_EU_UKRAINIAN_MARKERS=OK' . PHP_EOL;

require_once $language;

$options = p7ops_language_options();
$expected = ['bg', 'hr', 'cs', 'da', 'nl', 'en', 'et', 'fi', 'fr', 'de', 'el', 'hu', 'ga', 'it', 'lv', 'lt', 'mt', 'pl', 'pt', 'ro', 'sk', 'sl', 'es', 'sv', 'uk'];

if (count($options) !== count($expected)) {
    throw new RuntimeException('EU_UKRAINIAN_LANGUAGE_SELECTOR_COUNT_INVALID: ' . count($options));
}

foreach ($expected as $code) {
    if (!array_key_exists($code, $options)) {
        throw new RuntimeException('EU_UKRAINIAN_LANGUAGE_SELECTOR_CODE_MISSING: ' . $code);
    }
}

foreach (['ru', 'no', 'tr', 'sr', 'sq', 'be', 'bs', 'ka', 'mk', 'is'] as $forbidden) {
    if (array_key_exists($forbidden, $options)) {
        throw new RuntimeException('EU_UKRAINIAN_LANGUAGE_SELECTOR_NON_EU_CODE_PRESENT: ' . $forbidden);
    }
}

echo 'CHECK_P7_OPS_LANGUAGE_SELECTOR_EU_UKRAINIAN_OPTIONS=OK' . PHP_EOL;

$_GET = ['site' => 'site-alpha', 'lang' => 'uk'];
if (p7ops_language() !== 'uk') {
    throw new RuntimeException('EU_UKRAINIAN_LANGUAGE_SELECTOR_UK_INVALID');
}

$_GET = ['site' => 'site-alpha', 'lang' => 'bad'];
if (p7ops_language() !== 'fr') {
    throw new RuntimeException('EU_UKRAINIAN_LANGUAGE_SELECTOR_FALLBACK_INVALID');
}

$_GET = ['site' => 'site-alpha', 'lang' => 'uk'];
$selector = p7ops_language_selector('/українська/операції?site=site-alpha&lang=uk');
foreach ([
    '<select',
    'data-contract="P7_OPS_LANGUAGE_SELECTOR_CORE"',
    'data-lang-active="uk"',
    'site=site-alpha',
    'Українська — UK',
    'Français — FR',
    'English — EN',
] as $marker) {
    if (!str_contains($selector, $marker)) {
        throw new RuntimeException('EU_UKRAINIAN_LANGUAGE_SELECTOR_RENDER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_LANGUAGE_SELECTOR_EU_UKRAINIAN_RENDER=OK' . PHP_EOL;
echo 'P7_OPS_LANGUAGE_SELECTOR_EU_UKRAINIAN_CORE_SMOKE_OK' . PHP_EOL;
