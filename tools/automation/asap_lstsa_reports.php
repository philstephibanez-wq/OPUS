<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaReportCatalog.php';

use ASAP\Lstsa\LstsaReportCatalog;

$limit = 50;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int)substr($arg, 8));
    }
}

$result = (new LstsaReportCatalog($root))->writeIndex($limit);
$index = $result['index'];

echo 'ASAP_Lstsa_REPORT_CATALOG_JSON=' . $result['json'] . PHP_EOL;
echo 'ASAP_Lstsa_REPORT_CATALOG_MD=' . $result['markdown'] . PHP_EOL;
echo 'ASAP_Lstsa_REPORT_CATALOG_TOTAL_RUNS=' . (string)($index['total_runs'] ?? 0) . PHP_EOL;
echo 'ASAP_Lstsa_REPORT_CATALOG_VISIBLE_RUNS=' . count((array)($index['runs'] ?? [])) . PHP_EOL;
