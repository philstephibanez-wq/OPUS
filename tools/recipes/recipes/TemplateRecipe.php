<?php

declare(strict_types=1);

namespace Opus\Recipe\Recipes;

use Opus\Recipe\RecipeContext;
use Opus\Recipe\RecipeInterface;
use Opus\Template\ScoreTemplateRenderer;

/** PUBLIC RECIPE: validate native ScoreTemplate without legacy adapters. */
final class TemplateRecipe implements RecipeInterface
{
    public function name(): string { return 'template'; }

    public function run(RecipeContext $context): array
    {
        $root = $context->path('var/recipes/score_template_' . $context->runId());
        if (!is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
            $context->assert(false, 'OPUS_SCORE_TEMPLATE_RECIPE_TMP_CREATE_FAILED', $root);
        }

        file_put_contents($root . '/row.score', '<li>{{ loop.index }}:{{ item }}</li>');
        file_put_contents($root . '/main.score', '<h1>{{ title|upper }}</h1>[[ if: enabled ]]<b>ON</b>[[ else ]]<b>OFF</b>[[ endif ]]<ul>[[ foreach: items as item ]][[ include:row.score ]][[ endforeach ]]</ul>');

        $renderer = new ScoreTemplateRenderer($root);
        $body = $renderer->render('main.score', [
            'title' => 'score',
            'enabled' => true,
            'items' => ['a', 'b'],
        ]);

        $context->assert($body === '<h1>SCORE</h1><b>ON</b><ul><li>1:a</li><li>2:b</li></ul>', 'OPUS_SCORE_TEMPLATE_RECIPE_RENDER_FAILED', $body);
        $context->assert(interface_exists(\Opus\Template\TemplateRendererInterface::class), 'OPUS_TEMPLATE_RENDERER_INTERFACE_NOT_LOADABLE');
        $context->assert(class_exists(\Opus\Template\ScoreTemplateRenderer::class), 'OPUS_SCORE_TEMPLATE_RENDERER_NOT_LOADABLE');
        $context->assert(!interface_exists(\Opus\Template\Adapter::class), 'OPUS_LEGACY_TEMPLATE_ADAPTER_MUST_NOT_EXIST');

        @unlink($root . '/row.score');
        @unlink($root . '/main.score');
        @rmdir($root);

        $json = (new \Opus\Renderer\JsonRenderer())->render(['ok' => true]);
        $context->assert(str_contains($json->body, 'true'), 'OPUS_TEMPLATE_JSON_RENDER_FAILED');

        return ['OPUS_SCORE_TEMPLATE_OK'];
    }
}
