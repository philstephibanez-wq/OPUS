<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$site = $root . '/sites/owasys';
$files = [
    $site . '/application/default/navigation/fsm-diagram-view-model.php',
    $site . '/application/default/templates/partials/fsm-diagram.score',
    $site . '/application/default/templates/layouts/main.score',
    $site . '/application/score-page.php',
    $site . '/www/asset/js/fsm-diagram.js',
    $site . '/www/asset/css/score.css',
];
foreach ($files as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('OWASYS_GLOBAL_FSM_FILE_MISSING:' . $file);
    }
}

$layout = (string) file_get_contents($files[2]);
if (!str_contains($layout, '[[ include:partials/fsm-diagram.score ]]')) {
    throw new RuntimeException('OWASYS_GLOBAL_FSM_LAYOUT_INCLUDE_MISSING');
}

$viewModel = (string) file_get_contents($files[0]);
foreach (['$navigation', '$fsm->transitions()', "'visible' => true", "'source' => implode", "'click '"] as $marker) {
    if (!str_contains($viewModel, $marker)) {
        throw new RuntimeException('OWASYS_GLOBAL_FSM_VIEWMODEL_MARKER_MISSING:' . $marker);
    }
}

$scorePage = (string) file_get_contents($files[3]);
foreach (['fsm-diagram-view-model.php', "'fsm_diagram' => \$fsmDiagram", '/asset/js/fsm-diagram.js'] as $marker) {
    if (!str_contains($scorePage, $marker)) {
        throw new RuntimeException('OWASYS_GLOBAL_FSM_SCORE_PAGE_MARKER_MISSING:' . $marker);
    }
}

$javascript = (string) file_get_contents($files[4]);
foreach (['const links = new Map()', 'click\\s+', "wrapper.setAttribute('href', href)", 'ow-fsm-link'] as $marker) {
    if (!str_contains($javascript, $marker)) {
        throw new RuntimeException('OWASYS_GLOBAL_FSM_CLICK_MARKER_MISSING:' . $marker);
    }
}

$template = (string) file_get_contents($files[1]);
foreach (['OWASYS_GLOBAL_FSM_DIAGRAM', 'OWASYS_FSM_DIAGRAM', 'fsm_diagram.source', 'fsm_diagram.asset_js'] as $marker) {
    if (!str_contains($template, $marker)) {
        throw new RuntimeException('OWASYS_GLOBAL_FSM_TEMPLATE_MARKER_MISSING:' . $marker);
    }
}

echo 'OWASYS_GLOBAL_FSM_DIAGRAM_SMOKE_OK' . PHP_EOL;
