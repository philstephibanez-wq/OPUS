<?php
declare(strict_types=1);

echo 'OPUS_SITE_STANDARD_CONTRACT_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);

$files = [
    $root . '/DOC/OPUS_SITE_STANDARD_CONTRACT.md',
    $root . '/sites/opus-manager/DOC/OPUS_SITE_STANDARD_CONTRACT.md',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('OPUS_SITE_STANDARD_CONTRACT_FILE_MISSING: ' . $file);
    }

    $content = file_get_contents($file);
    if (!is_string($content)) {
        throw new RuntimeException('OPUS_SITE_STANDARD_CONTRACT_READ_FAILED: ' . $file);
    }

    foreach ([
        'OPUS_SITE_STANDARD_CONTRACT_CORE',
        'tous les sites OPUS présents et futurs',
        'sites/<site>/',
        'application/',
        'default/',
        '<controller>/',
        'acl/',
        'helpers/',
        'javascript/',
        'local/',
        'models/',
        'templates/',
        'views/',
        'www/',
        'asset/',
        'css/',
        'js/',
        'themes/',
        'application`, pas `src`',
        'www`, pas `public`',
        'OPUS Manager est une application OPUS de type AMS',
        'OPUS, encore OPUS',
    ] as $marker) {
        if (!str_contains($content, $marker)) {
            throw new RuntimeException('OPUS_SITE_STANDARD_CONTRACT_MARKER_MISSING: ' . $marker);
        }
    }
}

echo 'CHECK_OPUS_SITE_STANDARD_CONTRACT=OK' . PHP_EOL;
echo 'OPUS_SITE_STANDARD_CONTRACT_CORE_SMOKE_OK' . PHP_EOL;
