<?php
declare(strict_types=1);

use Opus\I18n\ApplicationTranslationRuntime;
use Opus\I18n\TranslationRuntimeInterface;
use Opus\File\StructuredFileLoader;
use Opus\Template\ScoreTemplateRenderer;
use Opus\Http\UrlBuilder;
use Opus\Profiler\ProfilerInterface;

final class OwasysScorePageRenderer
{
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
            $session
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
            . '/css/fsm-native.css?v=p117w-r45b2a4m';

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
            'open_url' => $urlBuilder->withQuery($path, ['profiler' => 1]),
            'close_url' => $urlBuilder->withQuery($path, []),
            'iframe_url' => $urlBuilder->withQuery(
                '/_opus/profiler/trace/' . $traceId,
                []
            ),
        ];
        $data = $this->normalizeI18nViewData($data, $i18n);
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

        return str_replace($bodyPlaceholder, $bodyHtml, $layoutHtml);
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
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function normalizeI18nViewData(
        array $data,
        TranslationRuntimeInterface $i18n
    ): array {
        $fsm = $this->loadFsm();
        $stateId = trim((string) ($data['fsm']['state'] ?? ''));
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

        if (is_array($active)) {
            $titleKey = trim((string) (
                $active['title_key']
                ?? ('menu.' . (string) ($active['module'] ?? $stateId))
            ));
            $summaryKey = trim((string) (
                $active['summary_key']
                ?? 'state.default.summary'
            ));

            $data['page']['title'] = $i18n->translate($titleKey);
            $data['page']['summary'] = $i18n->translate($summaryKey);
        }

        if (is_array($data['navigation'] ?? null)) {
            foreach ($data['navigation'] as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }

                $id = trim((string) ($item['id'] ?? ''));
                $state = $states[$id] ?? null;

                if (!is_array($state)) {
                    continue;
                }

                $navigation = is_array($state['navigation'] ?? null)
                    ? $state['navigation']
                    : [];
                $labelKey = trim((string) (
                    $navigation['label']
                    ?? $state['title_key']
                    ?? ('menu.' . (string) ($state['module'] ?? $id))
                ));

                $data['navigation'][$index]['label'] = $i18n->translate(
                    $labelKey
                );

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
                    $targetLabelKey = trim((string) (
                        $targetNavigation['label']
                        ?? $targetState['title_key']
                        ?? (
                            'menu.'
                            . (string) (
                                $targetState['module'] ?? $targetId
                            )
                        )
                    ));

                    $data['navigation'][$index]['signals'][$signalIndex]
                        ['target_label'] = $i18n->translate(
                            $targetLabelKey
                        );
                }
            }
        }

        return $data;
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
                'OWASYS_SCORE_I18N_SITE_CONFIG_INVALID:' . $cause->getMessage(),
                0,
                $cause
            );
        }

        $navigation = is_array($siteConfig['navigation'] ?? null)
            ? $siteConfig['navigation']
            : [];
        $relative = trim(
            str_replace('\\', '/', (string) ($navigation['fsm'] ?? '')),
            '/'
        );
        if ($relative === '' || str_contains($relative, '..')) {
            throw new RuntimeException('OWASYS_SCORE_I18N_FSM_PATH_INVALID');
        }

        $fsmFile = $this->siteRoot . '/' . $relative;
        try {
            return $loader->read($fsmFile);
        } catch (Throwable $cause) {
            throw new RuntimeException(
                'OWASYS_SCORE_I18N_FSM_CONFIG_INVALID:' . $cause->getMessage(),
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

        return substr($normalized, 0, -strlen($suffix));
    }
}
