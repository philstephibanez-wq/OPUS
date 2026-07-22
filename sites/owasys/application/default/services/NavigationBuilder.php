<?php
declare(strict_types=1);

final class OwasysNavigationBuilder
{
    public function __construct(private readonly OwasysRuntimeSecurity $security)
    {
    }

    /**
     * @param array<string,mixed> $fsmConfig
     * @param array<string,mixed>|null $identity
     * @param callable(string):string $routeUrl
     * @return list<array<string,mixed>>
     */
    public function build(
        array $fsmConfig,
        ?array $identity,
        string $currentState,
        callable $routeUrl
    ): array {
        $items = [];

        foreach ((array) ($fsmConfig['states'] ?? []) as $state) {
            if (!is_array($state)) continue;
            $navigation = is_array($state['navigation'] ?? null) ? $state['navigation'] : [];
            if (($navigation['visible'] ?? false) !== true) continue;

            $stateId = (string) ($state['id'] ?? '');
            $module = (string) ($state['module'] ?? $stateId);
            $route = (string) ($state['route'] ?? '');
            $labelKey = (string) ($navigation['label'] ?? ('menu.' . $module));

            if ($stateId === '' || $route === '' || $labelKey === '') {
                throw new RuntimeException('OWASYS_NAVIGATION_STATE_INVALID:' . $stateId);
            }

            $items[] = [
                'id' => $stateId,
                'module' => $module,
                'label_key' => $labelKey,
                'label' => '',
                'url' => $routeUrl($route),
                'allowed' => $this->security->isAllowed($identity, $module, 'open'),
                'active' => $stateId === $currentState,
                'order' => (int) ($navigation['order'] ?? 1000),
            ];
        }

        usort($items, static fn (array $left, array $right): int => ($left['order'] <=> $right['order']));
        return $items;
    }
}
