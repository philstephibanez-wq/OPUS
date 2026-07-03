<?php
declare(strict_types=1);

echo 'OPUS_MANAGER_OPUS_ONLY_REALIGNMENT_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);

$files = [
    $root . '/DOC/OPUS_MANAGER_OPUS_ONLY_REALIGNMENT.md',
    $root . '/DOC/OPUS_MANAGER_OPUS_ONLY_REALIGNMENT_AUDIT.md',
    $root . '/sites/opus-manager/DOC/OPUS_MANAGER_OPUS_ONLY_REALIGNMENT.md',
    $root . '/tools/audits/audit_opus_manager_opus_only_realignment_core.php',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('OPUS_MANAGER_OPUS_ONLY_REALIGNMENT_FILE_MISSING: ' . $file);
    }
}

$combined = '';
foreach ($files as $file) {
    $source = file_get_contents($file);
    if (!is_string($source)) {
        throw new RuntimeException('OPUS_MANAGER_OPUS_ONLY_REALIGNMENT_READ_FAILED: ' . $file);
    }
    $combined .= $source . PHP_EOL;
}

foreach ([
    'OPUS_MANAGER_OPUS_ONLY_REALIGNMENT_CORE',
    'OPUS = framework',
    'OPUS Manager = AMS',
    'OPUS, encore OPUS',
    'OPUS I18N',
    'OPUS templates',
    'OPUS Identity',
    'OPUS ACL',
    'OPUS FSM',
    'OPUS_ONLY_AUDIT',
] as $marker) {
    if (!str_contains($combined, $marker)) {
        throw new RuntimeException('OPUS_MANAGER_OPUS_ONLY_REALIGNMENT_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_OPUS_MANAGER_OPUS_ONLY_REALIGNMENT=OK' . PHP_EOL;
echo 'OPUS_MANAGER_OPUS_ONLY_REALIGNMENT_CORE_SMOKE_OK' . PHP_EOL;
