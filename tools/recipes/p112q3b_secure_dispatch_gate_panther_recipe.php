<?php

declare(strict_types=1);

/**
 * PUBLIC ROBOTIZED RECIPE
 *
 * Role:
 *   Run the P112Q3B secure dispatch gate browser-life recipe through Panther when
 *   Panther is explicitly available in the local ASAP installation.
 *
 * Responsibility:
 *   Open a declared local HTTP URL, verify that the page renders, optionally verify
 *   an expected text marker, capture a screenshot and write JSON/Markdown reports.
 *
 * Reads:
 *   - vendor/autoload.php when present
 *   - optional ASAP_P112Q3B_PANTHER_AUTOLOAD when Panther lives outside H:\ASAP
 *   - environment variables ASAP_P112Q3B_PANTHER_URL,
 *     ASAP_P112Q3B_EXPECT_TEXT and ASAP_P112Q3B_PANTHER_REQUIRED
 *
 * Writes:
 *   - var/reports/p112q3b/p112q3b_secure_dispatch_gate_panther_recipe.json
 *   - var/reports/p112q3b/p112q3b_secure_dispatch_gate_panther_recipe.md
 *   - optional screenshot PNG when Panther runs
 *
 * Contract:
 *   No fake browser success. Missing Panther or missing URL is reported as an
 *   explicit SKIPPED status by default, or as FAILED when
 *   ASAP_P112Q3B_PANTHER_REQUIRED=1. No dependency is installed automatically.
 */

$root = dirname(__DIR__, 2);
$defaultVendorAutoload = $root . '/vendor/autoload.php';
$explicitVendorAutoload = trim((string) getenv('ASAP_P112Q3B_PANTHER_AUTOLOAD'));
$vendorAutoload = $explicitVendorAutoload !== '' ? $explicitVendorAutoload : $defaultVendorAutoload;
$reportDir = $root . '/var/reports/p112q3b';
$jsonReport = $reportDir . '/p112q3b_secure_dispatch_gate_panther_recipe.json';
$markdownReport = $reportDir . '/p112q3b_secure_dispatch_gate_panther_recipe.md';
$screenshot = $reportDir . '/p112q3b_secure_dispatch_gate_panther_recipe.png';
$required = getenv('ASAP_P112Q3B_PANTHER_REQUIRED') === '1';
$url = trim((string) getenv('ASAP_P112Q3B_PANTHER_URL'));
$expectedText = trim((string) getenv('ASAP_P112Q3B_EXPECT_TEXT'));
$autoloadLoaded = false;
$autoloadSource = $explicitVendorAutoload !== '' ? 'explicit-env' : 'default-root-vendor';

if (!is_dir($reportDir) && !mkdir($reportDir, 0777, true) && !is_dir($reportDir)) {
    fwrite(STDERR, 'P112Q3B_REPORT_DIR_CREATE_FAILED: ' . $reportDir . PHP_EOL);
    exit(1);
}

if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
    $autoloadLoaded = true;
}

/**
 * Finish the Panther recipe with an explicit status and reports.
 *
 * @param string $status One of OK, SKIPPED or FAILED.
 * @param string $reason Stable result reason.
 * @param array<string,mixed> $data Additional report values.
 * @param bool $failed Whether the process must exit with code 1.
 */
function p112q3b_recipe_finish(string $status, string $reason, array $data = [], bool $failed = false): void
{
    global $jsonReport, $markdownReport, $vendorAutoload, $autoloadLoaded, $autoloadSource;

    $payload = array_merge([
        'id' => 'P112Q3B_ASAP_SECURE_DISPATCH_GATE_PANTHER_RECIPE',
        'status' => $status,
        'reason' => $reason,
        'generated_at' => gmdate('c'),
        'autoload_path' => $vendorAutoload,
        'autoload_source' => $autoloadSource,
        'autoload_loaded' => $autoloadLoaded,
        'panther_client_class' => 'Symfony\\Component\\Panther\\Client',
        'panther_client_available' => class_exists('Symfony\\Component\\Panther\\Client'),
    ], $data);

    file_put_contents($jsonReport, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    $markdown = '# P112Q3B Secure Dispatch Gate Panther Recipe' . PHP_EOL . PHP_EOL;
    $markdown .= '- Status: `' . $status . '`' . PHP_EOL;
    $markdown .= '- Reason: `' . $reason . '`' . PHP_EOL;

    foreach ($payload as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $markdown .= '- ' . $key . ': `' . (string) $value . '`' . PHP_EOL;
        }
    }

    file_put_contents($markdownReport, $markdown);

    echo 'P112Q3B_PANTHER_RECIPE_' . $status . ': ' . $reason . PHP_EOL;
    echo 'Report JSON: ' . $jsonReport . PHP_EOL;
    echo 'Report MD: ' . $markdownReport . PHP_EOL;
    exit($failed ? 1 : 0);
}

if ($url === '') {
    p112q3b_recipe_finish(
        $required ? 'FAILED' : 'SKIPPED',
        'ASAP_P112Q3B_PANTHER_URL_MISSING',
        ['required' => $required],
        $required
    );
}

if (!$autoloadLoaded) {
    p112q3b_recipe_finish(
        $required ? 'FAILED' : 'SKIPPED',
        'PANTHER_AUTOLOAD_NOT_FOUND',
        [
            'url' => $url,
            'required' => $required,
            'expected_autoload' => $vendorAutoload,
            'hint' => 'Set ASAP_P112Q3B_PANTHER_AUTOLOAD to the Composer autoload.php that contains symfony/panther.',
        ],
        $required
    );
}

if (!class_exists('Symfony\\Component\\Panther\\Client')) {
    p112q3b_recipe_finish(
        $required ? 'FAILED' : 'SKIPPED',
        'PANTHER_CLIENT_NOT_AVAILABLE',
        [
            'url' => $url,
            'required' => $required,
            'autoload_path' => $vendorAutoload,
            'hint' => 'Composer autoload loaded, but Symfony\\Component\\Panther\\Client is not registered.',
        ],
        $required
    );
}

try {
    /** @var class-string $clientClass */
    $clientClass = 'Symfony\\Component\\Panther\\Client';
    $client = $clientClass::createChromeClient();
    $crawler = $client->request('GET', $url);
    $pageText = '';

    if (method_exists($crawler, 'filter')) {
        $body = $crawler->filter('body');

        if ($body->count() > 0) {
            $pageText = trim($body->text());
        }
    }

    if ($pageText === '') {
        p112q3b_recipe_finish('FAILED', 'PANTHER_PAGE_BODY_EMPTY', ['url' => $url], true);
    }

    if ($expectedText !== '' && !str_contains($pageText, $expectedText)) {
        p112q3b_recipe_finish(
            'FAILED',
            'PANTHER_EXPECTED_TEXT_NOT_FOUND',
            ['url' => $url, 'expected_text' => $expectedText],
            true
        );
    }

    if (method_exists($client, 'takeScreenshot')) {
        $client->takeScreenshot($screenshot);
    }

    p112q3b_recipe_finish('OK', 'PANTHER_PAGE_RENDERED', [
        'url' => $url,
        'expected_text' => $expectedText === '' ? null : $expectedText,
        'screenshot' => is_file($screenshot) ? $screenshot : null,
    ]);
} catch (Throwable $throwable) {
    p112q3b_recipe_finish('FAILED', 'PANTHER_RUNTIME_ERROR', [
        'url' => $url,
        'error' => $throwable->getMessage(),
    ], true);
}
