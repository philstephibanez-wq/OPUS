<?php
declare(strict_types=1);

$siteRoot = dirname(__DIR__);
$opusRoot = dirname(dirname($siteRoot));
$autoload = $opusRoot . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(500);
    exit('OWASYS_COMPOSER_AUTOLOAD_MISSING');
}

require_once $autoload;

http_response_code(503);
header('Content-Type: text/plain; charset=UTF-8');
echo "OWASYS_REFACTOR_FOUNDATION_READY\n";
