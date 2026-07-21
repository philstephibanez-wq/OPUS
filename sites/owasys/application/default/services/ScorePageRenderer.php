<?php
declare(strict_types=1);

use Opus\Template\ScoreTemplateRenderer;

final class OwasysScorePageRenderer
{
    private readonly ScoreTemplateRenderer $renderer;

    public function __construct(string $siteRoot)
    {
        $this->renderer = new ScoreTemplateRenderer($siteRoot . '/application');
    }

    /** @param array<string,mixed> $data */
    public function render(string $bodyTemplate, array $data): string
    {
        $data['body'] = [
            'html' => $this->renderer->render($bodyTemplate, $data),
        ];

        return $this->renderer->render('default/templates/layout.score', $data);
    }
}
