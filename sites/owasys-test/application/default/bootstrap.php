<?php
declare(strict_types=1);

$siteRoot = dirname(__DIR__, 2);
$opusRoot = dirname(dirname($siteRoot));
$autoload = $opusRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit;
}
require_once $autoload;
require_once __DIR__ . '/Application.php';

OwasysTestApplication::instance($siteRoot)->run();