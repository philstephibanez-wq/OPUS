<?php
declare(strict_types=1);

use Opus\Fsm\FsmProcessor;

/**
 * Builds the shared clickable FSM diagram from the same authorized navigation
 * projection used by the global menu.
 *
 * @return array{visible:bool,title:string,summary:string,source:string,asset_js:string}
 */
return static function (
    FsmProcessor $fsm,
    array $navigation,
    string $currentState,
    string $assetJs,
    callable $translate
): array {
    $itemsByState = [];
    foreach ((array) ($navigation['items'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $state = (string) ($item['target_state'] ?? '');
        $href = (string) ($item['href'] ?? '');
        if ($state === '' || $href === '') {
            continue;
        }
        $itemsByState[$state] = [
            'label' => (string) ($item['label'] ?? $state),
            'href' => $href,
        ];
    }

    if ($itemsByState === []) {
        return [
            'visible' => false,
            'title' => '',
            'summary' => '',
            'source' => '',
            'asset_js' => $assetJs,
        ];
    }

    $nodeId = static function (string $value): string {
        $id = preg_replace('/[^A-Za-z0-9_]/', '_', $value);
        $id = is_string($id) ? trim($id, '_') : '';
        return $id === '' ? 'state_unknown' : 'state_' . $id;
    };
    $text = static function (string $value): string {
        return str_replace(["\\", '"', "\r", "\n", '|'], ['\\\\', '\\"', ' ', ' ', '/'], trim($value));
    };

    $lines = ['flowchart LR'];
    foreach ($itemsByState as $state => $item) {
        $class = $state === $currentState ? 'active' : 'primary';
        $lines[] = '    ' . $nodeId($state) . '["' . $text($item['label']) . '"]:::' . $class;
        $lines[] = '    click ' . $nodeId($state) . ' "' . $text($item['href']) . '"';
    }

    $seenEdges = [];
    foreach ($fsm->transitions() as $transition) {
        if (!is_array($transition) || ($transition['visual'] ?? false) !== true) {
            continue;
        }
        $from = (string) ($transition['from'] ?? '');
        $to = (string) ($transition['to'] ?? '');
        if ($from === '*' || !isset($itemsByState[$from], $itemsByState[$to])) {
            continue;
        }
        $edgeKey = $from . '>' . $to;
        if (isset($seenEdges[$edgeKey])) {
            continue;
        }
        $seenEdges[$edgeKey] = true;
        $lines[] = '    ' . $nodeId($from) . ' -->|' . $text((string) ($transition['event'] ?? 'event')) . '| ' . $nodeId($to);
    }

    $lines[] = '    classDef primary fill:#123456,stroke:#6ce3ff,color:#f6f8ff,stroke-width:2px';
    $lines[] = '    classDef active fill:#164e63,stroke:#4ade80,color:#f6f8ff,stroke-width:4px';

    return [
        'visible' => true,
        'title' => $translate('mermaid.title'),
        'summary' => $translate('mermaid.description'),
        'source' => implode("\n", $lines),
        'asset_js' => $assetJs,
    ];
};
