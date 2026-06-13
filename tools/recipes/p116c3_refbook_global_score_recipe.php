<?php

declare(strict_types=1);

/**
 * P116C3 RefBook global Score recipe.
 *
 * Contract:
 *   - validates the real OPUS_REF_BOOK application root, not a sandbox;
 *   - validates Composer/vendor stay free of Twig and Symfony;
 *   - validates RefBook templates are native .score only;
 *   - validates layout.score owns the shell: header, sidebar, main content, footer;
 *   - validates page .score templates are real files, non-empty and not placeholder-only;
 *   - rejects PHP renderers that generate the complete application shell in hardcoded HTML.
 */
$root = dirname(__DIR__, 2);
$runtime = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'recipes' . DIRECTORY_SEPARATOR . 'p116c3_refbook_global_score_' . date('Ymd_His');
ensureDirectory($runtime);

$refBookRoot = refBookRoot($root);
$baseUrl = rtrim((string)(getenv('OPUS_RECIPE_REFBOOK_BASE_URL') ?: 'http://127.0.0.1/OPUS_REF_BOOK'), '/');
$checks = [];

check($checks, is_dir($refBookRoot), 'P116C3_REFBOOK_ROOT_OK', 'P116C3_REFBOOK_ROOT_MISSING', $refBookRoot);
assertFile($checks, $refBookRoot, 'composer.json', 'P116C3_REFBOOK_COMPOSER_JSON_OK');
assertFile($checks, $refBookRoot, 'public/index.php', 'P116C3_REFBOOK_PUBLIC_INDEX_OK');
assertFile($checks, $refBookRoot, 'application/reference/templates/layout.score', 'P116C3_REFBOOK_LAYOUT_SCORE_EXISTS');

$composerJson = readText($refBookRoot . DIRECTORY_SEPARATOR . 'composer.json');
checkNoNeedle($checks, strtolower($composerJson), 'twig/twig', 'P116C3_REFBOOK_COMPOSER_NO_TWIG');
checkNoNeedle($checks, strtolower($composerJson), 'symfony/', 'P116C3_REFBOOK_COMPOSER_NO_SYMFONY');
check($checks, !is_dir($refBookRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'twig'), 'P116C3_REFBOOK_VENDOR_NO_TWIG', 'P116C3_REFBOOK_VENDOR_TWIG_PRESENT');
check($checks, !is_dir($refBookRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'symfony'), 'P116C3_REFBOOK_VENDOR_NO_SYMFONY', 'P116C3_REFBOOK_VENDOR_SYMFONY_PRESENT');

$twigFiles = filesByExtension($refBookRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'reference' . DIRECTORY_SEPARATOR . 'templates', 'twig');
check($checks, $twigFiles === [], 'P116C3_REFBOOK_NO_TWIG_TEMPLATES', 'P116C3_REFBOOK_TWIG_TEMPLATES_PRESENT', implode(', ', $twigFiles));

$scoreTemplates = requiredScoreTemplates();
foreach ($scoreTemplates as $relative) {
    $path = $refBookRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    check($checks, is_file($path), 'P116C3_REFBOOK_SCORE_TEMPLATE_EXISTS=' . $relative, 'P116C3_REFBOOK_SCORE_TEMPLATE_MISSING', $relative);
    if (is_file($path)) {
        $source = trim(readText($path));
        check($checks, $source !== '', 'P116C3_REFBOOK_SCORE_TEMPLATE_NOT_EMPTY=' . $relative, 'P116C3_REFBOOK_SCORE_TEMPLATE_EMPTY', $relative);
        check($checks, !preg_match('/^\s*(?:<!--.*?-->|\/\*.*?\*\/|#.*)?\s*$/s', $source), 'P116C3_REFBOOK_SCORE_TEMPLATE_NOT_PLACEHOLDER=' . $relative, 'P116C3_REFBOOK_SCORE_TEMPLATE_PLACEHOLDER_ONLY', $relative);
    }
}

