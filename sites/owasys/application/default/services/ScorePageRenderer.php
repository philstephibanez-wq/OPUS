<?php
declare(strict_types=1);

use Opus\I18n\ApplicationTranslationRuntime;
use Opus\I18n\TranslationRuntimeInterface;
use Opus\Template\ScoreTemplateRenderer;

final class OwasysScorePageRenderer
{
    private readonly OwasysFsmMermaidBuilder $fsmMermaid;

    public function __construct(private readonly string $siteRoot)
    {
        $this->fsmMermaid = new OwasysFsmMermaidBuilder($siteRoot);
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
            . '/css/fsm-mermaid.css?v=p117h';
        $assets['opus_mermaid_js'] = $assetBase
            . '/opus/mermaid/opus-mermaid.js';
        $assets['fsm_mermaid_js'] = $assetBase
            . '/js/fsm-mermaid.js?v=p117h';

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
            $i18n
        );

        $data['assets'] = $assets;
        $data = $this->normalizeI18nViewData($data, $i18n);
        $data['fsm_diagram'] = $this->fsmMermaid->build($data);
        $data['body'] = [
            'html' => $renderer->render($bodyTemplate, $data),
        ];

        return $renderer->render(
            'default/templates/layout.score',
            $data
        );
    }

    /**
     * Transitional normalization for ViewModel fields still produced by the
     * pre-P117I controller adapter. SCORE texts and canonical page/navigation
     * fields are resolved again through the strict module-aware runtime.
     *
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
            }
        }

        $data['labels']['navigation'] = $i18n->translate(
            'navigation.main'
        );

        if (is_array($data['entries'] ?? null)) {
            foreach ($data['entries'] as $index => $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $data['entries'][$index]['button_label'] = $i18n->translate(
                    ($entry['current'] ?? false) === true
                        ? 'registry.current_application'
                        : 'registry.work_on_this_app'
                );
            }
        }

        return $data;
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
                'OWASYS_SCORE_I18N_SITE_CONFIG_INVALID'
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
                'OWASYS_SCORE_I18N_FSM_PATH_INVALID'
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
                'OWASYS_SCORE_I18N_FSM_CONFIG_INVALID'
            );
        }

        return $fsm;
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
