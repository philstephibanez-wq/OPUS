<?php
declare(strict_types=1);

/*
 * P113D3B live smoke.
 *
 * Contract:
 *   Ask OPUS_REF_BOOK live API to recompute asset integrity after the ASAP
 *   documentation assets are installed.
 */

$url = 'http://127.0.0.1/OPUS_REF_BOOK/api/refbook/asset-integrity';
$json = @file_get_contents($url);

if ($json === false) {
    fwrite(STDERR, 'P113D3B_LIVE_FAIL api_unreachable=' . $url . PHP_EOL);
    exit(1);
}

$data = json_decode($json, true);
if (!is_array($data)) {
    fwrite(STDERR, 'P113D3B_LIVE_FAIL json_invalid' . PHP_EOL);
    exit(1);
}

$integrity = $data['asset_integrity'] ?? null;
if (!is_array($integrity)) {
    fwrite(STDERR, 'P113D3B_LIVE_FAIL integrity_missing' . PHP_EOL);
    exit(1);
}

if (($integrity['unique_missing_count'] ?? null) !== 0) {
    fwrite(STDERR, 'P113D3B_LIVE_FAIL unique_missing_count=' . (string) ($integrity['unique_missing_count'] ?? 'missing') . PHP_EOL);
    exit(1);
}

if (($integrity['missing_count'] ?? null) !== 0) {
    fwrite(STDERR, 'P113D3B_LIVE_FAIL missing_count=' . (string) ($integrity['missing_count'] ?? 'missing') . PHP_EOL);
    exit(1);
}

echo 'P113D3B_OPUS_REFBOOK_DOC_ASSETS_LIVE_SMOKE_OK' . PHP_EOL;