$layoutPath = $refBookRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'reference' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'layout.score';
$layout = is_file($layoutPath) ? readText($layoutPath) : '';
foreach ([
    'header' => ['<header', 'role="banner"', "role='banner'"],
    'sidebar' => ['<aside', 'role="navigation"', "role='navigation'", 'refbook-sidebar'],
    'main' => ['<main', 'role="main"', "role='main'", '{{{ content', '[[ include:pages/'],
    'footer' => ['<footer', 'role="contentinfo"', "role='contentinfo'"],
] as $slot => $needles) {
    checkAnyNeedle($checks, $layout, $needles, 'P116C3_REFBOOK_LAYOUT_SLOT_' . strtoupper($slot) . '_OK', 'P116C3_REFBOOK_LAYOUT_SLOT_' . strtoupper($slot) . '_MISSING');
}

$hardcodedRenderer = $refBookRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'reference' . DIRECTORY_SEPARATOR . 'Service' . DIRECTORY_SEPARATOR . 'ReferenceScorePageRenderer.php';
if (is_file($hardcodedRenderer)) {
    $rendererSource = readText($hardcodedRenderer);
    $hardShell = str_contains($rendererSource, '<!doctype') || (str_contains($rendererSource, '<header') && str_contains($rendererSource, '<aside') && str_contains($rendererSource, '<main'));
    check($checks, !$hardShell, 'P116C3_REFBOOK_NO_HARDCODED_PAGE_SHELL', 'P116C3_REFBOOK_HARDCODED_PAGE_SHELL_PRESENT', 'ReferenceScorePageRenderer.php');
} else {
    $checks[] = ok('P116C3_REFBOOK_NO_HARDCODED_PAGE_SHELL');
}

$home = http($baseUrl . '/?lang=fr&theme=night');
if ($home['status'] === 0) {
    $checks[] = skip('P116C3_REFBOOK_HTTP_SKIPPED', 'UwAmp/refbook HTTP unavailable at ' . $baseUrl);
} else {
    check($checks, $home['status'] === 200, 'P116C3_REFBOOK_HTTP_HOME_OK', 'P116C3_REFBOOK_HTTP_HOME_FAILED', (string)$home['status']);
    checkNoNeedle($checks, $home['body'], 'OPUS_REFBOOK_RUNTIME_ERROR', 'P116C3_REFBOOK_HTTP_NO_RUNTIME_ERROR');
    checkNoNeedle($checks, $home['body'], 'Fatal error', 'P116C3_REFBOOK_HTTP_NO_FATAL_ERROR');
    checkAnyNeedle($checks, $home['body'], ['<header', 'role="banner"', "role='banner'"], 'P116C3_REFBOOK_HTTP_HEADER_VISIBLE', 'P116C3_REFBOOK_HTTP_HEADER_MISSING');
    checkAnyNeedle($checks, $home['body'], ['<aside', 'refbook-sidebar', 'role="navigation"', "role='navigation'"], 'P116C3_REFBOOK_HTTP_SIDEBAR_VISIBLE', 'P116C3_REFBOOK_HTTP_SIDEBAR_MISSING');
    checkAnyNeedle($checks, $home['body'], ['<main', 'role="main"', "role='main'"], 'P116C3_REFBOOK_HTTP_MAIN_VISIBLE', 'P116C3_REFBOOK_HTTP_MAIN_MISSING');
    checkAnyNeedle($checks, $home['body'], ['<footer', 'role="contentinfo"', "role='contentinfo'"], 'P116C3_REFBOOK_HTTP_FOOTER_VISIBLE', 'P116C3_REFBOOK_HTTP_FOOTER_MISSING');
}

$failed = array_values(array_filter($checks, static fn(array $check): bool => $check['status'] === 'FAILED'));
$report = [
    'recipe' => 'P116C3_REFBOOK_GLOBAL_SCORE_RECIPE',
    'status' => $failed === [] ? 'OK' : 'FAILED',
    'refbook_root' => $refBookRoot,
    'base_url' => $baseUrl,
    'checks' => $checks,
];
writeJson($runtime . DIRECTORY_SEPARATOR . 'report.json', $report);
writeText($runtime . DIRECTORY_SEPARATOR . 'report.md', markdown($report));

echo 'P116C3_REFBOOK_GLOBAL_SCORE_RECIPE_REPORT=' . $runtime . DIRECTORY_SEPARATOR . 'report.json' . PHP_EOL;
foreach ($checks as $check) {
    echo '[' . $check['status'] . '] ' . $check['code'] . ($check['detail'] !== '' ? ' :: ' . $check['detail'] : '') . PHP_EOL;
}
if ($failed !== []) {
    fwrite(STDERR, 'P116C3_REFBOOK_GLOBAL_SCORE_RECIPE_FAILED' . PHP_EOL);
    exit(1);
}
echo 'P116C3_REFBOOK_GLOBAL_SCORE_RECIPE_OK' . PHP_EOL;
exit(0);

