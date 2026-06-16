<?php

declare(strict_types=1);

namespace Opus\Template;

use Opus\Http\PublicResponse;
use Opus\PublicSite\PublicPageModel;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Render a tiny ScoreTemplate-compatible public page for the P117A2 smoke.
 *
 * Responsibility:
 *   Convert a public page model into a public response.
 */
final class SimpleScoreTemplateRenderer
{
    public function render(PublicPageModel $model): PublicResponse
    {
        $body = '<!doctype html><html><head><meta charset="UTF-8"><title>'
            . htmlspecialchars($model->title(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</title></head><body><h1>'
            . htmlspecialchars($model->title(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</h1><p>'
            . htmlspecialchars($model->content(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</p></body></html>';

        return new PublicResponse(200, $body, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
