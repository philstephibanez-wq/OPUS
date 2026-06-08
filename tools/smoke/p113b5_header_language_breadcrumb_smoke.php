<?php
declare(strict_types=1);

/**
 * PUBLIC SMOKE TEST
 *
 * Role:
 *   Validate the P113B5 RefBook UI contract without requiring Apache/UwAmp.
 *
 * Contract:
 *   Read-only smoke. It checks the persistent header, header language switcher,
 *   breadcrumb, CSS cache busting and I18N keys.
 */
$root = dirname(__DIR__, 2);

$layoutFile = $root . '/application/reference/templates/layout.twig';
$cssFile = $root . '/public/assets/css/refbook.css';
$i18nRoot = $root . '/content/refbook/i18n';

foreach ([$layoutFile, $cssFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, 'P113B5_FILE_MISSING=' . $file . PHP_EOL);
        exit(1);
    }
}

$layout = (string) file_get_contents($layoutFile);
$css = (string) file_get_contents($cssFile);

$layoutNeedles = [
    'class="site-header"',
    'class="site-brand"',
    'class="language-switcher',
    'class="breadcrumb"',
    'refbook.css?v=P113B',
    'breadcrumbTitle',
    'ui.topbar.subtitle',
    'ui.breadcrumb.label',
];

foreach ($layoutNeedles as $needle) {
    if ($needle === 'refbook.css?v=P113B') {
        if (!str_contains($layout, 'refbook.css?v=P113B5') && !str_contains($layout, 'refbook.css?v=P113B6') && !str_contains($layout, 'refbook.css?v=P113B7') && !str_contains($layout, 'refbook.css?v=P113B8')) {
            fwrite(STDERR, 'P113B5_LAYOUT_NEEDLE_MISSING=' . $needle . PHP_EOL);
            exit(1);
        }
        continue;
    }

    if (!str_contains($layout, $needle)) {
        fwrite(STDERR, 'P113B5_LAYOUT_NEEDLE_MISSING=' . $needle . PHP_EOL);
        exit(1);
    }
}

$cssNeedles = [
    '.site-header',
    '.site-brand',
    '.language-switcher',
    '.breadcrumb',
    '.app-body',
    'min-height:3.55rem',
];

foreach ($cssNeedles as $needle) {
    if (!str_contains($css, $needle)) {
        fwrite(STDERR, 'P113B5_CSS_NEEDLE_MISSING=' . $needle . PHP_EOL);
        exit(1);
    }
}

foreach (['fr', 'en', 'es'] as $lang) {
    $file = $i18nRoot . '/' . $lang . '.json';
    if (!is_file($file)) {
        fwrite(STDERR, 'P113B5_I18N_FILE_MISSING=' . $file . PHP_EOL);
        exit(1);
    }

    $json = json_decode((string) file_get_contents($file), true);
    if (!is_array($json)) {
        fwrite(STDERR, 'P113B5_I18N_JSON_INVALID=' . $file . PHP_EOL);
        exit(1);
    }

    foreach ([['labels', 'topbar', 'subtitle'], ['labels', 'breadcrumb', 'label'], ['labels', 'breadcrumb', 'home']] as $path) {
        $cursor = $json;
        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                fwrite(STDERR, 'P113B5_I18N_KEY_MISSING=' . $lang . ':' . implode('.', $path) . PHP_EOL);
                exit(1);
            }
            $cursor = $cursor[$segment];
        }
        if (!is_string($cursor) || trim($cursor) === '') {
            fwrite(STDERR, 'P113B5_I18N_VALUE_INVALID=' . $lang . ':' . implode('.', $path) . PHP_EOL);
            exit(1);
        }
    }
}

echo 'P113B5_HEADER_LANGUAGE_BREADCRUMB_SMOKE_OK' . PHP_EOL;
