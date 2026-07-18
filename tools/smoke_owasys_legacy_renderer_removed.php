<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$file = $root . '/sites/owasys/application/application.php';
$source = file_get_contents($file);

if (!is_string($source)) {
    throw new RuntimeException('OWASYS_LEGACY_APPLICATION_UNREADABLE');
}

foreach (['ow-shell', 'ow-sidebar', 'class="ow-nav"', 'mermaid.min.js', 'OWASYS public entry'] as $forbidden) {
    if (str_contains($source, $forbidden)) {
        throw new RuntimeException('OWASYS_LEGACY_RENDERER_STILL_PRESENT:' . $forbidden);
    }
}

if (!str_contains($source, 'OWASYS_LEGACY_APPLICATION_REMOVED')) {
    throw new RuntimeException('OWASYS_LEGACY_REMOVAL_SENTINEL_MISSING');
}

if (substr_count($source, "\n") > 12) {
    throw new RuntimeException('OWASYS_LEGACY_APPLICATION_NOT_MINIMAL');
}

echo 'OWASYS_LEGACY_RENDERER_REMOVED_SMOKE_OK' . PHP_EOL;
