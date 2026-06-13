<?php

declare(strict_types=1);

require_once __DIR__ . '/../../framework/Opus/RefBook/I18n/RefBookDocumentationLocale.php';
require_once __DIR__ . '/../../framework/Opus/RefBook/I18n/RefBookDocumentationTranslationMissingException.php';
require_once __DIR__ . '/../../framework/Opus/RefBook/I18n/RefBookDocumentationI18nCatalog.php';
require_once __DIR__ . '/../../framework/Opus/RefBook/Api/LocalizedRefBookDocumentationProvider.php';
require_once __DIR__ . '/../../framework/Opus/RefBook/Api/RefBookDocumentationI18nRestRouter.php';

use ASAP\RefBook\Api\LocalizedRefBookDocumentationProvider;
use ASAP\RefBook\Api\RefBookDocumentationI18nRestRouter;
use ASAP\RefBook\I18n\RefBookDocumentationI18nCatalog;
use ASAP\RefBook\I18n\RefBookDocumentationTranslationMissingException;

$failures = [];

function assert_true(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

$catalog = new RefBookDocumentationI18nCatalog();
$provider = new LocalizedRefBookDocumentationProvider($catalog);

$sample = [
    'symbols' => [
        [
            'id' => 'RefBookDocumentationAssetRepository',
            'name' => 'RefBookDocumentationAssetRepository',
            'domain' => 'RefBook',
            'role' => 'Expose official RefBook examples and diagrams by stable identifier',
            'responsibility' => 'Read documentation assets from DOC/refbook for the RefBook REST API without path traversal or placeholder fallback.',
            'contracts' => [
                'Only DOC/refbook/examples/*.php and DOC/refbook/diagrams/*.mmd are exposed.',
                'Asset identifiers are validated and must not contain path separators.',
                'Duplicate asset identifiers fail explicitly.',
            ],
            'methods' => [
                [
                    'name' => 'index',
                    'role' => 'Return all official RefBook documentation assets',
                    'behavior' => 'Scans the official DOC/refbook examples and diagrams directories and returns a deterministic asset index.',
                    'preconditions' => ['DOC/refbook exists.', 'Asset identifiers are unique.'],
                    'postconditions' => ['Examples and diagrams are returned as machine-readable arrays.'],
                    'side_effects' => ['Reads documentation files from disk.'],
                ],
            ],
        ],
        [
            'id' => 'RequestContext',
            'name' => 'RequestContext',
            'domain' => 'HTTP',
            'responsibility' => 'Represent the request path and HTTP method passed to routing and secure dispatch boundaries.',
        ],
    ],
];

$cs = $provider->localizeSnapshot($sample, 'cs');
assert_true(($cs['language'] ?? null) === 'cs', 'localized snapshot does not expose cs language', $failures);
assert_true(str_contains($cs['symbols'][0]['responsibility'], 'Čte dokumentační assets'), 'cs responsibility was not localized', $failures);
assert_true(str_contains($cs['symbols'][1]['responsibility'], 'cestu požadavku'), 'cs request context responsibility was not localized', $failures);
assert_true(!str_contains($cs['symbols'][0]['responsibility'], 'Read documentation assets'), 'cs still contains English source text', $failures);

$fr = $provider->localizeSnapshot($sample, 'fr');
assert_true(str_contains($fr['symbols'][1]['responsibility'], 'chemin de requête'), 'fr request context responsibility was not localized', $failures);

$router = new RefBookDocumentationI18nRestRouter(static fn (): array => $sample, $provider);
$response = $router->handle('GET', '/api/refbook/cs/snapshot');
assert_true($response['status'] === 200, 'REST cs snapshot did not return 200', $failures);
assert_true(($response['body']['language'] ?? null) === 'cs', 'REST response language is not cs', $failures);
assert_true(str_contains($response['body']['data']['symbols'][1]['responsibility'], 'cestu požadavku'), 'REST cs snapshot was not localized', $failures);

$unsupported = $router->handle('GET', '/api/refbook/pt/snapshot');
assert_true($unsupported['status'] === 500 && str_contains((string) ($unsupported['body']['error'] ?? ''), 'OPUS_REFBOOK_DOC_LANG_UNSUPPORTED'), 'unsupported lang did not fail explicitly', $failures);

$missingSnapshot = ['symbols' => [['id' => 'x', 'responsibility' => 'This source string is intentionally not translated.']]];
try {
    $provider->localizeSnapshot($missingSnapshot, 'cs');
    $failures[] = 'missing translation did not throw';
} catch (RefBookDocumentationTranslationMissingException $exception) {
    assert_true(str_contains($exception->getMessage(), 'OPUS_REFBOOK_DOC_TRANSLATION_MISSING'), 'missing translation error code is wrong', $failures);
}

if ($failures !== []) {
    echo "P114D1_OPUS_REFBOOK_DOC_I18N_REST_LANG_PROVIDER_FAIL\n";
    foreach ($failures as $idx => $failure) {
        echo str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT) . ' ' . $failure . "\n";
    }
    exit(1);
}

echo "P114D1_OPUS_REFBOOK_DOC_I18N_REST_LANG_PROVIDER_OK\n";
