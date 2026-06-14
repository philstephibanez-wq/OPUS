<?php

declare(strict_types=1);

/**
 * P116B4 ScoreTemplate unit recipe.
 *
 * Contract:
 *   - validates the native ScoreTemplate engine without HTTP, browser or RefBook;
 *   - validates escaped/raw interpolation, include, if/else, foreach, loop metadata
 *     and whitelisted filters;
 *   - validates explicit failures for missing data, missing include, bad extension
 *     and unknown filter;
 *   - validates Twig/Symfony remain absent from Composer dependencies.
 */
$root = dirname(__DIR__, 2);
$runtime = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'recipes' . DIRECTORY_SEPARATOR . 'p116b4_score_template_unit_' . date('Ymd_His');
$templates = $runtime . DIRECTORY_SEPARATOR . 'templates';
ensureDirectory($templates . DIRECTORY_SEPARATOR . 'partials');

require_once $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use Opus\Template\ScoreTemplateRenderer;

writeText($templates . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'item.score', '<span data-i="{{ loop.index }}" data-first="{{ loop.first }}" data-last="{{ loop.last }}">{{ key }}={{ item.label|upper }}:{{ item.optional|default:"fallback" }}</span>');
writeText($templates . DIRECTORY_SEPARATOR . 'main.score', implode("\n", [
    'Hello {{ user.name }} {{{ raw.html }}}',
    'Role={{ user.role|upper|trim }}',
    'Date={{ today|date:"Y-m-d" }} Count={{ items|length }}',
    '[[ if: user.active ]]ACTIVE[[ else ]]INACTIVE[[ endif ]]',
    '[[ if: missing is not defined ]]MISSING_OK[[ endif ]]',
    '[[ foreach: items as key, item ]][[ include:partials/item.score ]][[ endforeach ]]',
]));

$renderer = new ScoreTemplateRenderer($templates);
$html = normalize($renderer->render('main.score', [
    'user' => ['name' => '<Steve>', 'role' => ' admin ', 'active' => true],
    'raw' => ['html' => '<strong>raw</strong>'],
    'today' => '2026-06-14',
    'items' => [
        'first' => ['label' => 'cello', 'optional' => 'warm'],
        'second' => ['label' => 'violin', 'optional' => ''],
    ],
]));

assertContains($html, 'Hello &lt;Steve&gt; <strong>raw</strong>', 'P116B4_SCORE_ESCAPED_RAW_FAILED');
assertContains($html, 'Role=ADMIN', 'P116B4_SCORE_FILTER_CHAIN_FAILED');
assertContains($html, 'Date=2026-06-14 Count=2', 'P116B4_SCORE_DATE_LENGTH_FAILED');
assertContains($html, 'ACTIVE', 'P116B4_SCORE_IF_TRUE_FAILED');
assertContains($html, 'MISSING_OK', 'P116B4_SCORE_DEFINED_EXPRESSION_FAILED');
assertContains($html, '<span data-i="1" data-first="1" data-last="">first=CELLO:warm</span>', 'P116B4_SCORE_FOREACH_FIRST_FAILED');
assertContains($html, '<span data-i="2" data-first="" data-last="1">second=VIOLIN:fallback</span>', 'P116B4_SCORE_FOREACH_LAST_FAILED');

writeText($templates . DIRECTORY_SEPARATOR . 'missing-data.score', '{{ missing.value }}');
assertThrowsContains(static fn() => $renderer->render('missing-data.score', []), 'OPUS_SCORE_TEMPLATE_DATA_MISSING', 'P116B4_SCORE_MISSING_DATA_NOT_EXPLICIT');
assertThrowsContains(static fn() => $renderer->render('missing-include.score', []), 'OPUS_SCORE_TEMPLATE_NOT_FOUND', 'P116B4_SCORE_MISSING_INCLUDE_NOT_EXPLICIT');
assertThrowsContains(static fn() => $renderer->render('main.twig', []), 'OPUS_SCORE_TEMPLATE_EXTENSION_INVALID', 'P116B4_SCORE_BAD_EXTENSION_NOT_EXPLICIT');
writeText($templates . DIRECTORY_SEPARATOR . 'bad-filter.score', '{{ user.name|unknown }}');
assertThrowsContains(static fn() => $renderer->render('bad-filter.score', ['user' => ['name' => 'Ada']]), 'OPUS_SCORE_TEMPLATE_UNKNOWN_FILTER', 'P116B4_SCORE_UNKNOWN_FILTER_NOT_EXPLICIT');

$composerJson = readText($root . DIRECTORY_SEPARATOR . 'composer.json');
assertNotContains(strtolower($composerJson), 'twig/twig', 'P116B4_COMPOSER_TWIG_FORBIDDEN');
assertNotContains(strtolower($composerJson), 'symfony/', 'P116B4_COMPOSER_SYMFONY_FORBIDDEN');

$forbiddenVendorDirs = [
    $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'twig',
    $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'symfony',
];
foreach ($forbiddenVendorDirs as $dir) {
    if (is_dir($dir)) {
        fail('P116B4_VENDOR_FORBIDDEN_DEPENDENCY_PRESENT', $dir);
    }
}

writeJson($runtime . DIRECTORY_SEPARATOR . 'report.json', [
    'status' => 'OK',
    'recipe' => 'P116B4_SCORE_TEMPLATE_UNIT_RECIPE',
    'runtime' => $runtime,
    'markers' => [
        'P116B4_SCORE_TEMPLATE_UNIT_RECIPE_OK',
        'P116B4_SCORE_TEMPLATE_DIRECTIVES_OK',
        'P116B4_SCORE_TEMPLATE_EXPLICIT_ERRORS_OK',
        'P116B4_SCORE_TEMPLATE_NO_TWIG_SYMFONY_OK',
    ],
]);

echo 'P116B4_SCORE_TEMPLATE_UNIT_RECIPE_OK' . PHP_EOL;
echo 'P116B4_REPORT=' . $runtime . DIRECTORY_SEPARATOR . 'report.json' . PHP_EOL;
exit(0);

function normalize(string $text): string { return str_replace(["\r\n", "\r"], "\n", $text); }
function ensureDirectory(string $path): void { if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) { fail('P116B4_DIRECTORY_CREATE_FAILED', $path); } }
function writeText(string $path, string $content): void { if (file_put_contents($path, $content) === false) { fail('P116B4_WRITE_FAILED', $path); } }
function readText(string $path): string { $text = file_get_contents($path); if (!is_string($text)) { fail('P116B4_READ_FAILED', $path); } return $text; }
function writeJson(string $path, array $payload): void { $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); if (!is_string($json)) { fail('P116B4_JSON_ENCODE_FAILED', $path); } writeText($path, $json . PHP_EOL); }
function assertContains(string $haystack, string $needle, string $code): void { if (!str_contains($haystack, $needle)) { fail($code, 'missing=' . $needle . ' output=' . $haystack); } }
function assertNotContains(string $haystack, string $needle, string $code): void { if (str_contains($haystack, $needle)) { fail($code, 'forbidden=' . $needle); } }
function assertThrowsContains(callable $callback, string $expected, string $code): void { try { $callback(); } catch (Throwable $exception) { if (str_contains($exception->getMessage(), $expected)) { return; } fail($code, $exception->getMessage()); } fail($code, 'NO_EXCEPTION'); }
function fail(string $code, string $detail = ''): never { fwrite(STDERR, $code . ($detail !== '' ? '=' . $detail : '') . PHP_EOL); exit(1); }
