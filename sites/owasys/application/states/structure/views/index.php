<?php
declare(strict_types=1);

use Opus\Owasys\ApplicationInspector;

$inspection = null;
$error = null;
$targetDiagram = '';

$mermaidId = static function (string $value): string {
    $id = preg_replace('/[^A-Za-z0-9_]/', '_', $value);
    $id = is_string($id) ? trim($id, '_') : '';
    return $id === '' ? 'state_unknown' : 'state_' . $id;
};
$mermaidText = static function (string $value): string {
    return str_replace(["\\", '"', "\r", "\n"], ['\\\\', '\\"', ' ', ' '], trim($value));
};
$buildDiagram = static function (array $fsmConfig, string $activeState = '') use ($mermaidId, $mermaidText): string {
    $states = [];
    foreach ((array) ($fsmConfig['states'] ?? []) as $stateRow) {
        if (!is_array($stateRow)) {
            continue;
        }
        $id = (string) ($stateRow['id'] ?? '');
        if ($id !== '') {
            $states[$id] = $stateRow;
        }
    }

    $lines = ['flowchart LR'];
    foreach ($states as $id => $stateRow) {
        $node = $mermaidId($id);
        $label = $mermaidText((string) ($stateRow['label'] ?? $stateRow['title'] ?? $id));
        $class = $id === $activeState ? 'active' : 'primary';
        $lines[] = '    ' . $node . '["' . $label . '"]:::' . $class;
    }
    foreach ((array) ($fsmConfig['transitions'] ?? []) as $transition) {
        if (!is_array($transition)) {
            continue;
        }
        $from = (string) ($transition['from'] ?? '');
        $to = (string) ($transition['to'] ?? '');
        if ($from === '' || $to === '' || !isset($states[$from], $states[$to])) {
            continue;
        }
        $event = $mermaidText((string) ($transition['event'] ?? 'event'));
        $lines[] = '    ' . $mermaidId($from) . ' -->|' . $event . '| ' . $mermaidId($to);
    }
    $lines[] = '    classDef primary fill:#123456,stroke:#6ce3ff,color:#f6f8ff,stroke-width:2px';
    $lines[] = '    classDef active fill:#164e63,stroke:#4ade80,color:#f6f8ff,stroke-width:4px';
    return implode("\n", $lines);
};

if (is_array($currentApplication ?? null)) {
    try {
        $inspection = ApplicationInspector::forOpusRoot($opusRoot)->inspectEntry($currentApplication);
        $targetRoot = $opusRoot . '/' . str_replace('\\', '/', (string) ($inspection['root_path'] ?? ''));
        $fsmFile = $targetRoot . '/' . str_replace('\\', '/', (string) ($inspection['fsm_relative_path'] ?? ''));
        $fsmConfig = json_decode((string) file_get_contents($fsmFile), true);
        if (!is_array($fsmConfig)) {
            throw new RuntimeException('OWASYS_STRUCTURE_FSM_CONFIG_INVALID');
        }
        $targetDiagram = $buildDiagram($fsmConfig, (string) ($fsmConfig['initial_state'] ?? ''));
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$states = [];
$routes = [];
$summary = [];
if (is_array($inspection)) {
    foreach ((array) ($inspection['states'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $states[] = [
            'id' => (string) ($row['id'] ?? ''),
            'directory' => (string) ($row['directory'] ?? ''),
            'fsm' => ($row['in_fsm'] ?? false) === true ? 'FSM' : '—',
            'routes' => ($row['in_routes'] ?? false) === true ? 'Routes' : '—',
        ];
    }
    foreach ((array) ($inspection['routes'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $routes[] = [
            'path' => (string) ($row['path'] ?? ''),
            'state' => (string) ($row['state'] ?? ''),
            'view' => (string) ($row['view'] ?? ''),
        ];
    }
    $summary = [
        ['label' => 'Application', 'value' => (string) ($inspection['site_name'] ?? $inspection['site_id'] ?? '')],
        ['label' => 'Racine', 'value' => (string) ($inspection['root_path'] ?? '')],
        ['label' => 'Contrat', 'value' => (string) ($inspection['site_contract'] ?? '')],
        ['label' => 'FSM', 'value' => (string) ($inspection['fsm_contract'] ?? '')],
        ['label' => 'États', 'value' => (string) ($inspection['state_count'] ?? 0)],
        ['label' => 'Routes', 'value' => (string) ($inspection['route_count'] ?? 0)],
        ['label' => 'Transitions', 'value' => (string) ($inspection['transition_count'] ?? 0)],
    ];
}

return [
    'state' => 'structure',
    'title_key' => 'state.structure.title',
    'badge_key' => 'state.structure.title',
    'summary_key' => 'inspection.description',
    'template' => 'index.score',
    'contracts' => [
        'OWASYS_APPLICATION_INSPECTION_V1',
        'OPUS_ROUTE_REGISTRY_V1',
        'OPUS_APPLICATION_FSM_V1',
        'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
    ],
    'action_keys' => [],
    'state_content' => [
        'has_application' => is_array($currentApplication ?? null),
        'has_inspection' => is_array($inspection),
        'has_error' => $error !== null,
        'error' => $error ?? '',
        'application_name' => is_array($currentApplication ?? null)
            ? (string) ($currentApplication['name'] ?? $currentApplication['id'] ?? '')
            : '',
        'summary' => $summary,
        'states' => $states,
        'routes' => $routes,
        'diagram' => $targetDiagram,
        'diagram_available' => $targetDiagram !== '',
        'diagram_js' => $request->asset('/asset/js/fsm-diagram.js'),
    ],
];
