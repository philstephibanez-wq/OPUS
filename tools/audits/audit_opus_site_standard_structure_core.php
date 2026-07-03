<?php
declare(strict_types=1);

echo 'OPUS_SITE_STANDARD_STRUCTURE_AUDIT' . PHP_EOL;

$root = dirname(__DIR__, 2);
$sitesRoot = $root . '/sites';
$reportFile = $root . '/DOC/OPUS_SITE_STANDARD_STRUCTURE_AUDIT.md';

$siteNames = [];
if (is_dir($sitesRoot)) {
    foreach (new DirectoryIterator($sitesRoot) as $entry) {
        if ($entry->isDot() || !$entry->isDir()) {
            continue;
        }
        $siteNames[] = $entry->getFilename();
    }
}

sort($siteNames);

$lines = [];
$lines[] = '# OPUS — Site standard structure audit';
$lines[] = '';
$lines[] = 'Contrat : `OPUS_SITE_STANDARD_CONTRACT_CORE`';
$lines[] = '';
$lines[] = '## Règle';
$lines[] = '';
$lines[] = 'Tous les sites OPUS présents et futurs doivent respecter la structure standard `application` + `www/asset`.';
$lines[] = '';

$violations = [];

foreach ($siteNames as $site) {
    $sitePath = $sitesRoot . '/' . $site;
    $relative = 'sites/' . $site;

    $requiredDirs = [
        'application',
        'application/default',
        'config',
        'www',
        'www/asset',
        'www/asset/css',
        'www/asset/js',
        'www/asset/themes',
    ];

    foreach ($requiredDirs as $dir) {
        if (!is_dir($sitePath . '/' . $dir)) {
            $violations[] = $relative . '/' . $dir;
        }
    }

    foreach (['src', 'public'] as $forbidden) {
        if (is_dir($sitePath . '/' . $forbidden)) {
            $violations[] = $relative . '/' . $forbidden . ' FORBIDDEN';
        }
    }
}

$lines[] = '## Sites détectés';
$lines[] = '';
if ($siteNames === []) {
    $lines[] = '- Aucun site détecté sous `sites/`.';
} else {
    foreach ($siteNames as $site) {
        $lines[] = '- `' . $site . '`';
    }
}
$lines[] = '';

$lines[] = '## Violations';
$lines[] = '';
if ($violations === []) {
    $lines[] = '`OPUS_SITE_STANDARD_STRUCTURE_OK`';
} else {
    $lines[] = '`OPUS_SITE_STANDARD_STRUCTURE_VIOLATIONS_PRESENT`';
    $lines[] = '';
    foreach ($violations as $violation) {
        $lines[] = '- `' . $violation . '`';
    }
}
$lines[] = '';

if (file_put_contents($reportFile, implode(PHP_EOL, $lines) . PHP_EOL) === false) {
    fwrite(STDERR, 'OPUS_SITE_STANDARD_STRUCTURE_AUDIT_WRITE_FAILED' . PHP_EOL);
    exit(1);
}

echo $violations === [] ? 'OPUS_SITE_STANDARD_STRUCTURE_OK' . PHP_EOL : 'OPUS_SITE_STANDARD_STRUCTURE_VIOLATIONS_PRESENT' . PHP_EOL;
echo $reportFile . PHP_EOL;
