<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\ApplicationExporter;

if (($argv[1] ?? '') === '' || in_array($argv[1] ?? '', ['-h', '--help'], true)) {
    echo "OWASYS application exporter\n";
    echo "Usage:\n";
    echo "  php tools/owasys_export_application.php SITE_ID [OUTPUT_ZIP] [--overwrite]\n";
    echo "\n";
    echo "Default output: var/owasys-export/<SITE_ID>.zip\n";
    exit(($argv[1] ?? '') === '' ? 1 : 0);
}

$siteId = (string) $argv[1];
$args = array_slice($argv, 2);
$overwrite = in_array('--overwrite', $args, true);
$outputZip = '';
foreach ($args as $arg) {
    if (str_starts_with($arg, '--')) {
        continue;
    }
    $outputZip = (string) $arg;
    break;
}

if ($outputZip === '') {
    $outputZip = 'var/owasys-export/' . $siteId . '.zip';
}

try {
    $summary = (new ApplicationExporter(dirname(__DIR__)))->export($siteId, $outputZip, $overwrite);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

echo "OWASYS_APPLICATION_EXPORT_OK: " . (string) $summary['output_zip'] . "\n";
echo "OWASYS_APPLICATION_EXPORT_FILES=" . (int) $summary['files'] . "\n";
echo "OWASYS_APPLICATION_EXPORT_BYTES=" . (int) $summary['bytes'] . "\n";
echo "OWASYS_APPLICATION_EXPORT_MANIFEST=" . (string) $summary['manifest_path'] . "\n";
