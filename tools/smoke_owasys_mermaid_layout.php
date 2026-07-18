<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$theme = $root . '/sites/owasys/www/asset/themes/owasys/js/theme.js';
$source = file_get_contents($theme);

if (!is_string($source)) {
    throw new RuntimeException('OWASYS_MERMAID_THEME_UNREADABLE');
}

foreach ([
    'nodeSpacing: 56',
    'rankSpacing: 96',
    'diagramPadding: 32',
    'useMaxWidth: false',
    "curve: 'basis'",
] as $required) {
    if (!str_contains($source, $required)) {
        throw new RuntimeException('OWASYS_MERMAID_LAYOUT_SETTING_MISSING:' . $required);
    }
}

foreach ([
    'createElement(',
    'appendChild(',
    'innerHTML',
] as $forbidden) {
    if (str_contains($source, $forbidden)) {
        throw new RuntimeException('OWASYS_MERMAID_THEME_STRUCTURAL_JS_FORBIDDEN:' . $forbidden);
    }
}

echo 'OWASYS_MERMAID_LAYOUT_SMOKE_OK' . PHP_EOL;
