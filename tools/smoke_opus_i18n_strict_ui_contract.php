<?php
declare(strict_types=1);

use Opus\I18n\I18nKey;
use Opus\I18n\TranslationCatalogueValidator;
use Opus\I18n\UiTextContractValidator;

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "OPUS_I18N_STRICT_AUTOLOAD_MISSING\n");
    exit(1);
}
require_once $autoload;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

if (I18nKey::CONTRACT !== 'OPUS_I18N_KEY_V1') {
    $fail('OPUS_I18N_KEY_CONTRACT_INVALID');
}
if (UiTextContractValidator::CONTRACT !== 'OPUS_I18N_STRICT_UI_CONTRACT_V1') {
    $fail('OPUS_I18N_STRICT_UI_CONTRACT_INVALID');
}

$validator = new UiTextContractValidator();
$validator->validate([
    'title_key' => 'state.source.title',
    'actions' => [
        ['label_key' => 'source.actions.preview_diff', 'id' => 'preview'],
    ],
    'application_name' => 'OPUS OWASYS',
    'path' => 'application/states/source/views/index.php',
]);

$rawRejected = false;
try {
    $validator->validate(['title' => 'Source & Git']);
} catch (RuntimeException $exception) {
    $rawRejected = str_starts_with($exception->getMessage(), 'OPUS_I18N_RAW_UI_TEXT_FORBIDDEN:');
}
if (!$rawRejected) {
    $fail('OPUS_I18N_RAW_UI_TEXT_NOT_REJECTED');
}

$invalidKeyRejected = false;
try {
    $validator->validate(['title_key' => 'Source & Git']);
} catch (RuntimeException $exception) {
    $invalidKeyRejected = str_starts_with($exception->getMessage(), 'OPUS_I18N_KEY_INVALID:');
}
if (!$invalidKeyRejected) {
    $fail('OPUS_I18N_INVALID_KEY_NOT_REJECTED');
}

$catalogueValidator = new TranslationCatalogueValidator();
$catalogueValidator->validate([
    'en' => ['state.source.title' => 'Source & Git'],
    'fr' => ['state.source.title' => 'Sources et Git'],
], [new I18nKey('state.source.title')]);

$missingRejected = false;
try {
    $catalogueValidator->validate([
        'en' => ['state.source.title' => 'Source & Git'],
        'fr' => [],
    ], ['state.source.title']);
} catch (RuntimeException $exception) {
    $missingRejected = str_starts_with($exception->getMessage(), 'OPUS_I18N_KEY_MISSING:');
}
if (!$missingRejected) {
    $fail('OPUS_I18N_MISSING_KEY_NOT_REJECTED');
}

echo "OPUS_I18N_STRICT_UI_CONTRACT_SMOKE_OK\n";
