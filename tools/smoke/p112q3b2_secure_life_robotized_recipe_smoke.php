<?php

declare(strict_types=1);

/**
 * PUBLIC SMOKE SCRIPT
 *
 * Role:
 *   Validate the P112Q3B2 robotized evolutive recipe files without requiring a
 *   real mail transport, Panther, Apache or UwAmp.
 *
 * Responsibility:
 *   Run the recipe in non-mail-required EML mode, prevent browser opening, and
 *   verify that JSON/Markdown/HTML reports are generated with the three user
 *   identities and FR/ES/EN language markers.
 *
 * Contract:
 *   No silent success. Missing files, missing markers or a failed recipe process
 *   abort with explicit P112Q3B2_* messages.
 */

$root = dirname(__DIR__, 2);
$recipe = $root . '/tools/recipes/p112q3b2_secure_life_robotized_recipe.php';
$reportDir = $root . '/var/reports/p112q3b2';
$jsonReport = $reportDir . '/p112q3b2_secure_life_robotized_recipe.json';
$htmlReport = $reportDir . '/p112q3b2_secure_life_robotized_recipe.html';
$markdownReport = $reportDir . '/p112q3b2_secure_life_robotized_recipe.md';

function p112q3b2_smoke_fail(string $code, string $detail = ''): never
{
    $message = $detail === '' ? $code : $code . ': ' . $detail;
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function p112q3b2_smoke_assert(bool $condition, string $code, string $detail = ''): void
{
    if (!$condition) {
        p112q3b2_smoke_fail($code, $detail);
    }
}

p112q3b2_smoke_assert(is_file($recipe), 'P112Q3B2_RECIPE_FILE_MISSING', $recipe);

$command = escapeshellarg(PHP_BINARY)
    . ' ' . escapeshellarg($recipe);

$env = [
    'OPUS_P112Q3B2_MAIL_MODE=eml',
    'OPUS_P112Q3B2_MAIL_REQUIRED=0',
    'OPUS_P112Q3B2_REPORT_EMAIL_TO=smoke@example.invalid',
    'OPUS_P112Q3B2_PANTHER_REQUIRED=0',
];

if (PHP_OS_FAMILY === 'Windows') {
    $command = implode(' && ', array_map(static fn (string $item): string => 'set ' . $item, $env)) . ' && ' . $command;
} else {
    $command = implode(' ', array_map('escapeshellarg', $env)) . ' ' . $command;
}

$output = [];
$exitCode = 0;
exec($command . ' 2>&1', $output, $exitCode);

p112q3b2_smoke_assert($exitCode === 0, 'P112Q3B2_RECIPE_PROCESS_FAILED', implode("\n", $output));
p112q3b2_smoke_assert(is_file($jsonReport), 'P112Q3B2_JSON_REPORT_MISSING', $jsonReport);
p112q3b2_smoke_assert(is_file($htmlReport), 'P112Q3B2_HTML_REPORT_MISSING', $htmlReport);
p112q3b2_smoke_assert(is_file($markdownReport), 'P112Q3B2_MD_REPORT_MISSING', $markdownReport);

$json = file_get_contents($jsonReport);
$html = file_get_contents($htmlReport);
$md = file_get_contents($markdownReport);

p112q3b2_smoke_assert(is_string($json) && str_contains($json, 'P112Q3B2_OPUS_SECURE_LIFE_ROBOTIZED_RECIPE'), 'P112Q3B2_JSON_ID_MISSING');
p112q3b2_smoke_assert(is_string($html) && str_contains($html, '3 utilisateurs'), 'P112Q3B2_HTML_TITLE_MARKER_MISSING');
p112q3b2_smoke_assert(is_string($html) && str_contains($html, 'Invité'), 'P112Q3B2_HTML_GUEST_MISSING');
p112q3b2_smoke_assert(is_string($html) && str_contains($html, 'Éditeur'), 'P112Q3B2_HTML_EDITOR_MISSING');
p112q3b2_smoke_assert(is_string($html) && str_contains($html, 'Administrateur'), 'P112Q3B2_HTML_ADMIN_MISSING');
p112q3b2_smoke_assert(is_string($html) && str_contains($html, 'FR'), 'P112Q3B2_HTML_FR_MISSING');
p112q3b2_smoke_assert(is_string($html) && str_contains($html, 'ES'), 'P112Q3B2_HTML_ES_MISSING');
p112q3b2_smoke_assert(is_string($html) && str_contains($html, 'EN'), 'P112Q3B2_HTML_EN_MISSING');
p112q3b2_smoke_assert(is_string($md) && str_contains($md, 'guest'), 'P112Q3B2_MD_GUEST_MISSING');
p112q3b2_smoke_assert(is_string($html) && !str_contains($html, 'Mail: PENDING'), 'P112Q3B2_HTML_MAIL_PENDING_STILL_VISIBLE');
p112q3b2_smoke_assert(is_string($md) && !str_contains($md, 'Mail status: `PENDING`'), 'P112Q3B2_MD_MAIL_PENDING_STILL_VISIBLE');
p112q3b2_smoke_assert(is_string($html) && str_contains($html, 'Mail: EML_WRITTEN'), 'P112Q3B2_HTML_MAIL_FINAL_STATUS_MISSING');

echo 'P112Q3B2_SECURE_LIFE_ROBOTIZED_RECIPE_SMOKE_OK' . PHP_EOL;
