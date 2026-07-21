<?php
declare(strict_types=1);

use Opus\Template\ScoreTemplateRenderer;

final class OwasysScorePageRenderer
{
    private readonly ScoreTemplateRenderer $renderer;
    private readonly OwasysFsmMermaidBuilder $fsmMermaid;

    public function __construct(private readonly string $siteRoot)
    {
        $this->renderer = new ScoreTemplateRenderer(
            $siteRoot . '/application'
        );
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

        $assets['fsm_css'] = $assetBase . '/css/fsm-mermaid.css';
        $assets['opus_mermaid_js'] = $assetBase
            . '/opus/mermaid/opus-mermaid.js';
        $assets['fsm_mermaid_js'] = $assetBase
            . '/js/fsm-mermaid.js?v=p117f';

        $data['assets'] = $assets;
        $data['fsm_diagram'] = $this->fsmMermaid->build($data);
        $data['body'] = [
            'html' => $this->renderer->render($bodyTemplate, $data),
        ];

        return $this->renderer->render(
            'default/templates/layout.score',
            $data
        );
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
