<?php
declare(strict_types=1);

/*
 * P113D3B static smoke.
 *
 * Contract:
 *   Verify that the official Opus RefBook documentation assets added by this
 *   patch exist in the framework repository. This smoke does not execute
 *   examples; they are documentation assets consumed by RefBook.
 */

$root = dirname(__DIR__, 2);

$examples = [
    'acl-overview',
    'acl-condition',
    'acl-error',
    'acl-refbook-domain',
    'fsm-definition',
    'fsm-basic-transition',
    'fsm-action',
    'fsm-error',
    'fsm-refbook-domain',
    'response-overview',
    'response-html',
    'response-send',
    'http-refbook-domain',
    'attribute-routing',
    'secure-dispatch-gate',
    'routing-refbook-domain',
];

$diagrams = [
    'acl-runtime',
    'fsm-runtime',
    'http-runtime',
    'routing-runtime',
    'secure-dispatch-runtime',
];

foreach ($examples as $id) {
    $file = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'refbook' . DIRECTORY_SEPARATOR . 'examples' . DIRECTORY_SEPARATOR . $id . '.php';
    if (!is_file($file)) {
        fwrite(STDERR, 'P113D3B_STATIC_FAIL missing example=' . $id . PHP_EOL);
        exit(1);
    }

    $content = (string) file_get_contents($file);
    if (!str_contains($content, 'Opus RefBook example:')) {
        fwrite(STDERR, 'P113D3B_STATIC_FAIL invalid example=' . $id . PHP_EOL);
        exit(1);
    }
}

foreach ($diagrams as $id) {
    $file = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'refbook' . DIRECTORY_SEPARATOR . 'diagrams' . DIRECTORY_SEPARATOR . $id . '.mmd';
    if (!is_file($file)) {
        fwrite(STDERR, 'P113D3B_STATIC_FAIL missing diagram=' . $id . PHP_EOL);
        exit(1);
    }

    $content = trim((string) file_get_contents($file));
    if (!str_starts_with($content, 'flowchart') && !str_starts_with($content, 'stateDiagram')) {
        fwrite(STDERR, 'P113D3B_STATIC_FAIL invalid diagram=' . $id . PHP_EOL);
        exit(1);
    }
}

echo 'P113D3B_OPUS_REFBOOK_DOC_ASSETS_STATIC_SMOKE_OK' . PHP_EOL;
