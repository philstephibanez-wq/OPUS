<?php
declare(strict_types=1);
$manifestPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'opus-package.json';
if (!is_file($manifestPath)) {
    http_response_code(500);
    echo 'OPUS_REFBOOK_MANIFEST_MISSING';
    exit(1);
}
$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
$packageName = $manifest['package_name'] ?? null;
if (!is_string($packageName)) {
    http_response_code(500);
    echo 'OPUS_REFBOOK_PACKAGE_NAME_MISSING';
    exit(1);
}
if ($packageName === '') {
    http_response_code(500);
    echo 'OPUS_REFBOOK_PACKAGE_NAME_EMPTY';
    exit(1);
}
header('Content-Type: text/plain; charset=UTF-8');
echo $packageName . PHP_EOL;
