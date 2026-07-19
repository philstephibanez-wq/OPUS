<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$moduleFile = $root . '/sites/owasys/application/default/http/request-context.php';
if (!is_file($moduleFile)) {
    throw new RuntimeException('OWASYS_REQUEST_CONTEXT_MODULE_MISSING');
}

$buildRequestContext = require $moduleFile;
if (!$buildRequestContext instanceof Closure) {
    throw new RuntimeException('OWASYS_REQUEST_CONTEXT_MODULE_INVALID');
}

$cases = [
    [
        'server' => ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
        'method' => 'GET', 'path' => '/', 'mount' => '', 'link' => '/applications', 'asset' => '/asset/css/owasys.css',
    ],
    [
        'server' => ['REQUEST_METHOD' => 'post', 'REQUEST_URI' => '/owasys'],
        'method' => 'POST', 'path' => '/', 'mount' => '/owasys', 'link' => '/owasys/applications', 'asset' => '/owasys/asset/css/owasys.css',
    ],
    [
        'server' => ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/owasys/applications?lang=fr'],
        'method' => 'GET', 'path' => '/applications', 'mount' => '/owasys', 'link' => '/owasys/applications', 'asset' => '/owasys/asset/css/owasys.css',
    ],
    [
        'server' => ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/structure'],
        'method' => 'GET', 'path' => '/structure', 'mount' => '', 'link' => '/applications', 'asset' => '/asset/css/owasys.css',
    ],
];

foreach ($cases as $index => $case) {
    $context = $buildRequestContext($case['server']);
    foreach (['method', 'path', 'mount', 'link', 'asset'] as $required) {
        if (!array_key_exists($required, $context)) {
            throw new RuntimeException('OWASYS_REQUEST_CONTEXT_FIELD_MISSING:' . $index . ':' . $required);
        }
    }

    if ($context['method'] !== $case['method'] || $context['path'] !== $case['path'] || $context['mount'] !== $case['mount']) {
        throw new RuntimeException('OWASYS_REQUEST_CONTEXT_NORMALIZATION_FAILED:' . $index);
    }
    if (!$context['link'] instanceof Closure || $context['link']('/applications') !== $case['link']) {
        throw new RuntimeException('OWASYS_REQUEST_CONTEXT_LINK_FAILED:' . $index);
    }
    if (!$context['asset'] instanceof Closure || $context['asset']('/asset/css/owasys.css') !== $case['asset']) {
        throw new RuntimeException('OWASYS_REQUEST_CONTEXT_ASSET_FAILED:' . $index);
    }
}

echo 'OWASYS_REQUEST_CONTEXT_MODULE_SMOKE_OK' . PHP_EOL;
