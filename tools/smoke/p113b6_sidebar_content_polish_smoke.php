<?php
declare(strict_types=1);

/**
 * PUBLIC SMOKE TEST
 *
 * Role:
 *   Validate P113B6 RefBook navigation ergonomics and content population.
 *
 * Reads:
 *   layout.twig, refbook.css, I18N JSON files and generated manifest.
 *
 * Writes:
 *   Nothing.
 *
 * Contract:
 *   Static read-only smoke. It does not require Apache, UwAmp, ASAP_ROOT or
 *   a database. Any missing UI contract or untranslated domain fails clearly.
 */
$root = dirname(__DIR__, 2);

$layoutFile = $root . '/application/reference/templates/layout.twig';
$cssFile = $root . '/public/assets/css/refbook.css';
$domainTemplateFile = $root . '/application/reference/templates/pages/domain.twig';
$symbolTemplateFile = $root . '/application/reference/templates/pages/symbol.twig';
$guideTemplateFile = $root . '/application/reference/templates/pages/guide.twig';
$manifestFile = $root . '/var/data/api_reference.generated.json';
$i18nRoot = $root . '/content/refbook/i18n';

foreach ([$layoutFile, $cssFile, $domainTemplateFile, $symbolTemplateFile, $guideTemplateFile, $manifestFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, 'P113B6_FILE_MISSING=' . $file . PHP_EOL);
        exit(1);
    }
}

$layout = (string) file_get_contents($layoutFile);
$css = (string) file_get_contents($cssFile);
$domainTemplate = (string) file_get_contents($domainTemplateFile);
$symbolTemplate = (string) file_get_contents($symbolTemplateFile);
$guideTemplate = (string) file_get_contents($guideTemplateFile);

$layoutNeedles = [
    'refbook.css?v=P113B',
    'class="nav-group"',
    'class="nav-group nav-group-domains"',
    'class="nav-list nav-list-compact"',
    'ui.sidebar.menu',
    'navigationDomains|length',
];
foreach ($layoutNeedles as $needle) {
    if ($needle === 'refbook.css?v=P113B') {
        if (!str_contains($layout, 'refbook.css?v=P113B6') && !str_contains($layout, 'refbook.css?v=P113B7') && !str_contains($layout, 'refbook.css?v=P113B8')) {
            fwrite(STDERR, 'P113B6_LAYOUT_NEEDLE_MISSING=' . $needle . PHP_EOL);
            exit(1);
        }
        continue;
    }

    if (!str_contains($layout, $needle)) {
        fwrite(STDERR, 'P113B6_LAYOUT_NEEDLE_MISSING=' . $needle . PHP_EOL);
        exit(1);
    }
}

$cssNeedles = [
    '.nav-group',
    '.nav-list-compact',
    'max-height:38vh',
    '.definition-grid',
    '.table-wrap',
    'position:sticky',
];
foreach ($cssNeedles as $needle) {
    if (!str_contains($css, $needle)) {
        fwrite(STDERR, 'P113B6_CSS_NEEDLE_MISSING=' . $needle . PHP_EOL);
        exit(1);
    }
}

$templateNeedles = [
    $domainTemplate => ['domain.method_count', 'domain.class_count', 'domain.interface_count', 'table-wrap'],
    $symbolTemplate => ['definition-grid', 'symbol.examples', 'symbol.diagrams', 'ui.symbol.identity_title'],
    $guideTemplate => ['guide.reading', 'ui.guide.reading_title', 'check-list'],
];
foreach ($templateNeedles as $template => $needles) {
    foreach ($needles as $needle) {
        if (!str_contains($template, $needle)) {
            fwrite(STDERR, 'P113B6_TEMPLATE_NEEDLE_MISSING=' . $needle . PHP_EOL);
            exit(1);
        }
    }
}

$manifest = json_decode((string) file_get_contents($manifestFile), true);
if (!is_array($manifest) || ($manifest['schema'] ?? null) !== 'ASAP_REFBOOK_SOURCE_MANIFEST_V1') {
    fwrite(STDERR, 'P113B6_MANIFEST_INVALID=' . $manifestFile . PHP_EOL);
    exit(1);
}

$domains = [];
foreach (($manifest['symbols'] ?? []) as $symbol) {
    if (!is_array($symbol)) {
        continue;
    }
    $domain = trim((string) ($symbol['domain'] ?? 'CORE'));
    $domains[$domain !== '' ? $domain : 'CORE'] = true;
}

foreach (['fr', 'en', 'es'] as $lang) {
    $file = $i18nRoot . '/' . $lang . '.json';
    if (!is_file($file)) {
        fwrite(STDERR, 'P113B6_I18N_FILE_MISSING=' . $file . PHP_EOL);
        exit(1);
    }

    $json = json_decode((string) file_get_contents($file), true);
    if (!is_array($json)) {
        fwrite(STDERR, 'P113B6_I18N_JSON_INVALID=' . $file . PHP_EOL);
        exit(1);
    }

    foreach ([
        ['labels', 'sidebar', 'menu'],
        ['labels', 'guide', 'reading_title'],
        ['labels', 'domain', 'public_methods'],
        ['labels', 'symbol', 'identity_title'],
    ] as $path) {
        $cursor = $json;
        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                fwrite(STDERR, 'P113B6_I18N_KEY_MISSING=' . $lang . ':' . implode('.', $path) . PHP_EOL);
                exit(1);
            }
            $cursor = $cursor[$segment];
        }
    }

    $descriptions = $json['domain_descriptions'] ?? null;
    if (!is_array($descriptions)) {
        fwrite(STDERR, 'P113B6_DOMAIN_DESCRIPTIONS_MISSING=' . $lang . PHP_EOL);
        exit(1);
    }

    foreach (array_keys($domains) as $domain) {
        $value = $descriptions[$domain] ?? '';
        if (!is_string($value) || trim($value) === '' || str_contains($value, '[*domain.')) {
            fwrite(STDERR, 'P113B6_DOMAIN_DESCRIPTION_INVALID=' . $lang . ':' . $domain . PHP_EOL);
            exit(1);
        }
    }
}

echo 'P113B6_SIDEBAR_CONTENT_POLISH_SMOKE_OK' . PHP_EOL;
