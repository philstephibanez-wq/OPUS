<?php
declare(strict_types=1);

use Opus\File\StructuredFileLoader;

final class OwasysFsmDiagramBuilder
{
    public function __construct(
        private readonly string $siteRoot,
        private readonly OwasysAuthSession $session,
        private readonly OwasysRuntimeSecurity $security
    ) {
    }

    /**
     * @param array<string,mixed> $pageData
     * @return array{visible:bool,description:string,html:string}
     */
    public function build(array $pageData): array
    {
        $identityView = is_array($pageData['identity'] ?? null)
            ? $pageData['identity']
            : [];
        $currentState = trim((string) ($pageData['fsm']['state'] ?? ''));

        if (($identityView['authenticated'] ?? false) !== true
            || in_array($currentState, ['login', 'account'], true)) {
            return $this->hidden();
        }

        $fsm = $this->loadFsm();
        $identity = $this->session->user();
        if (!is_array($identity)) {
            throw new RuntimeException(
                'OWASYS_FSM_NATIVE_IDENTITY_REQUIRED'
            );
        }

        $states = [];
        $allowed = [];

        foreach ((array) ($fsm['states'] ?? []) as $state) {
            if (!is_array($state)) {
                continue;
            }

            $id = trim((string) ($state['id'] ?? ''));
            $module = trim((string) ($state['module'] ?? $id));
            $requiresAuth = ($state['requires_auth'] ?? false) === true;

            if ($id === '' || $module === '') {
                throw new RuntimeException(
                    'OWASYS_FSM_NATIVE_STATE_INVALID'
                );
            }

            $stateAllowed = !$requiresAuth
                || $this->security->isAllowed(
                    $identity,
                    $module,
                    'open'
                );

            if (!$stateAllowed) {
                continue;
            }

            $states[] = $state;
            $allowed[$id] = true;
        }

        if ($states === []) {
            return $this->hidden();
        }

        $transitions = [];
        foreach ((array) ($fsm['transitions'] ?? []) as $transition) {
            if (!is_array($transition)) {
                continue;
            }

            $from = trim((string) (
                $transition['from']
                ?? $transition['state']
                ?? ''
            ));
            $to = trim((string) (
                $transition['next_state']
                ?? $transition['nextState']
                ?? ''
            ));

            if ($to === '' || !isset($allowed[$to])) {
                continue;
            }
            if ($from !== '*' && !isset($allowed[$from])) {
                continue;
            }

            // Native OPUS projection deliberately preserves the real
            // transition. Legacy visual/visual_from metadata is ignored.
            unset($transition['visual'], $transition['visual_from']);
            $transitions[] = $transition;
        }

        $definition = $fsm;
        unset($definition['diagram']);
        $definition['states'] = $states;
        $definition['transitions'] = $transitions;

        $initial = trim((string) ($definition['initial_state'] ?? ''));
        if ($initial === '' || !isset($allowed[$initial])) {
            $definition['initial_state'] = isset($allowed[$currentState])
                ? $currentState
                : (string) ($states[0]['id'] ?? '');
        }

        $final = trim((string) ($definition['final_state'] ?? ''));
        if ($final !== '' && !isset($allowed[$final])) {
            unset($definition['final_state']);
        }

        $stateLinks = $this->stateLinks($pageData, $allowed);
        $diagram = \OPUS_FSM_Diagram::renderDefinition(
            $definition,
            isset($allowed[$currentState]) ? $currentState : '',
            [],
            $stateLinks
        );

        return [
            'visible' => true,
            'description' => 'OPUS_FSM_Diagram · '
                . (string) ($fsm['contract'] ?? 'FSM'),
            'html' => $diagram,
        ];
    }

    /**
     * @param array<string,mixed> $pageData
     * @param array<string,bool> $allowed
     * @return array<string,string>
     */
    private function stateLinks(array $pageData, array $allowed): array
    {
        $links = [];

        foreach ((array) ($pageData['navigation'] ?? []) as $item) {
            if (!is_array($item)
                || ($item['allowed'] ?? false) !== true
                || ($item['available'] ?? false) !== true) {
                continue;
            }

            $id = trim((string) ($item['id'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));

            if ($id !== ''
                && isset($allowed[$id])
                && $this->isLocalUrl($url)) {
                $links[$id] = $url;
            }
        }

        $urls = is_array($pageData['urls'] ?? null)
            ? $pageData['urls']
            : [];
        foreach ([
            'login' => 'login',
            'account' => 'account',
            'registry' => 'applications',
        ] as $state => $urlKey) {
            $url = trim((string) ($urls[$urlKey] ?? ''));
            if (isset($allowed[$state]) && $this->isLocalUrl($url)) {
                $links[$state] = $url;
            }
        }

        return $links;
    }

    private function isLocalUrl(string $url): bool
    {
        return $url !== ''
            && $url[0] === '/'
            && !str_contains($url, "\0");
    }

    /**
     * @return array{visible:bool,description:string,html:string}
     */
    private function hidden(): array
    {
        return [
            'visible' => false,
            'description' => '',
            'html' => '',
        ];
    }

    /** @return array<string,mixed> */
    private function loadFsm(): array
    {
        $loader = StructuredFileLoader::instance();
        try {
            $site = $loader->read(
                $this->siteRoot . '/config/site.json'
            );
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
