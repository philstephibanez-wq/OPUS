<?php
declare(strict_types=1);

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/source'), PHP_URL_PATH);
$requestPath = is_string($requestPath) ? $requestPath : '/source';
$sourceAssetBase = str_starts_with($requestPath, '/owasys/') ? '/owasys' : '';
$assetBaseEscaped = htmlspecialchars($sourceAssetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
echo '<script defer src="' . $assetBaseEscaped . '/asset/js/source-browser.js"></script>';

return [
    'state' => 'source',
    'title' => 'Source & Git',
    'badge' => 'Application source browser',
    'summary' => 'Inspect the selected application files with syntax highlighting.',
    'sections' => [],
    'contracts' => ['OWASYS_SOURCE_BROWSER_V1'],
    'actions' => [],
];
