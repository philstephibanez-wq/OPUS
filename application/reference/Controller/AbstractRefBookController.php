<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Controller;

use ASAP\Application\ApplicationPaths;
use ASAP\Renderer\ViewModel;
use ASAP\Template\TemplateRendererInterface;
use ASAPRefBook\Reference\Service\ManifestRepository;
use ASAPRefBook\Reference\Service\ReferenceCatalogService;
use ASAPRefBook\Reference\Service\ReferenceContentService;
use ASAPRefBook\Reference\Service\ReferenceSearchService;
use ASAPRefBook\Reference\Service\ReferenceThemeService;
use RuntimeException;

/**
 * INTERNAL CONTROLLER BASE
 *
 * Role:
 *   Share RefBook controller dependencies.
 *
 * Contract:
 *   Controller helper only. Business preparation stays in services.
 */
abstract class AbstractRefBookController
{
    public function __construct(
        protected readonly ApplicationPaths $paths,
        protected readonly TemplateRendererInterface $templateRenderer
    ) {
    }

    protected function language(): string
    {
        $language = (string) ($_GET['lang'] ?? ReferenceContentService::DEFAULT_LANGUAGE);

        if (!in_array($language, ReferenceContentService::SUPPORTED_LANGUAGES, true)) {
            throw new RuntimeException('OPUS_REFBOOK_LANG_UNSUPPORTED=' . $language);
        }

        return $language;
    }

    protected function content(): ReferenceContentService
    {
        return new ReferenceContentService($this->paths->appRoot . '/content/refbook/i18n', $this->language());
    }

    protected function theme(): ReferenceThemeService
    {
        $theme = (string) ($_GET['theme'] ?? ReferenceThemeService::DEFAULT_THEME);

        return new ReferenceThemeService($theme);
    }

    protected function catalog(): ReferenceCatalogService
    {
        return new ReferenceCatalogService(
            new ManifestRepository($this->paths->appRoot . '/var/data/api_reference.generated.json'),
            $this->content()
        );
    }

    protected function search(): ReferenceSearchService
    {
        return new ReferenceSearchService($this->catalog(), $this->content());
    }

    /**
     * @param array<string,mixed> $data
     */
    protected function view(string $template, array $data, int $status = 200): ViewModel
    {
        $overview = $this->catalog()->overview();
        $content = $this->content();
        $theme = $this->theme();

        $data['basePath'] = '/OPUS_REF_BOOK';
        $data['lang'] = $content->language();
        $data['languages'] = $content->supportedLanguages();
        $data['theme'] = $theme->theme();
        $data['themeClass'] = $theme->bodyClass();
        $data['themes'] = $theme->supportedThemes();
        $data['pageSlug'] = (string) ($_GET['page'] ?? '');
        $data['searchQuery'] = trim((string) ($_GET['q'] ?? ''));
        $data['breadcrumbTitle'] = (string) ($data['title'] ?? $content->t('breadcrumb.home'));
        $data['ui'] = $content->labels();
        $data['moduleTitle'] = (string) ($content->module()['title'] ?? 'Opus Reference Book');
        $data['module'] = $content->module();
        $data['navigationDomains'] = $overview['domains'];
        $data['navigationGuides'] = $content->guideNavigation();

        return new ViewModel($template, $data, $status);
    }
}