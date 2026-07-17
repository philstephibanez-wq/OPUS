<?php
declare(strict_types=1);

use Opus\Fsm\FsmProcessor;

/**
 * Build the navigation fragment of the OWASYS ScoreTemplate ViewModel.
 *
 * @return array{items:list<array<string,mixed>>,action:string,current_state:string}
 */
return static function (
    FsmProcessor $fsm,
    string $currentState,
    array $runtimeContext,
    string $profile,
    array $presentation,
    array $acl,
    string $actionUrl,
    callable $translate
): array {
    $project = require __DIR__ . '/project.php';
    $items = $project($fsm, $currentState, $runtimeContext, $profile, $presentation, $acl);

    foreach ($items as &$item) {
        $labelKey = (string) ($item['label_key'] ?? '');
        $item['label'] = $translate($labelKey);
        $item['current'] = ((string) ($item['target_state'] ?? '')) === $currentState;
    }
    unset($item);

    return [
        'items' => $items,
        'action' => $actionUrl,
        'current_state' => $currentState,
    ];
};
