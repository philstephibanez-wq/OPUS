<?php

declare(strict_types=1);

namespace Opus\Template;

use ASAP\Contract\ContractException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/*
 * OPUS_REFBOOK:
 *   domain: TEMPLATE
 *   role: Class TwigTemplateRenderer belongs to the TEMPLATE Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the TEMPLATE domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - template-overview
 *   diagrams:
 *     - template-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC RENDERER
 *
 * Role:
 *   Render Opus view data with Twig.
 *
 * Responsibility:
 *   Own the Twig boundary for one application template root.
 *
 * Contract:
 *   Twig dependency is official and mandatory for this renderer. If Twig is not
 *   installed, rendering fails explicitly with `OPUS_TWIG_VENDOR_MISSING`.
 *
 * Since:
 *   P112D1
 */
final class TwigTemplateRenderer implements TemplateRendererInterface
{
    private Environment $twig;

    public function __construct(string $templateRoot, string $cacheRoot)
    {
        if (!is_dir($templateRoot)) {
            throw ContractException::because('OPUS_TWIG_TEMPLATE_ROOT_MISSING', $templateRoot);
        }

        if (!class_exists(Environment::class) || !class_exists(FilesystemLoader::class)) {
            throw ContractException::because('OPUS_TWIG_VENDOR_MISSING', 'Run composer install in the application root.');
        }

        if (!is_dir($cacheRoot) && !mkdir($cacheRoot, 0775, true) && !is_dir($cacheRoot)) {
            throw ContractException::because('OPUS_TWIG_CACHE_ROOT_NOT_WRITABLE', $cacheRoot);
        }

        $loader = new FilesystemLoader($templateRoot);

        $this->twig = new Environment($loader, [
            'cache' => false,
            'autoescape' => 'html',
            'strict_variables' => true,
        ]);
    }

    public function render(string $template, array $data): string
    {
        if (trim($template) === '') {
            throw ContractException::because('OPUS_TWIG_TEMPLATE_EMPTY');
        }

        return $this->twig->render($template, $data);
    }
}
