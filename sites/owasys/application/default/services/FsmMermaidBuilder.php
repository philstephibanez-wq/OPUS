<?php
declare(strict_types=1);

use Opus\Componants\Diagram\MermaidDiagram;

final class OwasysFsmMermaidBuilder
{
    public function __construct(private readonly string $siteRoot)
    {
    }

    /**
     * @param array<string,mixed> $pageData
     * @return array{
     *   visible:bool,
     *   description:string,
     *   html:string,
     *   routes_json:string
     * }
     */
    public function build(array $pageData): array
    {
        $identity = is_array($pageData['identity'] ?? null)
            ? $pageData['identity']
            : [];
        $stateId = trim(
            (string) ($pageData['fsm']['state'] ?? '')
        );

        if (
            ($identity['authenticated'] ?? false) !== true
            || in_array($stateId, ['login', 'account'], true)
        ) {
            return $this->hidden();
        }

        $fsm = $this->loadFsm();
        $states = $this->statesById($fsm);
        $navigation = is_array($pageData['navigation'] ?? null)
            ? $pageData['navigation']
            : [];

        $nodes = [];

        foreach ($navigation as $item) {
            if (
                !is_array($item)
                || ($item['allowed'] ?? false) !== true
            ) {
                continue;
            }

            $id = trim((string) ($item['id'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));

            if (
                $id === ''
                || $url === ''
                || !isset($states[$id])
            ) {
                continue;
            }

            $nodes[$id] = [
                'label' => $this->label(
                    (string) ($item['label'] ?? $id)
                ),
                'url' => $url,
                'state' => $states[$id],
                'node_class' => $this->nodeClass($id),
            ];
        }

        if ($nodes === []) {
            return $this->hidden();
        }

        $diagram = is_array($fsm['diagram'] ?? null)
            ? $fsm['diagram']
            : [];
        $direction = strtoupper(
            (string) ($diagram['direction'] ?? 'LR')
        );

        if (!in_array($direction, ['LR', 'RL', 'TB', 'BT'], true)) {
            throw new RuntimeException(
                'OWASYS_FSM_MERMAID_DIRECTION_INVALID'
            );
        }

        $currentApp = is_array($pageData['current_app'] ?? null)
            ? $pageData['current_app']
            : [];
        $lines = ['flowchart ' . $direction];

        foreach ($nodes as $id => $node) {
            $state = $node['state'];
            $class = $id === $stateId
                ? 'active'
                : (
                    ($state['requires_current_app'] ?? false) === true
                    ? 'work'
                    : 'primary'
                );

            $label = (string) $node['label'];

            if (
                $id === 'structure'
                && ($currentApp['present'] ?? false) === true
                && trim((string) ($currentApp['name'] ?? '')) !== ''
            ) {
                $label .= '<br/>'
                    . $this->label(
                        (string) $currentApp['name']
                    );
            }

            $lines[] = '    ' . $id
                . '["' . $label . '"]:::'
                . $class;
        }

        foreach ($nodes as $id => $node) {
            $lines[] = '    class ' . $id
                . ' ' . $node['node_class'];
        }

        foreach ((array) ($fsm['transitions'] ?? []) as $transition) {
            if (
                !is_array($transition)
                || ($transition['visual'] ?? false) !== true
            ) {
                continue;
            }

            $from = trim((string) (
                $transition['visual_from']
                ?? $transition['from']
                ?? ''
            ));
            $to = trim((string) ($transition['to'] ?? ''));

            if (!isset($nodes[$from], $nodes[$to])) {
                continue;
            }

            $event = $this->label(
                str_replace(
                    '_',
                    ' ',
                    (string) ($transition['event'] ?? 'event')
                )
            );

            $lines[] = '    ' . $from
                . ' -->|' . $event . '| '
                . $to;
        }

        $lines[] = '    linkStyle default stroke:#6ce3ff,stroke-width:2px';
        $lines[] = '    classDef primary fill:#123456,stroke:#6ce3ff,color:#f6f8ff,stroke-width:2px';
        $lines[] = '    classDef active fill:#164e63,stroke:#4ade80,color:#f6f8ff,stroke-width:4px';
        $lines[] = '    classDef work fill:#101c2f,stroke:#94aad8,color:#f6f8ff,stroke-width:1px';

        $routes = [];

        foreach ($nodes as $id => $node) {
            $routes[$id] = [
                'url' => (string) $node['url'],
                'node_class' => (string) $node['node_class'],
            ];
        }

        return [
            'visible' => true,
            'description' => (string) (
                $diagram['contract']
                ?? $fsm['contract']
                ?? 'OWASYS_NAVIGATION_FSM_V1'
            ),
            'html' => (
                new MermaidDiagram(
                    'owasys-fsm-diagram',
                    implode("\n", $lines)
                )
            )->render(),
            'routes_json' => json_encode(
                $routes,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
                | JSON_THROW_ON_ERROR
            ),
        ];
    }

    /**
     * @return array{
     *   visible:bool,
     *   description:string,
     *   html:string,
     *   routes_json:string
     * }
     */
    private function hidden(): array
    {
        return [
            'visible' => false,
            'description' => '',
            'html' => '',
            'routes_json' => '{}',
        ];
    }

    /** @return array<string,mixed> */
    private function loadFsm(): array
    {
        $siteConfigFile = $this->siteRoot . '/config/site.json';
        $siteConfig = is_file($siteConfigFile)
            ? json_decode(
                (string) file_get_contents($siteConfigFile),
                true,
                512,
                JSON_THROW_ON_ERROR
            )
            : null;

        if (!is_array($siteConfig)) {
            throw new RuntimeException(
                'OWASYS_FSM_MERMAID_SITE_CONFIG_INVALID'
            );
        }

        $navigation = is_array($siteConfig['navigation'] ?? null)
            ? $siteConfig['navigation']
            : [];
        $relative = trim(
            str_replace(
                '\\',
                '/',
                (string) ($navigation['fsm'] ?? '')
            ),
            '/'
        );

        if (
            $relative === ''
            || str_contains($relative, '..')
        ) {
            throw new RuntimeException(
                'OWASYS_FSM_MERMAID_CONFIG_PATH_INVALID'
            );
        }

        $fsmFile = $this->siteRoot . '/' . $relative;
        $fsm = is_file($fsmFile)
            ? json_decode(
                (string) file_get_contents($fsmFile),
                true,
                512,
                JSON_THROW_ON_ERROR
            )
            : null;

        if (!is_array($fsm)) {
            throw new RuntimeException(
                'OWASYS_FSM_MERMAID_CONFIG_INVALID'
            );
        }

        return $fsm;
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

    private function nodeClass(string $stateId): string
    {
        if (
            preg_match('/^[a-z][a-z0-9_-]*$/', $stateId) !== 1
        ) {
            throw new RuntimeException(
                'OWASYS_FSM_MERMAID_STATE_ID_INVALID:'
                . $stateId
            );
        }

        return 'owasys-fsm-state-' . $stateId;
    }

    private function label(string $value): string
    {
        $clean = strip_tags($value);
        $clean = str_replace(
            [
                "\r",
                "\n",
                '"',
                '`',
                '{',
                '}',
                '[',
                ']',
                '\\',
            ],
            ' ',
            $clean
        );
        $clean = preg_replace('/\s+/u', ' ', $clean);
        $clean = trim(is_string($clean) ? $clean : '');

        return $clean === '' ? 'unknown' : $clean;
    }
}
