<?php
declare(strict_types=1);

use Opus\Fsm\FsmProcessor;

/**
 * Build the navigation fragment of the OWASYS ScoreTemplate ViewModel.
 *
 * @return array{items:list<array<string,mixed>>,current_state:string,aria_label:string}
 */
return static function (
    FsmProcessor $fsm,
    string $currentState,
    array $runtimeContext,
    string $profile,
    array $presentation,
    array $acl,
    string $applicationBaseUrl,
    callable $translate
): array {
    $project = require __DIR__ . '/project.php';
    $items = $project($fsm, $currentState, $runtimeContext, $profile, $presentation, $acl);

    $base = rtrim($applicationBaseUrl, '/');
    foreach ($items as &$item) {
        $labelKey = (string) ($item['label_key'] ?? '');
        $targetRoute = '/' . ltrim((string) ($item['target_route'] ?? ''), '/');
        $item['label'] = $translate($labelKey);
        $item['current'] = ((string) ($item['target_state'] ?? '')) === $currentState;
        $item['href'] = $base . ($targetRoute === '/' ? '/' : $targetRoute);
    }
    unset($item);

    return [
        'items' => $items,
        'current_state' => $currentState,
        'aria_label' => $translate('navigation.aria_label'),
    ];
};
