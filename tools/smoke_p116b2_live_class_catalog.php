<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Documentation\RuntimeClassCatalog;

$root = dirname(__DIR__) . '/framework/Opus';
$catalog = new RuntimeClassCatalog($root);
$classes = $catalog->all();

if ($classes === []) {
    fwrite(STDERR, 'P116B2_LIVE_CLASS_CATALOG_EMPTY' . PHP_EOL);
    exit(1);
}

$foundCatalog = false;
foreach ($classes as $class) {
    $data = $class->toArray();
    if (($data['domain'] ?? '') === 'unclassified') {
        fwrite(STDERR, 'P116B2_UNCLASSIFIED_DOMAIN_FOUND=' . ($data['name'] ?? '?') . PHP_EOL);
        exit(1);
    }
    if (($data['domain'] ?? '') === '' || ($data['domain'] ?? '') === 'unknown') {
        fwrite(STDERR, 'P116B2_INVALID_DOMAIN_FOUND=' . ($data['name'] ?? '?') . PHP_EOL);
        exit(1);
    }
    if (!is_file((string) ($data['file'] ?? ''))) {
        fwrite(STDERR, 'P116B2_REFLECTED_FILE_MISSING=' . ($data['name'] ?? '?') . PHP_EOL);
        exit(1);
    }
    if (($data['name'] ?? '') === RuntimeClassCatalog::class) {
        $foundCatalog = true;
    }
}

if (!$foundCatalog) {
    fwrite(STDERR, 'P116B2_RUNTIME_CLASS_CATALOG_NOT_DISCOVERED' . PHP_EOL);
    exit(1);
}

$matches = $catalog->search('RuntimeClassCatalog');
if ($matches === []) {
    fwrite(STDERR, 'P116B2_LIVE_SEARCH_FAILED' . PHP_EOL);
    exit(1);
}

if (stripos(json_encode($classes, JSON_THROW_ON_ERROR), 'unclassified') !== false) {
    fwrite(STDERR, 'P116B2_FORBIDDEN_UNCLASSIFIED_TEXT_FOUND' . PHP_EOL);
    exit(1);
}

echo 'P116B2_LIVE_CLASS_CATALOG_SMOKE_OK' . PHP_EOL;
echo 'live_classes=' . count($classes) . PHP_EOL;
echo 'diagnostics=' . count($catalog->diagnostics()) . PHP_EOL;
