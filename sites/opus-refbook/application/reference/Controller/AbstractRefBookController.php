<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Controller;

use Opus\Application\ApplicationPaths;
use Opus\Renderer\ViewModel;
use Opus\Template\TemplateRendererInterface;
use OpusRefBook\Reference\Service\ReferenceBreadcrumbService;
use OpusRefBook\Reference\Service\ReferenceOpusRootLocator;
use OpusRefBook\Reference\Service\ReferenceCatalogService;
use OpusRefBook\Reference\Service\ReferenceContentService;
use OpusRefBook\Reference\Service\ReferenceFsmRunner;
use OpusRefBook\Reference\Service\ReferencePublicSiteService;
use OpusRefBook\Reference\Service\ReferenceRuntimeSnapshotRepository;
use OpusRefBook\Reference\Service\ReferenceSearchService;
use OpusRefBook\Reference\Service\ReferenceThemeService;
use OpusRefBook\Reference\Service\ReferenceVersionService;
use RuntimeException;

/**
 * INTERNAL CONTROLLER BASE
 *
 * Role:
 *   Share RefBook controller dependencies and prepare the common ScoreTemplate
 *   ViewModel data required by the public OPUS Reference Book shell.
 *
 * Contract:
 *   Controller helper only. Business preparation stays in services. HTML
 *   representation belongs to `.score` templates rendered by the official Opus
 *   ScoreTemplateRenderer; this class must not rebuild page HTML in PHP.
 */
abstract class AbstractRefBookController
{
    private ?ReferenceContentService $contentCache = null;
    private ?ReferenceThemeService $themeCache = null;
    private ?ReferenceFsmRunner $runtimeCache = null;
    private ?ReferenceCatalogService $catalogCache = null;
    private ?ReferenceSearchService $searchCache = null;
    private ?ReferencePublicSiteService $publicSiteCache = null;
    private ?ReferenceVersionService $versionCache = null;
    private ?ReferenceBreadcrumbService $breadcrumbCache = null;

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
        if ($this->contentCache === null) {
            $this->contentCache = new ReferenceContentService($this->paths->appRoot . '/content/refbook/i18n', $this->language());
        }

        return $this->contentCache;
    }

    protected function theme(): ReferenceThemeService
    {
        if ($this->themeCache === null) {
            $theme = (string) ($_GET['theme'] ?? ReferenceThemeService::DEFAULT_THEME);
            $this->themeCache = new ReferenceThemeService($theme);
        }

        return $this->themeCache;
    }

    protected function refBookRuntime(): ReferenceFsmRunner
    {
        if ($this->runtimeCache === null) {
            $this->runtimeCache = new ReferenceFsmRunner(
                new ReferenceRuntimeSnapshotRepository(ReferenceOpusRootLocator::fromEnvironment())
            );
        }

        return $this->runtimeCache;
    }

    protected function catalog(): ReferenceCatalogService
    {
        if ($this->catalogCache === null) {
            $this->catalogCache = new ReferenceCatalogService(
                $this->refBookRuntime(),
                $this->content()
            );
        }

        return $this->catalogCache;
    }

    protected function search(): ReferenceSearchService
    {
        if ($this->searchCache === null) {
            $this->searchCache = new ReferenceSearchService($this->catalog(), $this->content());
        }

        return $this->searchCache;
    }

    protected function version(): ReferenceVersionService
    {
        if ($this->versionCache === null) {
            $this->versionCache = new ReferenceVersionService($this->paths->appRoot);
        }

        return $this->versionCache;
    }

    protected function publicSite(): ReferencePublicSiteService
    {
        if ($this->publicSiteCache === null) {
            $this->publicSiteCache = new ReferencePublicSiteService();
        }

        return $this->publicSiteCache;
    }

    protected function breadcrumb(): ReferenceBreadcrumbService
    {
        if ($this->breadcrumbCache === null) {
            $this->breadcrumbCache = new ReferenceBreadcrumbService($this->paths, $this->content(), $this->publicSite());
        }

        return $this->breadcrumbCache;
    }

    /**
     * @param array<string,mixed> $data
     */
    protected function view(string $template, array $data, int $status = 200): ViewModel
    {
        if (str_ends_with($template, '.twig')) {
            throw new RuntimeException('OPUS_REFBOOK_TWIG_TEMPLATE_FORBIDDEN=' . $template);
        }

        if (!str_ends_with($template, '.score')) {
            throw new RuntimeException('OPUS_REFBOOK_SCORE_TEMPLATE_REQUIRED=' . $template);
        }

        $overview = $this->catalog()->overview();
        $content = $this->content();
        $theme = $this->theme();
        $publicSite = $this->publicSite();
        $pageSlug = (string) ($data['pageSlug'] ?? ($_GET['page'] ?? ''));
        $title = (string) ($data['title'] ?? $content->t('breadcrumb.home'));

        $data['basePath'] = $publicSite->basePath();
        $data['canonicalBaseUrl'] = $publicSite->publicBaseUrl();
        $data['lang'] = $content->language();
        $data['languages'] = $content->supportedLanguages();
        $data['languageOptions'] = $content->languageOptions();
        $data['languageState'] = $content->languageState();
        $data['isPartialLanguage'] = $content->isPartialLanguage();
        $data['sourceLanguage'] = $data['languageState']['source_language'];
        $data['theme'] = $theme->theme();
        $data['themeClass'] = $theme->bodyClass();
        $data['themes'] = $theme->supportedThemes();
        $data['pageSlug'] = $pageSlug;
        $data['searchQuery'] = trim((string) ($_GET['q'] ?? ''));
        $data['breadcrumbTitle'] = $title;
        $data['breadcrumbs'] = $this->breadcrumb()->trail($title, $pageSlug, $theme->theme());
        $data['ui'] = $content->labels();
        $data['site'] = $publicSite->branding($data['ui']['footer'] ?? []);
        $data['moduleTitle'] = (string) ($content->module()['title'] ?? 'Opus Reference Book');
        $data['module'] = $content->module();
        $data['navigationDomains'] = $overview['domains'];
        $data['navigationGuides'] = $content->guideNavigation();
        $data['runtime'] = $overview['runtime'];
        $data['assetIntegrity'] = $overview['asset_integrity'];
        $data['versionPanel'] = $this->version()->panel();
        $data['seo'] = $publicSite->seo($data);
        $data['contentHtml'] = $this->templateRenderer->render($template, $data);

        return new ViewModel('layout.score', $data, $status);
    }
}
