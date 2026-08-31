<?php
declare(strict_types=1);

use Opus\I18n\ApplicationTranslationRuntime;
use Opus\I18n\TranslationRuntimeInterface;
use Opus\I18n\TranslationException;
use Opus\File\StructuredFileLoader;
use Opus\Template\ScoreTemplateRenderer;
use Opus\Http\UrlBuilder;
use Opus\Profiler\ProfilerInterface;
use Opus\Security\Csrf\CsrfTokenManager;

final class OwasysScorePageRenderer
{
    private const FSM_I18N_REVISION = 'P117W_R45B2A4BQ';

    private readonly OwasysFsmDiagramBuilder $fsmDiagram;

    public function __construct(
        private readonly string $siteRoot,
        private readonly ?ProfilerInterface $profiler = null,
        private readonly ?string $parentSpanId = null,
        private readonly ?OwasysAuthSession $session = null,
        private readonly ?OwasysRuntimeSecurity $security = null
    ) {
        if (!$session instanceof OwasysAuthSession
            || !$security instanceof OwasysRuntimeSecurity) {
            throw new RuntimeException(
                'OWASYS_FSM_NATIVE_SECURITY_CONTEXT_MISSING'
            );
        }

        $this->fsmDiagram = new OwasysFsmDiagramBuilder(
            $siteRoot,
            $session,
            $this->profiler
        );
    }

