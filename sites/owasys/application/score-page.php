<?php
declare(strict_types=1);

use Opus\Fsm\FsmSiteLoader;
use Opus\Owasys\RegistryRepository;
use Opus\Template\ScoreTemplateRenderer;
use Owasys\Application\Configuration\SiteConfiguration;
use Owasys\Application\Http\RequestContext;
use Owasys\Application\I18n\Translator;
use Owasys\Application\Session\SessionContext;

$siteRoot = dirname(__DIR__);
$opusRoot = dirname(dirname($siteRoot));
require_once $opusRoot . '/vendor/autoload.php';

try {
    $configuration = SiteConfiguration::load($siteRoot);
    $request = RequestContext::fromServer($_SERVER);
    $auth = $configuration->auth();
    $session = new SessionContext((string) ($auth['session_name'] ?? 'OWASYS_LOCAL_SESSION'));
    $session->start();

    $requestedLocale = strtolower((string) ($_GET['lang'] ?? $session->locale($configuration->defaultLocale())));
    $translator = Translator::load(
        $siteRoot,
        $configuration->locales(),
        $configuration->defaultLocale(),
        $requestedLocale
    );
    $session->setLocale($translator->locale());
    $t = static fn(string $key): string => $translator->translate($key);

    $route = $configuration->routeByPath($request->path());
    $state = (string) ($route['state'] ?? '');
    if ($state === '') {
        throw new RuntimeException('OWASYS_SCORE_STATE_MISSING');
    }

    $user = $session->user();
    if ($user === null && $state !== 'login') {
        header('Location: ' . $request->link('/login'), true, 303);
        exit;
    }

    $fsm = FsmSiteLoader::processorForSite($opusRoot, 'owasys');
    $acl = require $siteRoot . '/application/default/acl/navigation.php';
    $presentation = require $siteRoot . '/application/default/navigation/menu.php';
    $authorizeRoute = require $siteRoot . '/application/default/navigation/authorize-route.php';
    $navigationViewModel = require $siteRoot . '/application/default/navigation/view-model.php';

    $currentApplication = $session->currentApplication();
    if ($currentApplication === null && $user !== null) {
        $registryConfig = is_array($configuration->site()['registry'] ?? null) ? $configuration->site()['registry'] : [];
        $database = trim(str_replace('\\', '/', (string) ($registryConfig['runtime_database'] ?? 'var/registry/owasys.sqlite')), '/');
        $registry = RegistryRepository::forOwasysSite($siteRoot, $opusRoot, $database);
        $currentApplication = $registry->currentApplication();
        if (is_array($currentApplication)) {
            $_SESSION['owasys_current_app'] = $currentApplication;
        }
    }

    $runtimeContext = [
        'has_current_app' => is_array($currentApplication),
        'current_app' => $currentApplication,
    ];
    $currentState = $session->currentState('home');
    $profile = is_array($user) ? (string) ($user['profile'] ?? 'viewer') : 'viewer';

    if ($user !== null) {
        $authorizeRoute($fsm, $currentState, $state, $runtimeContext, $profile, $acl);
    }

    $navigation = $user === null ? [
        'items' => [],
        'action' => $request->link('/'),
        'current_state' => $state,
        'aria_label' => $t('navigation.aria_label'),
    ] : $navigationViewModel(
        $fsm,
        $state,
        $runtimeContext,
        $profile,
        $presentation,
        $acl,
        $request->link('/'),
        $t
    );

    $viewFile = $siteRoot . '/application/states/' . $state . '/views/index.php';
    $page = is_file($viewFile) ? require $viewFile : [];
    if (!is_array($page)) {
        throw new RuntimeException('OWASYS_SCORE_VIEWMODEL_INVALID');
    }

    $contracts = [];
    foreach ((array) ($page['contracts'] ?? []) as $contract) {
        $contracts[] = ['label' => (string) $contract];
    }
    $actions = [];
    foreach ((array) ($page['action_keys'] ?? []) as $key) {
        $actions[] = ['label' => $t((string) $key)];
    }

    $localePresentation = [
        'bg' => ['label' => 'Български', 'flag' => '🇧🇬'],
        'hr' => ['label' => 'Hrvatski', 'flag' => '🇭🇷'],
        'cs' => ['label' => 'Čeština', 'flag' => '🇨🇿'],
        'da' => ['label' => 'Dansk', 'flag' => '🇩🇰'],
        'nl' => ['label' => 'Nederlands', 'flag' => '🇳🇱'],
        'en' => ['label' => 'English', 'flag' => '🇬🇧'],
        'et' => ['label' => 'Eesti', 'flag' => '🇪🇪'],
        'fi' => ['label' => 'Suomi', 'flag' => '🇫🇮'],
        'fr' => ['label' => 'Français', 'flag' => '🇫🇷'],
        'de' => ['label' => 'Deutsch', 'flag' => '🇩🇪'],
        'el' => ['label' => 'Ελληνικά', 'flag' => '🇬🇷'],
        'hu' => ['label' => 'Magyar', 'flag' => '🇭🇺'],
        'ga' => ['label' => 'Gaeilge', 'flag' => '🇮🇪'],
        'it' => ['label' => 'Italiano', 'flag' => '🇮🇹'],
        'lv' => ['label' => 'Latviešu', 'flag' => '🇱🇻'],
        'lt' => ['label' => 'Lietuvių', 'flag' => '🇱🇹'],
        'mt' => ['label' => 'Malti', 'flag' => '🇲🇹'],
        'pl' => ['label' => 'Polski', 'flag' => '🇵🇱'],
        'pt' => ['label' => 'Português', 'flag' => '🇵🇹'],
        'ro' => ['label' => 'Română', 'flag' => '🇷🇴'],
        'sk' => ['label' => 'Slovenčina', 'flag' => '🇸🇰'],
        'sl' => ['label' => 'Slovenščina', 'flag' => '🇸🇮'],
        'es' => ['label' => 'Español', 'flag' => '🇪🇸'],
        'sv' => ['label' => 'Svenska', 'flag' => '🇸🇪'],
        'uk' => ['label' => 'Українська', 'flag' => '🇺🇦'],
    ];
    $localeAction = $request->link($request->path());
    $locales = [];
    foreach ($configuration->locales() as $code) {
        $presentationData = $localePresentation[$code] ?? ['label' => strtoupper($code), 'flag' => '🌐'];
        $locales[] = [
            'code' => $code,
            'label' => $presentationData['label'],
            'flag' => $presentationData['flag'],
            'href' => $localeAction . '?lang=' . rawurlencode($code),
            'selected' => $code === $translator->locale(),
        ];
    }
    $currentLocalePresentation = $localePresentation[$translator->locale()] ?? [
        'label' => strtoupper($translator->locale()),
        'flag' => '🌐',
    ];

    $currentApplicationView = is_array($currentApplication) ? [
        'present' => true,
        'name' => (string) ($currentApplication['name'] ?? $currentApplication['id'] ?? ''),
        'id' => (string) ($currentApplication['id'] ?? ''),
        'kind' => (string) ($currentApplication['kind'] ?? ''),
        'root_path' => (string) ($currentApplication['root_path'] ?? ''),
        'status' => (string) ($currentApplication['status'] ?? ''),
        'working_label' => $t('registry.you_are_working_on'),
        'change_label' => $t('registry.change_application'),
        'id_label' => $t('common.id'),
        'type_label' => $t('common.type'),
        'root_label' => $t('common.root'),
        'status_label' => $t('common.status'),
    ] : [
        'present' => false,
        'name' => '', 'id' => '', 'kind' => '', 'root_path' => '', 'status' => '',
        'working_label' => '', 'change_label' => '', 'id_label' => '', 'type_label' => '', 'root_label' => '', 'status_label' => '',
    ];

    $renderer = new ScoreTemplateRenderer($siteRoot . '/application/default/templates');
    $viewModel = [
        'locale' => [
            'code' => $translator->locale(),
            'action' => $localeAction,
            'label' => $translator->locale() === 'fr' ? 'Langue' : 'Language',
            'submit_label' => $translator->locale() === 'fr' ? 'Appliquer' : 'Apply',
            'current_label' => $currentLocalePresentation['label'],
            'current_flag' => $currentLocalePresentation['flag'],
            'preserved_query' => [],
            'options' => $locales,
        ],
        'page' => [
            'title' => $t('state.' . $state . '.title'),
            'summary' => $t('state.default.summary'),
        ],
        'state' => ['id' => $state],
        'brand' => ['name' => $t('brand.name'), 'long_name' => $t('brand.subtitle')],
        'routes' => ['home' => $request->link('/'), 'applications' => $request->link('/applications')],
        'assets' => ['theme_css' => $request->asset('/asset/css/score.css'), 'theme_js' => $request->asset('/asset/js/owasys.js')],
        'auth' => [
            'authenticated' => $user !== null,
            'label' => is_array($user) ? (string) ($user['label'] ?? '') : '',
            'profile' => is_array($user) ? (string) ($user['profile'] ?? '') : '',
        ],
        'security' => ['csrf' => $session->csrfToken()],
        'navigation' => $navigation,
        'current_application' => $currentApplicationView,
        'content' => [
            'section_title' => $t('state.default.section'),
            'section_summary' => $t('state.default.summary'),
            'contracts_label' => $t('common.contracts'),
            'actions_label' => $t('common.next_actions'),
            'has_contracts' => $contracts !== [],
            'contracts' => $contracts,
            'has_actions' => $actions !== [],
            'actions' => $actions,
        ],
    ];
    $viewModel['content']['html'] = $renderer->render('partials/state-content.score', $viewModel);

    if ($user !== null) {
        $_SESSION['owasys_current_state'] = $state;
    }

    header('Content-Type: text/html; charset=UTF-8');
    echo $renderer->render('layouts/main.score', $viewModel);
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'OWASYS_SCORE_PAGE_FAILED: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
