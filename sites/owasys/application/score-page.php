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
    $translator = Translator::load($siteRoot, $configuration->locales(), $configuration->defaultLocale(), $requestedLocale);
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

    $runtimeContext = ['has_current_app' => is_array($currentApplication), 'current_app' => $currentApplication];
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
    ] : $navigationViewModel($fsm, $state, $runtimeContext, $profile, $presentation, $acl, $request->link('/'), $t);

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
        'bg' => ['label' => 'Български', 'flag_id' => 'flag-bg'], 'hr' => ['label' => 'Hrvatski', 'flag_id' => 'flag-hr'],
        'cs' => ['label' => 'Čeština', 'flag_id' => 'flag-cs'], 'da' => ['label' => 'Dansk', 'flag_id' => 'flag-da'],
        'nl' => ['label' => 'Nederlands', 'flag_id' => 'flag-nl'], 'en' => ['label' => 'English', 'flag_id' => 'flag-en'],
        'et' => ['label' => 'Eesti', 'flag_id' => 'flag-et'], 'fi' => ['label' => 'Suomi', 'flag_id' => 'flag-fi'],
        'fr' => ['label' => 'Français', 'flag_id' => 'flag-fr'], 'de' => ['label' => 'Deutsch', 'flag_id' => 'flag-de'],
        'el' => ['label' => 'Ελληνικά', 'flag_id' => 'flag-el'], 'hu' => ['label' => 'Magyar', 'flag_id' => 'flag-hu'],
        'ga' => ['label' => 'Gaeilge', 'flag_id' => 'flag-ga'], 'it' => ['label' => 'Italiano', 'flag_id' => 'flag-it'],
        'lv' => ['label' => 'Latviešu', 'flag_id' => 'flag-lv'], 'lt' => ['label' => 'Lietuvių', 'flag_id' => 'flag-lt'],
        'mt' => ['label' => 'Malti', 'flag_id' => 'flag-mt'], 'pl' => ['label' => 'Polski', 'flag_id' => 'flag-pl'],
        'pt' => ['label' => 'Português', 'flag_id' => 'flag-pt'], 'ro' => ['label' => 'Română', 'flag_id' => 'flag-ro'],
        'sk' => ['label' => 'Slovenčina', 'flag_id' => 'flag-sk'], 'sl' => ['label' => 'Slovenščina', 'flag_id' => 'flag-sl'],
        'es' => ['label' => 'Español', 'flag_id' => 'flag-es'], 'sv' => ['label' => 'Svenska', 'flag_id' => 'flag-sv'],
        'uk' => ['label' => 'Українська', 'flag_id' => 'flag-uk'],
    ];
    $localeAction = $request->link($request->path());
    $flagSprite = $request->asset('/asset/flags/locale-flags.svg');
    $locales = [];
    foreach ($configuration->locales() as $code) {
        $localeData = $localePresentation[$code] ?? ['label' => strtoupper($code), 'flag_id' => 'flag-world'];
        $locales[] = [
            'code' => $code,
            'label' => $localeData['label'],
            'flag_id' => $localeData['flag_id'],
            'href' => $localeAction . '?lang=' . rawurlencode($code),
            'selected' => $code === $translator->locale(),
        ];
    }
    $currentLocale = $localePresentation[$translator->locale()] ?? ['label' => strtoupper($translator->locale()), 'flag_id' => 'flag-world'];

    $currentApplicationView = is_array($currentApplication) ? [
        'present' => true,
        'name' => (string) ($currentApplication['name'] ?? $currentApplication['id'] ?? ''),
        'id' => (string) ($currentApplication['id'] ?? ''),
        'kind' => (string) ($currentApplication['kind'] ?? ''),
        'root_path' => (string) ($currentApplication['root_path'] ?? ''),
        'status' => (string) ($currentApplication['status'] ?? ''),
        'working_label' => $t('registry.you_are_working_on'), 'change_label' => $t('registry.change_application'),
        'id_label' => $t('common.id'), 'type_label' => $t('common.type'), 'root_label' => $t('common.root'), 'status_label' => $t('common.status'),
    ] : [
        'present' => false, 'name' => '', 'id' => '', 'kind' => '', 'root_path' => '', 'status' => '',
        'working_label' => '', 'change_label' => '', 'id_label' => '', 'type_label' => '', 'root_label' => '', 'status_label' => '',
    ];

    $renderer = new ScoreTemplateRenderer($siteRoot . '/application/default/templates');
    $viewModel = [
        'locale' => [
            'code' => $translator->locale(), 'action' => $localeAction,
            'label' => $translator->locale() === 'fr' ? 'Langue' : 'Language',
            'submit_label' => $translator->locale() === 'fr' ? 'Appliquer' : 'Apply',
            'current_label' => $currentLocale['label'], 'current_flag_id' => $currentLocale['flag_id'],
            'flag_sprite' => $flagSprite, 'preserved_query' => [], 'options' => $locales,
        ],
        'page' => [
            'title' => $t((string) ($page['title_key'] ?? 'state.' . $state . '.title')),
            'summary' => $t((string) ($page['summary_key'] ?? 'state.default.summary')),
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
        'state_content' => is_array($page['state_content'] ?? null) ? $page['state_content'] : [],
        'content' => [
            'section_title' => $t('state.default.section'), 'section_summary' => $t('state.default.summary'),
            'contracts_label' => $t('common.contracts'), 'actions_label' => $t('common.next_actions'),
            'has_contracts' => $contracts !== [], 'contracts' => $contracts,
            'has_actions' => $actions !== [], 'actions' => $actions,
        ],
    ];

    $stateTemplate = (string) ($page['template'] ?? '');
    if ($stateTemplate !== '') {
        $stateRenderer = new ScoreTemplateRenderer($siteRoot . '/application/states/' . $state . '/templates');
        $viewModel['content']['html'] = $stateRenderer->render($stateTemplate, $viewModel);
    } else {
        $viewModel['content']['html'] = $renderer->render('partials/state-content.score', $viewModel);
    }

    if ($user !== null) {
        $_SESSION['owasys_current_state'] = $state;
    }

    header('Content-Type: text/html; charset=UTF-8');
    echo $renderer->render('layouts/main.score', $viewModel);
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'OWASYS_SCORE_PAGE_FAILED: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