    /** @param array<string,mixed> $data */
    public function render(string $bodyTemplate, array $data): string
    {
        $assets = is_array($data['assets'] ?? null)
            ? $data['assets']
            : [];
        $assetBase = $this->assetBase(
            (string) ($assets['score_css'] ?? '')
        );

        $assets['fsm_css'] = $assetBase
            . '/css/fsm-native.css?v=p117w-r45b2a4bz2r8b6o';
        $assets['fsm_designer_js'] = $assetBase
            . '/js/fsm-designer.js?v=p117w-r45b2a4bz2r8b6r';

        $source = is_array($data['source'] ?? null)
            ? $data['source']
            : [];
        $data['source'] = array_replace(
            ['browser_enabled' => false],
            $source
        );

        $locale = trim((string) ($data['locale']['code'] ?? ''));
        $module = trim((string) ($data['fsm']['module'] ?? ''));

        if ($locale === '' || $module === '') {
            throw new RuntimeException(
                'OWASYS_SCORE_I18N_CONTEXT_MISSING'
            );
        }

        $i18n = new ApplicationTranslationRuntime(
            $this->siteRoot . '/application',
            $module,
            $locale
        );
        $renderer = new ScoreTemplateRenderer(
            $this->siteRoot . '/application',
            $i18n,
            $this->profiler,
            $this->parentSpanId
        );

        $data['assets'] = $assets;
        $data['diagnostics']['fsm_i18n_revision'] =
            self::FSM_I18N_REVISION;

        $traceId = trim((string) getenv('OPUS_TRACE_ID'));
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($requestUri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $profilerRequested = (string) ($_GET['profiler'] ?? '') === '1';
        $identity = $this->session?->user();
        $profilerAllowed = $this->security !== null
            && $this->security->isAllowed(
                is_array($identity) ? $identity : null,
                'profiler',
                'view'
            );

        if ($profilerRequested && !$profilerAllowed) {
            throw new RuntimeException(
                'OPUS_ACL_DENIED:profiler:view'
            );
        }

        $urlBuilder = new UrlBuilder();
        $data['profiler'] = [
            'allowed' => $profilerAllowed,
            'forbidden' => !$profilerAllowed,
            'visible' => $profilerAllowed && $profilerRequested,
            'hidden' => !$profilerAllowed || !$profilerRequested,
            'trace_id' => $traceId,
            'open_url' => $urlBuilder->withQuery(
                $path,
                ['profiler' => 1]
            ),
            'close_url' => $urlBuilder->withQuery($path, []),
            'iframe_url' => $urlBuilder->withQuery(
                '/_opus/profiler/trace/' . $traceId,
                []
            ),
        ];

        $designerRequested =
            (string) ($_GET['fsm_design'] ?? '') === '1';
        $contextRegistry = new OwasysContextEfsmRegistry();
        $designerContext = $contextRegistry->forPage($data);
        $designerEfsmId = (string) ($designerContext['efsm_id'] ?? '');
        $designerHostContext = ($designerContext['host'] ?? false) === true;
        $designerApplicationId = $designerHostContext
            ? 'owasys-front'
            : strtolower(trim((string) (
                $data['current_app']['id'] ?? ''
            )));
        $designerAclResource = $designerHostContext ? 'owasys' : 'fsm';
        $designerAclAction = $designerHostContext ? 'modify' : 'update';
        $designerHasContext = $designerEfsmId !== '';
        $designerHasApplication = preg_match(
            '/^[a-z][a-z0-9-]{0,63}$/D',
            $designerApplicationId
        ) === 1;
        $designerAllowed = $designerHasApplication
            && $designerHasContext
            && $this->security !== null
            && $this->security->isAllowed(
                is_array($identity) ? $identity : null,
                $designerAclResource,
                $designerAclAction
            );

        if ($designerRequested && !$designerHasApplication) {
            throw new RuntimeException(
                'OWASYS_FSM_DESIGNER_APPLICATION_REQUIRED'
            );
        }
        if ($designerRequested && !$designerHasContext) {
            throw new RuntimeException(
                'OWASYS_FSM_DESIGNER_CONTEXT_EFSM_REQUIRED'
            );
        }
        if ($designerRequested && !$designerAllowed) {
            throw new RuntimeException(
                'OPUS_ACL_DENIED:' . $designerAclResource
                . ':' . $designerAclAction
            );
        }

        $designerOpenQuery = ['fsm_design' => 1];
        $designerCloseQuery = [];
        if ($profilerRequested && $profilerAllowed) {
            $designerOpenQuery['profiler'] = 1;
            $designerCloseQuery['profiler'] = 1;
        }

        $data['fsm_designer'] = [
            'allowed' => $designerAllowed,
            'active' => $designerAllowed && $designerRequested,
            'mode' => $designerAllowed && $designerRequested
                ? 'design'
                : 'view',
            'open_url' => $urlBuilder->withQuery(
                $path,
                $designerOpenQuery
            ),
            'close_url' => $urlBuilder->withQuery(
                $path,
                $designerCloseQuery
            ),
            'action_url' => $urlBuilder->withQuery(
                $path,
                $designerOpenQuery
            ),
            'csrf_token' => $designerAllowed && $designerRequested
                ? (new CsrfTokenManager())->issue(
                    'owasys.fsm.designer'
                )
                : '',
            'application_id' => $designerApplicationId,
            'efsm_id' => $designerEfsmId,
            'revision' => 'P117W_R45B2A4BZ2R8B6R',
            'labels' => $designerAllowed
                ? $this->designerLabels($locale)
                : [],
        ];

        if (($data['fsm_designer']['active'] ?? false) === true) {
            $this->profiler?->event(
                'fsm',
                'designer.opened',
                [
                    'path' => $path,
                    'state' => (string) ($data['fsm']['state'] ?? ''),
                    'mode' => 'design',
                ],
                'success',
                null,
                $this->parentSpanId
            );
        }

        /*
         * State-owned text is resolved from the module that owns the state.
         * The page/body renderer deliberately keeps the active page module
         * runtime above. This preserves SCORE's active-module contract while
         * making Menu = FSM genuinely cross-module.
         */
        $data = $this->normalizeI18nViewData($data);

        $data['fsm_diagram'] = $this->fsmDiagram->build($data);

        $bodyHtml = $renderer->render($bodyTemplate, $data);
        $bodyPlaceholder = 'OPUS_BODY_FRAGMENT_'
            . strtoupper(bin2hex(random_bytes(24)));
        $data['body'] = ['html' => $bodyPlaceholder];

        $layoutHtml = $renderer->render(
            'default/layouts/layout.score',
            $data
        );

        if (substr_count($layoutHtml, $bodyPlaceholder) !== 1) {
            throw new RuntimeException(
                'OWASYS_SCORE_BODY_FRAGMENT_PLACEHOLDER_INVALID'
            );
        }

        if (!headers_sent()) {
            header(
                'X-Owasys-Fsm-I18n-Revision: '
                . self::FSM_I18N_REVISION
            );
        }

        return str_replace(
            $bodyPlaceholder,
            $bodyHtml,
            $layoutHtml
        );
    }

    /** @param array<string,mixed> $data */
    public function emit(string $bodyTemplate, array $data): void
    {
        $html = $this->render($bodyTemplate, $data);
        $stream = fopen('php://output', 'wb');

        if ($stream === false) {
            throw new RuntimeException(
                'OWASYS_SCORE_OUTPUT_STREAM_OPEN_FAILED'
            );
        }

        try {
            $written = fwrite($stream, $html);

            if ($written === false || $written !== strlen($html)) {
                throw new RuntimeException(
                    'OWASYS_SCORE_OUTPUT_WRITE_FAILED'
                );
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * Menu = FSM I18n contract:
     * - active state title/summary => active state module;
     * - each state menu label => that state module;
     * - each signal target label => target state module;
     * - no global duplication and no silent fallback.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function normalizeI18nViewData(array $data): array
    {
        $fsm = $this->loadFsm();
        $stateId = trim((string) ($data['fsm']['state'] ?? ''));
        $locale = trim((string) ($data['locale']['code'] ?? ''));

        if ($stateId === '' || $locale === '') {
            throw new RuntimeException(
                'OWASYS_SCORE_FSM_I18N_CONTEXT_MISSING'
            );
        }

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

        $active = $states[$stateId] ?? null;

        if (!is_array($active)) {
            throw new RuntimeException(
                'OWASYS_SCORE_FSM_ACTIVE_STATE_UNKNOWN:'
                . $stateId
            );
        }

        /** @var array<string,ApplicationTranslationRuntime> $runtimes */
        $runtimes = [];

        $activeModule = $this->stateModule($active, $stateId);
        $activeRuntime = $this->translationRuntime(
            $runtimes,
            $activeModule,
            $locale
        );

        $titleKey = trim((string) (
            $active['title_key']
            ?? ('menu.' . $activeModule)
        ));
        $summaryKey = trim((string) (
            $active['summary_key']
            ?? 'state.default.summary'
        ));

        $data['page']['title'] = $this->translateStateText(
            $activeRuntime,
            $stateId,
            $activeModule,
            $locale,
            $titleKey,
            'title'
        );
        $data['page']['summary'] = $this->translateStateText(
            $activeRuntime,
            $stateId,
            $activeModule,
            $locale,
            $summaryKey,
            'summary'
        );

        if (!is_array($data['navigation'] ?? null)) {
            return $data;
        }

        $menuRuntime = $this->translationRuntime(
            $runtimes,
            'menu',
            $locale
        );

        foreach ($data['navigation'] as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = trim((string) ($item['id'] ?? ''));
            $state = $states[$id] ?? null;

            if (!is_array($state)) {
                throw new RuntimeException(
                    'OWASYS_SCORE_NAVIGATION_STATE_UNKNOWN:' . $id
                );
            }

            $navigation = is_array($state['navigation'] ?? null)
                ? $state['navigation']
                : [];
            $stateModule = $this->stateModule($state, $id);
            $entryState = trim((string) ($state['type'] ?? '')) === 'entry';

            if ($entryState) {
                /*
                 * Entry states are technical FSM control states, not human
                 * navigation resources. Their canonical ID is deliberately
                 * not an I18n message key. Keep it available to diagnostic
                 * consumers without forcing a translation that cannot exist.
                 */
                $data['navigation'][$index]['label'] = $id;
            } else {
                $labelKey = trim((string) (
                    $navigation['label']
                    ?? $state['title_key']
                    ?? ('menu.' . $stateModule)
                ));
                $stateRuntime = $this->translationRuntime(
                    $runtimes,
                    $stateModule,
                    $locale
                );
                $data['navigation'][$index]['label'] =
                    $this->translateStateText(
                        $stateRuntime,
                        $id,
                        $stateModule,
                        $locale,
                        $labelKey,
                        'menu'
                    );
            }

            foreach ((array) (
                $data['navigation'][$index]['operations'] ?? []
            ) as $operationIndex => $operation) {
                if (!is_array($operation)) {
                    continue;
                }
                $operationKey = trim((string) (
                    $operation['label_key'] ?? ''
                ));
                if ($operationKey === '') {
                    throw new RuntimeException(
                        'OWASYS_SCORE_EFSM_MENU_OPERATION_I18N_KEY_MISSING:'
                        . (string) ($operation['signal'] ?? '')
                    );
                }
                $data['navigation'][$index]['operations'][$operationIndex]
                    ['label'] = $this->translateStateText(
                        $menuRuntime,
                        (string) ($operation['signal'] ?? ''),
                        'menu',
                        $locale,
                        $operationKey,
                        'operation-menu'
                    );
            }

            foreach ((array) (
                $data['navigation'][$index]['signals'] ?? []
            ) as $signalIndex => $signal) {
                if (!is_array($signal)) {
                    continue;
                }

                $targetId = trim((string) (
                    $signal['target'] ?? ''
                ));
                $targetState = $states[$targetId] ?? null;

                if (!is_array($targetState)) {
                    throw new RuntimeException(
                        'OWASYS_SCORE_FSM_SIGNAL_TARGET_UNKNOWN:'
                        . $targetId
                    );
                }

                $targetNavigation = is_array(
                    $targetState['navigation'] ?? null
                )
                    ? $targetState['navigation']
                    : [];
                $targetModule = $this->stateModule(
                    $targetState,
                    $targetId
                );
                $targetLabelKey = trim((string) (
                    $targetNavigation['label']
                    ?? $targetState['title_key']
                    ?? ('menu.' . $targetModule)
                ));

                $targetRuntime = $this->translationRuntime(
                    $runtimes,
                    $targetModule,
                    $locale
                );

                $data['navigation'][$index]['signals'][$signalIndex]
                    ['target_label'] = $this->translateStateText(
                        $targetRuntime,
                        $targetId,
                        $targetModule,
                        $locale,
                        $targetLabelKey,
                        'signal-target'
                    );
            }
        }

        return $data;
    }

    /**
     * @param array<string,mixed> $state
     */
    private function stateModule(array $state, string $stateId): string
    {
        $module = trim((string) (
            $state['module'] ?? $stateId
        ));

        if ($module === '') {
            throw new RuntimeException(
                'OWASYS_SCORE_FSM_STATE_MODULE_MISSING:'
                . $stateId
            );
        }

        return $module;
    }

    /**
     * @param array<string,ApplicationTranslationRuntime> $runtimes
     */
    private function translationRuntime(
        array &$runtimes,
        string $module,
        string $locale
    ): ApplicationTranslationRuntime {
        if (!isset($runtimes[$module])) {
            $runtimes[$module] = new ApplicationTranslationRuntime(
                $this->siteRoot . '/application',
                $module,
                $locale
            );
        }

        return $runtimes[$module];
    }

    private function translateStateText(
        TranslationRuntimeInterface $runtime,
        string $stateId,
        string $module,
        string $locale,
        string $key,
        string $role
    ): string {
        if ($key === '') {
            throw new RuntimeException(
                'OWASYS_SCORE_FSM_I18N_KEY_EMPTY:'
                . $stateId . ':' . $role
            );
        }

        try {
            return $runtime->translate($key);
        } catch (TranslationException $cause) {
            /*
             * Do not silently fall back. Preserve the exact state/module/
             * locale/key in the exception chain for logs and diagnostics.
             */
            throw new RuntimeException(
                'OWASYS_SCORE_FSM_I18N_MESSAGE_MISSING:'
                . $stateId
                . ':' . $module
                . ':' . $locale
                . ':' . $role
                . ':' . $key,
                0,
                $cause
            );
        }
    }

    /** @return array<string,string> */
    private function designerLabels(string $locale): array
    {
        $runtime = new ApplicationTranslationRuntime(
            $this->siteRoot . '/application',
            'default',
            $locale
        );
        $keys = [
            'designer',
            'design',
            'view',
            'select',
            'state',
            'transition',
            'condition',
            'create',
            'edit',
            'rename',
            'delete',
            'validate',
            'publish',
            'inspector',
            'no_selection',
            'readonly',
        ];
        $labels = [];
        foreach ($keys as $key) {
            $messageKey = 'fsm_designer.' . $key;
            try {
                $labels[$key] = $runtime->translate($messageKey);
            } catch (TranslationException $cause) {
                throw new RuntimeException(
                    'OWASYS_FSM_DESIGNER_I18N_MESSAGE_MISSING:'
                    . $locale . ':' . $messageKey,
                    0,
                    $cause
                );
            }
        }
        return $labels;
    }
    /** @return array<string,mixed> */
    private function loadFsm(): array
    {
        $loader = StructuredFileLoader::instance();
        $siteConfigFile = $this->siteRoot . '/config/site.json';

        try {
            $siteConfig = $loader->read($siteConfigFile);
        } catch (Throwable $cause) {
            throw new RuntimeException(
                'OWASYS_SCORE_I18N_SITE_CONFIG_INVALID:'
                . $cause->getMessage(),
                0,
                $cause
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

        if ($relative === '' || str_contains($relative, '..')) {
            throw new RuntimeException(
                'OWASYS_SCORE_I18N_FSM_PATH_INVALID'
            );
        }

        $fsmFile = $this->siteRoot . '/' . $relative;

        try {
            return $loader->read($fsmFile);
        } catch (Throwable $cause) {
            throw new RuntimeException(
                'OWASYS_SCORE_I18N_FSM_CONFIG_INVALID:'
                . $cause->getMessage(),
                0,
                $cause
            );
        }
    }

    private function assetBase(string $scoreCss): string
    {
        $normalized = str_replace('\\', '/', trim($scoreCss));
        $suffix = '/css/owasys.css';

        if (
            $normalized === ''
            || !str_ends_with($normalized, $suffix)
        ) {
            throw new RuntimeException(
                'OWASYS_SCORE_ASSET_BASE_INVALID'
            );
        }

        return substr(
            $normalized,
            0,
            -strlen($suffix)
        );
    }
}
