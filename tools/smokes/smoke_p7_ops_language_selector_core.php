<?php
declare(strict_types=1);

echo 'P7_OPS_LANGUAGE_SELECTOR_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$publicDir = $root . '/sites/opus-p7-ops/public';

$files = [
    'language' => $publicDir . '/language.php',
    'index' => $publicDir . '/index.php',
    'action' => $publicDir . '/action.php',
    'command' => $publicDir . '/command.php',
    'navigation' => $publicDir . '/navigation.php',
    'diagnostics' => $publicDir . '/diagnostics.php',
    'health' => $publicDir . '/health.php',
    'css' => $publicDir . '/ops-ui.css',
    'readme' => $root . '/sites/opus-p7-ops/README.md',
];

$combined = '';

foreach ($files as $label => $file) {
    if (!is_file($file)) {
        throw new RuntimeException('LANGUAGE_SELECTOR_FILE_MISSING: ' . $label);
    }

    $source = file_get_contents($file);
    if ($source === false) {
        throw new RuntimeException('LANGUAGE_SELECTOR_READ_FAILED: ' . $label);
    }

    $combined .= $source . PHP_EOL;
}

foreach ([
    'P7_OPS_LANGUAGE_SELECTOR_CORE',
    'p7ops_language_selector',
    'p7ops_language_url',
    'p7ops_i18n_catalog',
    'ops-language-selector',
    'data-lang-active',
    'lang=fr',
    'lang=en',
    'Langue',
    'Language',
    'FR',
    'EN',
    'Dashboard',
    'Operations',
    'Command Center',
    'Navigation',
    'Diagnostics',
    'Health Hub',
] as $marker) {
    if (!str_contains($combined, $marker)) {
        throw new RuntimeException('LANGUAGE_SELECTOR_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_LANGUAGE_SELECTOR_MARKERS=OK' . PHP_EOL;

foreach (['index', 'action', 'command', 'navigation', 'diagnostics', 'health'] as $label) {
    $source = file_get_contents($files[$label]);
    if ($source === false) {
        throw new RuntimeException('LANGUAGE_SELECTOR_PAGE_READ_FAILED: ' . $label);
    }

    if (!str_contains($source, "require_once __DIR__ . '/language.php';")) {
        throw new RuntimeException('LANGUAGE_SELECTOR_REQUIRE_MISSING: ' . $label);
    }

    if (!str_contains($source, 'p7ops_language_selector(')) {
        throw new RuntimeException('LANGUAGE_SELECTOR_RENDER_CALL_MISSING: ' . $label);
    }
}

echo 'CHECK_P7_OPS_LANGUAGE_SELECTOR_GLOBAL_PAGES=OK' . PHP_EOL;

require_once $files['language'];

$_GET = ['site' => 'site-alpha', 'lang' => 'en'];
if (p7ops_language() !== 'en') {
    throw new RuntimeException('LANGUAGE_SELECTOR_EN_INVALID');
}

$_GET = ['site' => 'site-alpha', 'lang' => 'bad'];
if (p7ops_language() !== 'fr') {
    throw new RuntimeException('LANGUAGE_SELECTOR_FALLBACK_INVALID');
}

$_GET = ['site' => 'site-alpha', 'lang' => 'fr'];
$selector = p7ops_language_selector('/opus-lstsar-manager/operations?site=site-alpha&lang=fr');

foreach ([
    'ops-language-selector',
    'data-contract="P7_OPS_LANGUAGE_SELECTOR_CORE"',
    'data-lang-active="fr"',
    'site=site-alpha',
    'lang=fr',
    'lang=en',
    'FR',
    'EN',
    'Langue',
] as $marker) {
    if (!str_contains($selector, $marker)) {
        throw new RuntimeException('LANGUAGE_SELECTOR_RENDER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_LANGUAGE_SELECTOR_RENDER=OK' . PHP_EOL;

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

    return is_string($out) ? $out : '';
};

$dashboard = $render($files['index'], '/opus-lstsar-manager?site=site-alpha&lang=en', ['site' => 'site-alpha', 'lang' => 'en']);

foreach ([
    'ops-language-selector',
    'data-lang-active="en"',
    'site=site-alpha',
    'lang=fr',
    'lang=en',
    'Language',
    'EN',
] as $marker) {
    if (!str_contains($dashboard, $marker)) {
        throw new RuntimeException('LANGUAGE_SELECTOR_DASHBOARD_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_LANGUAGE_SELECTOR_DASHBOARD=OK' . PHP_EOL;
echo 'P7_OPS_LANGUAGE_SELECTOR_CORE_SMOKE_OK' . PHP_EOL;
