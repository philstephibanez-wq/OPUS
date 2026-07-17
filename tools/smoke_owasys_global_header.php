<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$themeJsFile = $root . '/sites/owasys/www/asset/themes/owasys/js/theme.js';
$frontFile = $root . '/sites/owasys/www/index.php';

foreach ([$themeJsFile, $frontFile] as $requiredFile) {
    if (!is_file($requiredFile)) {
        fwrite(STDERR, 'OWASYS_GLOBAL_HEADER_REQUIRED_FILE_MISSING:' . $requiredFile . PHP_EOL);
        exit(1);
    }
}

$themeJs = (string) file_get_contents($themeJsFile);
$front = (string) file_get_contents($frontFile);

foreach ([
    'ow-global-header',
    'OWASYS_GLOBAL_HEADER',
    'ow-global-header-identity',
    'ow-global-header-actions',
    'OWASYS_GLOBAL_NAVIGATION',
    'OWASYS_GLOBAL_CURRENT_APPLICATION',
    'OWASYS_LOCALE_SWITCHER',
    'expectedCodes.length !== 25',
    'renderedBrand?.remove()',
    'sidebar?.remove()',
    'ow-shell-horizontal-navigation',
] as $marker) {
    if (!str_contains($themeJs, $marker)) {
        fwrite(STDERR, 'OWASYS_GLOBAL_HEADER_MARKER_MISSING:' . $marker . PHP_EOL);
        exit(1);
    }
}

foreach (['bg', 'hr', 'cs', 'da', 'nl', 'en', 'et', 'fi', 'fr', 'de', 'el', 'hu', 'ga', 'it', 'lv', 'lt', 'mt', 'pl', 'pt', 'ro', 'sk', 'sl', 'es', 'sv', 'uk'] as $locale) {
    if (!preg_match('/\\b' . preg_quote($locale, '/') . '\\s*:/', $themeJs)) {
        fwrite(STDERR, 'OWASYS_GLOBAL_HEADER_LOCALE_MISSING:' . $locale . PHP_EOL);
        exit(1);
    }
}

if (substr_count($themeJs, "form.dataset.context = 'OWASYS_LOCALE_SWITCHER'") !== 1) {
    fwrite(STDERR, 'OWASYS_GLOBAL_HEADER_LOCALE_SWITCHER_NOT_UNIQUE' . PHP_EOL);
    exit(1);
}

if (!str_contains($front, 'class="ow-brand"')) {
    fwrite(STDERR, 'OWASYS_GLOBAL_HEADER_SERVER_BRAND_SOURCE_MISSING' . PHP_EOL);
    exit(1);
}

if (!str_contains($front, 'class="ow-current-app"')) {
    fwrite(STDERR, 'OWASYS_GLOBAL_HEADER_CURRENT_APPLICATION_SOURCE_MISSING' . PHP_EOL);
    exit(1);
}

if (str_contains($themeJs, "identity.innerHTML = '<strong>OWASYS</strong>")) {
    fwrite(STDERR, 'OWASYS_GLOBAL_HEADER_HARDCODED_IDENTITY_PRESENT' . PHP_EOL);
    exit(1);
}

echo 'OWASYS_GLOBAL_HEADER_SMOKE_OK' . PHP_EOL;
