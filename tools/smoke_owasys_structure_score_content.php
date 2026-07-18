<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$site = $root . '/sites/owasys';
$view = $site . '/application/states/structure/views/index.php';
$template = $site . '/application/states/structure/templates/index.score';
$scorePage = $site . '/application/score-page.php';
$css = $site . '/www/asset/css/score.css';
$diagramJs = $site . '/www/asset/js/fsm-diagram.js';

foreach ([$view, $template, $scorePage, $css, $diagramJs] as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('OWASYS_STRUCTURE_SCORE_FILE_MISSING:' . $file);
    }
}

$viewSource = (string) file_get_contents($view);
foreach (['ApplicationInspector::forOpusRoot', "'template' => 'index.score'", "'state_content' =>", 'flowchart LR', "diagram_js"] as $marker) {
    if (!str_contains($viewSource, $marker)) {
        throw new RuntimeException('OWASYS_STRUCTURE_VIEWMODEL_MARKER_MISSING:' . $marker);
    }
}
foreach (['<section', '<form', '<script', 'ow-sidebar', 'ow-shell'] as $forbidden) {
    if (str_contains($viewSource, $forbidden)) {
        throw new RuntimeException('OWASYS_STRUCTURE_VIEWMODEL_FORBIDDEN_MARKER:' . $forbidden);
    }
}

$templateSource = (string) file_get_contents($template);
foreach (['OWASYS_STRUCTURE_INSPECTION', 'OWASYS_MERMAID_NAVIGATION', 'OWASYS_FSM_DIAGRAM', 'data-fsm-canvas', 'data-fsm-source', 'state_content.diagram_js'] as $marker) {
    if (!str_contains($templateSource, $marker)) {
        throw new RuntimeException('OWASYS_STRUCTURE_TEMPLATE_MARKER_MISSING:' . $marker);
    }
}
if (str_contains($templateSource, '<?')) {
    throw new RuntimeException('OWASYS_STRUCTURE_TEMPLATE_PHP_FORBIDDEN');
}

$diagramSource = (string) file_get_contents($diagramJs);
foreach (['createElementNS', 'ow-fsm-svg', 'data-context="OWASYS_FSM_DIAGRAM"', 'marker-end'] as $marker) {
    if (!str_contains($diagramSource, $marker)) {
        throw new RuntimeException('OWASYS_STRUCTURE_DIAGRAM_RENDERER_MARKER_MISSING:' . $marker);
    }
}
foreach (['mermaid.initialize', 'cdn.jsdelivr.net', 'unpkg.com'] as $forbidden) {
    if (str_contains($diagramSource, $forbidden)) {
        throw new RuntimeException('OWASYS_STRUCTURE_DIAGRAM_RENDERER_EXTERNAL_DEPENDENCY:' . $forbidden);
    }
}

$scoreSource = (string) file_get_contents($scorePage);
foreach (['application/states/', "new ScoreTemplateRenderer(\$siteRoot . '/application/states/'", "\$page['template']"] as $marker) {
    if (!str_contains($scoreSource, $marker)) {
        throw new RuntimeException('OWASYS_STATE_TEMPLATE_DISPATCH_MISSING:' . $marker);
    }
}

$cssSource = (string) file_get_contents($css);
foreach (['.ow-fsm-svg', '.ow-fsm-node', '.ow-fsm-edge'] as $marker) {
    if (!str_contains($cssSource, $marker)) {
        throw new RuntimeException('OWASYS_STRUCTURE_DIAGRAM_STYLE_MISSING:' . $marker);
    }
}
if (str_contains($cssSource, '@import') || str_contains($cssSource, 'ow-sidebar') || str_contains($cssSource, 'ow-shell')) {
    throw new RuntimeException('OWASYS_SCORE_CSS_NOT_SELF_CONTAINED');
}

foreach (['owasys.css', 'application/application.php'] as $deleted) {
    $path = $deleted === 'owasys.css' ? $site . '/www/asset/css/owasys.css' : $site . '/application/application.php';
    if (is_file($path)) {
        throw new RuntimeException('OWASYS_STRUCTURE_LEGACY_FILE_PRESENT:' . $deleted);
    }
}

echo 'OWASYS_STRUCTURE_SCORE_CONTENT_SMOKE_OK' . PHP_EOL;
