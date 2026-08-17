<?php
declare(strict_types=1);

use Opus\File\StructuredFileLoader;
use Opus\Fsm\FsmDiagramGeometryNormalizer;

/** Builds a fixed visual projection from the canonical OWASYS FSM. */
final class OwasysFsmDiagramBuilder
{
    private const REVISION = 'P117W_R45B2A4AO';

    public function __construct(
        private readonly string $siteRoot,
        private readonly OwasysAuthSession $session
    ) {
    }

    /**
     * The topology source is config/fsm.json. Presentation hints come only
     * from state.diagram {rank,order}; current state changes highlight only.
     *
     * Dense same-state technical workflows are reduced to one representative
     * self-loop per signal type, while every non-self workflow relation is
     * kept. Finite global navigation is represented once, except logout which
     * is expanded from every applicable authenticated state so its universal
     * return to login remains explicit.
     *
     * @param array<string,mixed> $pageData
     * @return array<string,mixed>
     */
    public function build(array $pageData): array
    {
        $identityView = is_array($pageData['identity'] ?? null)
            ? $pageData['identity']
            : [];
        $currentState = trim((string) ($pageData['fsm']['state'] ?? ''));

        if (($identityView['authenticated'] ?? false) !== true) {
            return $this->hidden();
        }
        if (!is_array($this->session->user())) {
            throw new RuntimeException(
                'OWASYS_FSM_NATIVE_IDENTITY_REQUIRED'
            );
        }

        $fsm = $this->loadFsm();
        $statesById = $this->statesById($fsm);
        $signalRegistry = $this->signalRegistry($fsm);
        $this->assertSignalRegistryComplete($fsm, $signalRegistry);

        $menuByState = [];
        $stateLabels = [];
        foreach ((array) ($pageData['navigation'] ?? []) as $item) {
            if (!is_array($item) || ($item['allowed'] ?? false) !== true) {
                continue;
            }
            $id = trim((string) ($item['id'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
            if ($id === '' || $label === '' || !isset($statesById[$id])) {
                throw new RuntimeException(
                    'OWASYS_FSM_MENU_PROJECTION_INVALID:' . $id
                );
            }
            $menuByState[$id] = $item;
            $stateLabels[$id] = $label;
        }

        if (!isset($statesById[$currentState], $menuByState[$currentState])) {
            return $this->hidden();
        }

        $initialState = trim((string) ($fsm['initial_state'] ?? ''));
        if ($initialState === '' || !isset($statesById[$initialState])) {
            throw new RuntimeException(
                'OWASYS_FSM_WORKFLOW_INITIAL_STATE_INVALID:' . $initialState
            );
        }

        $stateOrder = $this->orderedStates($statesById, $menuByState);
        if (($stateOrder[0] ?? '') !== $initialState) {
            throw new RuntimeException(
                'OWASYS_FSM_WORKFLOW_INITIAL_ORDER_DIVERGENCE:'
                . $initialState
            );
        }

        $states = [];
        $layout = [];
        foreach ($stateOrder as $stateId) {
            $states[] = $statesById[$stateId];
            $hint = is_array($statesById[$stateId]['diagram'] ?? null)
                ? $statesById[$stateId]['diagram']
                : [];
            $layout[$stateId] = [
                'rank' => (int) ($hint['rank'] ?? 0),
                'order' => (int) ($hint['order'] ?? 0),
            ];
        }

        $currentActions = $this->currentActions(
            $menuByState,
            $currentState
        );
        $transitions = [];
        $transitionLabels = [];
        $transitionLinks = [];
        $displayedBySignalTarget = [];
        $selfLoopTypes = [];

        foreach ((array) ($fsm['transitions'] ?? []) as $transition) {
            if (!is_array($transition)) {
                throw new RuntimeException(
                    'OWASYS_FSM_TRANSITION_ENTRY_INVALID'
                );
            }
            if (($transition['interrupt'] ?? null) === 'nmi') {
                continue;
            }

            $scope = trim((string) ($transition['scope'] ?? ''));
            $signal = trim((string) ($transition['signal'] ?? ''));
            $to = trim((string) ($transition['next_state'] ?? ''));
            $definition = $signalRegistry[$signal] ?? null;
            if (!is_array($definition) || !isset($menuByState[$to])) {
                continue;
            }

            if ($scope === 'global') {
                $sources = array_values(array_filter(
                    $this->globalSources($transition),
                    static fn (string $source): bool => isset($menuByState[$source])
                ));
                if ($sources === []) {
                    continue;
                }

                if ($signal === 'logout') {
                    foreach ($sources as $source) {
                        $clone = $transition;
                        $clone['id'] = (string) $transition['id']
                            . '__from__' . $source;
                        $clone['from'] = $source;
                        unset($clone['scope'], $clone['from_states']);
                        $this->appendTransition(
                            $clone,
                            $signal,
                            $source,
                            $to,
                            $transitions,
                            $transitionLabels,
                            $displayedBySignalTarget
                        );
                    }
                    continue;
                }

                $source = $this->representativeGlobalSource(
                    $sources,
                    $to,
                    $layout
                );
                $clone = $transition;
                $clone['id'] = (string) $transition['id']
                    . '__representative__' . $source;
                $clone['from'] = $source;
                unset($clone['scope'], $clone['from_states']);
                $this->appendTransition(
                    $clone,
                    $signal,
                    $source,
                    $to,
                    $transitions,
                    $transitionLabels,
                    $displayedBySignalTarget
                );
                continue;
            }

            if ($scope !== '') {
                throw new RuntimeException(
                    'OWASYS_FSM_WORKFLOW_SCOPE_INVALID:' . $scope
                );
            }

            $from = trim((string) ($transition['from'] ?? ''));
            if (!isset($menuByState[$from])) {
                continue;
            }

            if ($from === $to) {
                $type = (string) ($definition['type'] ?? '');
                $loopKey = $from . "\0" . $type;
                if (isset($selfLoopTypes[$loopKey])) {
                    continue;
                }
                $selfLoopTypes[$loopKey] = true;
            }

            $this->appendTransition(
                $transition,
                $signal,
                $from,
                $to,
                $transitions,
                $transitionLabels,
                $displayedBySignalTarget
            );
        }

        foreach ($currentActions as $key => $action) {
            $candidates = $displayedBySignalTarget[$key] ?? [];
            if ($candidates === []) {
                continue;
            }
            $selected = $candidates[0];
            foreach ($candidates as $candidate) {
                if (($candidate['from'] ?? '') === $currentState) {
                    $selected = $candidate;
                    break;
                }
            }
            $id = (string) ($selected['transition_id'] ?? '');
            if ($id !== '' && $this->isLocalUrl((string) $action['url'])) {
                $transitionLinks[$id] = (string) $action['url'];
            }
        }

        if ($transitions === []) {
            throw new RuntimeException('OWASYS_FSM_WORKFLOW_EMPTY');
        }

        $definition = $fsm;
        $definition['name'] = 'OWASYS · FSM';
        $definition['states'] = $states;
        $definition['transitions'] = $transitions;
        $definition['initial_state'] = $initialState;
        unset($definition['final_state']);

        $diagram = \OPUS_FSM_Diagram::renderDefinition(
            $definition,
            $currentState,
            [],
            [],
            array_intersect_key(
                $stateLabels,
                array_fill_keys($stateOrder, true)
            ),
            $transitionLabels,
            false,
            $transitionLinks,
            $initialState,
            $layout
        );
        $diagram = (new FsmDiagramGeometryNormalizer())->normalize(
            $diagram,
            0.60
        );

        return [
            'visible' => true,
            'description' => 'FSM fixe · départ '
                . $stateLabels[$initialState]
                . ' · état courant '
                . $stateLabels[$currentState]
                . ' surligné uniquement · globals finis',
            'html' => $diagram,
            'current_state' => $currentState,
            'current_label' => $stateLabels[$currentState],
            'projected_transition_count' => count($transitions),
            'revision' => self::REVISION,
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $statesById
     * @param array<string,array<string,mixed>> $menuByState
     * @return list<string>
     */
    private function orderedStates(array $statesById, array $menuByState): array
    {
        $ordered = [];
        foreach ($statesById as $id => $state) {
            if (!isset($menuByState[$id])) {
                continue;
            }
            $hint = is_array($state['diagram'] ?? null)
                ? $state['diagram']
                : [];
            $ordered[] = [
                'id' => $id,
                'rank' => (int) ($hint['rank'] ?? PHP_INT_MAX),
                'order' => (int) ($hint['order'] ?? PHP_INT_MAX),
            ];
        }
        usort(
            $ordered,
            static fn (array $left, array $right): int =>
                ($left['rank'] <=> $right['rank'])
                ?: ($left['order'] <=> $right['order'])
                ?: strcmp((string) $left['id'], (string) $right['id'])
        );
        return array_column($ordered, 'id');
    }

    /**
     * @param array<string,mixed> $transition
     * @param list<array<string,mixed>> $transitions
     * @param array<string,string> $labels
     * @param array<string,list<array{transition_id:string,from:string}>> $displayed
     */
    private function appendTransition(
        array $transition,
        string $signal,
        string $from,
        string $to,
        array &$transitions,
        array &$labels,
        array &$displayed
    ): void {
        $id = trim((string) ($transition['id'] ?? ''));
        if ($id === '') {
            throw new RuntimeException(
                'OWASYS_FSM_WORKFLOW_TRANSITION_ID_MISSING:'
                . $from . ':' . $signal . ':' . $to
            );
        }
        $transitions[] = $transition;
        $labels[$id] = $signal;
        $key = $this->signalTargetKey($signal, $to);
        $displayed[$key] ??= [];
        $displayed[$key][] = [
            'transition_id' => $id,
            'from' => $from,
        ];
    }

    /**
     * @param list<string> $sources
     * @param array<string,array{rank:int,order:int}> $layout
     */
    private function representativeGlobalSource(
        array $sources,
        string $target,
        array $layout
    ): string {
        $targetRank = (int) ($layout[$target]['rank'] ?? 0);
        usort(
            $sources,
            static function (string $left, string $right) use (
                $target,
                $targetRank,
                $layout
            ): int {
                $leftSelf = $left === $target ? 1 : 0;
                $rightSelf = $right === $target ? 1 : 0;
                if ($leftSelf !== $rightSelf) {
                    return $leftSelf <=> $rightSelf;
                }
                $leftDistance = abs(
                    (int) ($layout[$left]['rank'] ?? 0) - $targetRank
                );
                $rightDistance = abs(
                    (int) ($layout[$right]['rank'] ?? 0) - $targetRank
                );
                return ($leftDistance <=> $rightDistance)
                    ?: strcmp($left, $right);
            }
        );
        return $sources[0];
    }

    /**
     * @param array<string,array<string,mixed>> $menuByState
     * @return array<string,array{url:string,transition_id:string}>
     */
    private function currentActions(
        array $menuByState,
        string $currentState
    ): array {
        $actions = [];
        $current = $menuByState[$currentState] ?? [];

        foreach ((array) ($current['signals'] ?? []) as $signalItem) {
            if (!is_array($signalItem)
                || ($signalItem['actionable'] ?? false) !== true) {
                continue;
            }
            $this->registerAction($actions, $signalItem);
        }

        foreach ($menuByState as $item) {
            if (!is_array($item)
                || ($item['global_host'] ?? false) !== true) {
                continue;
            }
            foreach ((array) ($item['global_signals'] ?? []) as $signalItem) {
                if (!is_array($signalItem)
                    || ($signalItem['actionable'] ?? false) !== true) {
                    continue;
                }
                $this->registerAction($actions, $signalItem);
            }
        }

        return $actions;
    }

    /**
     * @param array<string,array{url:string,transition_id:string}> $actions
     * @param array<string,mixed> $signalItem
     */
    private function registerAction(array &$actions, array $signalItem): void
    {
        $signal = trim((string) ($signalItem['signal'] ?? ''));
        $target = trim((string) ($signalItem['target'] ?? ''));
        $url = trim((string) ($signalItem['url'] ?? ''));
        $transitionId = trim((string) (
            $signalItem['transition_id'] ?? ''
        ));
        if ($signal === '' || $target === '' || $transitionId === '') {
            throw new RuntimeException(
                'OWASYS_FSM_CURRENT_ACTION_INVALID:' . $transitionId
            );
        }
        $key = $this->signalTargetKey($signal, $target);
        $actions[$key] = [
            'url' => $url,
            'transition_id' => $transitionId,
        ];
    }

    private function signalTargetKey(string $signal, string $target): string
    {
        return $signal . "\0" . $target;
    }

    /** @param array<string,mixed> $transition @return list<string> */
    private function globalSources(array $transition): array
    {
        $sources = $transition['from_states'] ?? null;
        if (!is_array($sources) || $sources === []) {
            throw new RuntimeException(
                'OWASYS_FSM_GLOBAL_SOURCES_MISSING:'
                . (string) ($transition['signal'] ?? '')
            );
        }
        $result = [];
        foreach ($sources as $source) {
            if (!is_string($source) || trim($source) === '') {
                throw new RuntimeException(
                    'OWASYS_FSM_GLOBAL_SOURCE_INVALID'
                );
            }
            $result[] = trim($source);
        }
        return array_values(array_unique($result));
    }

    /**
     * @param array<string,mixed> $fsm
     * @return array<string,array<string,mixed>>
     */
    private function signalRegistry(array $fsm): array
    {
        $registry = [];
        foreach ((array) ($fsm['signals'] ?? []) as $signal) {
            if (!is_array($signal)) {
                throw new RuntimeException(
                    'OWASYS_FSM_SIGNAL_REGISTRY_ENTRY_INVALID'
                );
            }
            $id = trim((string) ($signal['id'] ?? ''));
            if ($id === '' || isset($registry[$id])) {
                throw new RuntimeException(
                    'OWASYS_FSM_SIGNAL_REGISTRY_ID_INVALID:' . $id
                );
            }
            $registry[$id] = $signal;
        }
        return $registry;
    }

    /**
     * @param array<string,mixed> $fsm
     * @param array<string,array<string,mixed>> $registry
     */
    private function assertSignalRegistryComplete(
        array $fsm,
        array $registry
    ): void {
        $referenced = [];
        foreach ((array) ($fsm['transitions'] ?? []) as $transition) {
            if (!is_array($transition)) {
                throw new RuntimeException(
                    'OWASYS_FSM_TRANSITION_ENTRY_INVALID'
                );
            }
            $signal = trim((string) ($transition['signal'] ?? ''));
            if ($signal === '' || !isset($registry[$signal])) {
                throw new RuntimeException(
                    'OWASYS_FSM_SIGNAL_UNDECLARED:' . $signal
                );
            }
            $referenced[$signal] = true;
        }
        $unused = array_diff_key($registry, $referenced);
        if ($unused !== []) {
            throw new RuntimeException(
                'OWASYS_FSM_SIGNAL_UNUSED:'
                . implode(',', array_keys($unused))
            );
        }
    }

    /**
     * @param array<string,mixed> $fsm
     * @return array<string,array<string,mixed>>
     */
    private function statesById(array $fsm): array
    {
        $states = [];
        foreach ((array) ($fsm['states'] ?? []) as $state) {
            if (!is_array($state)) {
                continue;
            }
            $id = trim((string) ($state['id'] ?? ''));
            if ($id !== '') {
                $states[$id] = $state;
            }
        }
        return $states;
    }

    private function isLocalUrl(string $url): bool
    {
        return $url !== ''
            && $url[0] === '/'
            && !str_contains($url, "\0");
    }

    /** @return array<string,mixed> */
    private function hidden(): array
    {
        return [
            'visible' => false,
            'description' => '',
            'html' => '',
            'current_state' => '',
            'current_label' => '',
            'projected_transition_count' => 0,
            'revision' => self::REVISION,
        ];
    }

    /** @return array<string,mixed> */
    private function loadFsm(): array
    {
        $loader = StructuredFileLoader::instance();
        try {
            $site = $loader->read($this->siteRoot . '/config/site.json');
        } catch (Throwable $cause) {
            throw new RuntimeException(
                'OWASYS_FSM_NATIVE_SITE_CONFIG_INVALID:'
                . $cause->getMessage(),
                0,
                $cause
            );
        }

        $navigation = is_array($site['navigation'] ?? null)
            ? $site['navigation']
            : [];
        $relative = trim(
            str_replace(
                '\\',
                '/',
                (string) ($navigation['fsm'] ?? '')
            ),
            '/'
        );
        if ($relative === '' || str_contains($relative, '..')) {
            throw new RuntimeException(
                'OWASYS_FSM_NATIVE_CONFIG_PATH_INVALID'
            );
        }

        try {
            $fsm = $loader->read($this->siteRoot . '/' . $relative);
        } catch (Throwable $cause) {
            throw new RuntimeException(
                'OWASYS_FSM_NATIVE_CONFIG_INVALID:'
                . $cause->getMessage(),
                0,
                $cause
            );
        }
        if (($fsm['contract'] ?? null) !== 'OWASYS_NAVIGATION_FSM_V1') {
            throw new RuntimeException(
                'OWASYS_FSM_NATIVE_CONTRACT_INVALID'
            );
        }
        return $fsm;
    }
}
