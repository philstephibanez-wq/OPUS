<?php
declare(strict_types=1);

$path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'WORKSPACE_STATUS.md';
if (!is_file($path)) {
    throw new RuntimeException('WORKSPACE_STATUS_NOT_FOUND');
}

$text = (string) file_get_contents($path);
$replacements = [
    '/- Latest validated milestone: `[^`]+`/' => '- Latest validated milestone: `P7_ODBC_EXPLORER_CRUD_UI_CORE`',
    '/- Latest functional commit: `[^`]+`/' => '- Latest functional commit: `pending commit after P7_ODBC_EXPLORER_CRUD_UI_CORE smoke`',
    '/- Previous validated milestone: `[^`]+`/' => '- Previous validated milestone: `P7_ODBC_EXPLORER_CRUD_CORE`',
    '/- Previous cleanup commit: `[^`]+`/' => '- Previous cleanup commit: `cb9d3b7`',
];
foreach ($replacements as $pattern => $replacement) {
    $text = preg_replace($pattern, $replacement, $text, 1) ?? $text;
}

if (!str_contains($text, '`P7_ODBC_EXPLORER_CRUD_UI_CORE`')) {
    $text .= PHP_EOL . '- `P7_ODBC_EXPLORER_CRUD_UI_CORE`: OK in source. Guarded CRUD UI routes, controllers, templates, I18N, ACL, navigation and profiler actions are validated.' . PHP_EOL;
}

$text = preg_replace(
    '/## Next recommended milestones\s+.*?## Operational rule/s',
    "## Next recommended milestones\n\n1. `P7_ODBC_MODEL_REFINEMENT_CORE`: refine OPUS Model constraints and metadata required before LSTSAR.\n2. Pause and explicitly notify the user before starting `P7_LSTSAR_MODEL_DRIVEN_ODBC_CORE`.\n\n## Operational rule",
    $text,
    1
) ?? $text;

file_put_contents($path, $text);
echo "WORKSPACE_STATUS_UPDATED\n";