function refBookRoot(string $root): string { $configured = trim((string)(getenv('OPUS_RECIPE_REFBOOK_ROOT') ?: '')); return $configured !== '' ? rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configured), DIRECTORY_SEPARATOR) : dirname($root) . DIRECTORY_SEPARATOR . 'OPUS_REF_BOOK'; }
function requiredScoreTemplates(): array { return ['application/reference/templates/layout.score', 'application/reference/templates/pages/home.score', 'application/reference/templates/pages/search.score', 'application/reference/templates/pages/symbol.score', 'application/reference/templates/pages/domain.score', 'application/reference/templates/pages/api-reference.score', 'application/reference/templates/pages/guide.score', 'application/reference/templates/pages/not-found.score']; }
function ok(string $code, string $detail = ''): array { return ['status' => 'OK', 'code' => $code, 'detail' => $detail]; }
function failCheck(string $code, string $detail = ''): array { return ['status' => 'FAILED', 'code' => $code, 'detail' => $detail]; }
function skip(string $code, string $detail = ''): array { return ['status' => 'SKIPPED', 'code' => $code, 'detail' => $detail]; }
function check(array &$checks, bool $condition, string $ok, string $fail, string $detail = ''): void { $checks[] = $condition ? ok($ok, $detail) : failCheck($fail, $detail); }
function checkNoNeedle(array &$checks, string $haystack, string $needle, string $ok): void { $checks[] = !str_contains($haystack, $needle) ? ok($ok) : failCheck($ok . '_FAILED', 'forbidden=' . $needle); }
function checkAnyNeedle(array &$checks, string $haystack, array $needles, string $ok, string $fail): void { foreach ($needles as $needle) { if (str_contains($haystack, $needle)) { $checks[] = ok($ok); return; } } $checks[] = failCheck($fail, 'expected any of: ' . implode(', ', $needles)); }
function assertFile(array &$checks, string $root, string $relative, string $ok): void { $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative); check($checks, is_file($path), $ok, $ok . '_MISSING', $relative); }
function filesByExtension(string $root, string $extension): array { if (!is_dir($root)) { return []; } $out = []; $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)); foreach ($it as $item) { if ($item instanceof SplFileInfo && $item->isFile() && strtolower($item->getExtension()) === strtolower($extension)) { $out[] = str_replace('\\', '/', $item->getPathname()); } } sort($out); return $out; }
function readText(string $path): string { $text = @file_get_contents($path); return is_string($text) ? $text : ''; }
function ensureDirectory(string $path): void { if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) { fwrite(STDERR, 'P116C3_DIRECTORY_CREATE_FAILED=' . $path . PHP_EOL); exit(1); } }
function writeText(string $path, string $content): void { if (file_put_contents($path, $content) === false) { fwrite(STDERR, 'P116C3_WRITE_FAILED=' . $path . PHP_EOL); exit(1); } }
function writeJson(string $path, array $payload): void { $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); writeText($path, (is_string($json) ? $json : '{}') . PHP_EOL); }
function http(string $url): array { $context = stream_context_create(['http' => ['method' => 'GET', 'ignore_errors' => true, 'timeout' => 5.0]]); $body = @file_get_contents($url, false, $context); $headers = $http_response_header ?? []; $status = 0; foreach ($headers as $header) { if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $m) === 1) { $status = (int)$m[1]; break; } } return ['status' => $status, 'body' => is_string($body) ? $body : '']; }
function markdown(array $report): string { $lines = ['# ' . $report['recipe'], '', 'Status: **' . $report['status'] . '**', '', 'RefBook: `' . $report['refbook_root'] . '`', 'Base URL: `' . $report['base_url'] . '`', '', '## Checks']; foreach ($report['checks'] as $check) { $lines[] = '- ' . $check['status'] . ' — ' . $check['code'] . ($check['detail'] !== '' ? ' — ' . $check['detail'] : ''); } return implode(PHP_EOL, $lines) . PHP_EOL; }
