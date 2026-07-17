<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$site = $root . '/sites/owasys';
$bootstrap = $site . '/application/default/bootstrap.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

if (!is_file($bootstrap)) {
    $fail('OWASYS_SCORE_BOOTSTRAP_MIGRATION_BOOTSTRAP_MISSING:' . $bootstrap);
}

$source = file_get_contents($bootstrap);
if (!is_string($source) || $source === '') {
    $fail('OWASYS_SCORE_BOOTSTRAP_MIGRATION_READ_FAILED');
}

$required = [
    "use Opus\\Fsm\\FsmSiteLoader;",
    "$body = '<div class=\"ow-shell\"><aside class=\"ow-sidebar\">';",
    "echo '<!doctype html>'",
    "$menu = [];",
];
foreach ($required as $marker) {
    if (!str_contains($source, $marker)) {
        $fail('OWASYS_SCORE_BOOTSTRAP_MIGRATION_UNEXPECTED_SOURCE:' . $marker);
    }
}

$source = str_replace(
    "use Opus\\Fsm\\FsmSiteLoader;",
    "use Opus\\Fsm\\FsmSiteLoader;\nuse Opus\\Template\\ScoreTemplateRenderer;",
    $source
);
$source = str_replace(
    "return is_string($value) && $value !== '' ? $value : $key;",
    "return is_string($value) && $value !== '' ? $value : '[[' . $key . ']]';",
    $source
);

$menuStart = strpos($source, "$menu = [];");
$menuEndMarker = "$asset = static fn (string $assetPath): string => $mount . '/' . ltrim($assetPath, '/');";
$menuEnd = strpos($source, $menuEndMarker, $menuStart);
if ($menuStart === false || $menuEnd === false) {
    $fail('OWASYS_SCORE_BOOTSTRAP_MIGRATION_MENU_BLOCK_NOT_FOUND');
}
$navigationSetup = <<<'PHP'
$navigationPresentation = require $siteRoot . '/application/default/navigation/menu.php';
$navigationAcl = require $siteRoot . '/application/default/security/navigation-acl.php';
$projectNavigation = require $siteRoot . '/application/default/navigation/project.php';
$buildNavigationViewModel = require $siteRoot . '/application/default/navigation/view-model.php';
$dispatchNavigation = require $siteRoot . '/application/default/navigation/dispatch.php';
$authorizeRoute = require $siteRoot . '/application/default/navigation/authorize-route.php';
$runtimeContext = [
    'current_app' => $currentApp,
    'has_current_app' => is_array($currentApp),
    'must_change_password' => $mustChangePassword,
];
$profile = is_array($user) ? (string) ($user['profile'] ?? 'viewer') : 'viewer';
$navigationItems = $isAuthenticated
    ? $projectNavigation($owasysFsmProcessor, $state, $runtimeContext, $profile, $navigationPresentation, $navigationAcl)
    : [];
$navigationViewModel = $buildNavigationViewModel($navigationItems, $t, $link($path));
PHP;
$source = substr($source, 0, $menuStart) . $navigationSetup . "\n" . substr($source, $menuEnd);

$routeAuthNeedle = "$state = (string) ($route['state'] ?? ($route['controller'] ?? ''));";
$routeAuthReplacement = $routeAuthNeedle . <<<'PHP'

if ($isAuthenticated && !in_array($state, ['login', 'account'], true)) {
    $directAuthorization = $authorizeRoute(
        $owasysFsmProcessor,
        $runtimeCurrentState(),
        $state,
        [
            'current_app' => $_SESSION['owasys_current_app'] ?? null,
            'has_current_app' => is_array($_SESSION['owasys_current_app'] ?? null),
        ],
        (string) ($user['profile'] ?? 'viewer'),
        $navigationAcl
    );
    if (($directAuthorization['allowed'] ?? false) !== true) {
        http_response_code(403);
        echo 'OWASYS_ROUTE_ACL_FSM_DENIED';
        exit;
    }
}
PHP;
$source = str_replace($routeAuthNeedle, $routeAuthReplacement, $source);

$logoutNeedle = "if ($path === '/logout') {";
$navigationPost = <<<'PHP'
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string) ($_POST['owasys_action'] ?? '') === 'navigate') {
    $event = trim((string) ($_POST['owasys_navigation_event'] ?? ''));
    $profile = is_array($_SESSION['owasys_user'] ?? null) ? (string) ($_SESSION['owasys_user']['profile'] ?? 'viewer') : 'viewer';
    $context = [
        'current_app' => $_SESSION['owasys_current_app'] ?? null,
        'has_current_app' => is_array($_SESSION['owasys_current_app'] ?? null),
    ];
    $transition = $dispatchNavigation($owasysFsmProcessor, $runtimeCurrentState(), $event, $context, $profile, $navigationAcl);
    $redirectAfterTransition($transition);
}

PHP;
$source = str_replace($logoutNeedle, $navigationPost . $logoutNeedle, $source);

$bodyStart = strpos($source, "$body = '<div class=\"ow-shell\"><aside class=\"ow-sidebar\">';");
$loginStart = strpos($source, "if ($state === 'login') {", $bodyStart);
if ($bodyStart === false || $loginStart === false) {
    $fail('OWASYS_SCORE_BOOTSTRAP_MIGRATION_BODY_BLOCK_NOT_FOUND');
}
$source = substr($source, 0, $bodyStart) . "$contentHtml = '';\n" . substr($source, $loginStart);
$tailStart = strpos($source, "$contentHtml = '';");
$source = substr($source, 0, $tailStart) . str_replace('$body .=', '$contentHtml .=', substr($source, $tailStart));
$source = str_replace("$body .= '</main></div>';\n\n", '', $source);

$echoStart = strpos($source, "echo '<!doctype html>'");
if ($echoStart === false) {
    $fail('OWASYS_SCORE_BOOTSTRAP_MIGRATION_DOCUMENT_RENDER_NOT_FOUND');
}
$source = substr($source, 0, $echoStart) . <<<'PHP'
$csrf = (string) ($_SESSION['owasys_csrf'] ?? '');
if ($csrf === '') {
    $csrf = bin2hex(random_bytes(32));
    $_SESSION['owasys_csrf'] = $csrf;
}

$score = new ScoreTemplateRenderer($siteRoot . '/application/default/templates');
$document = $score->render('layouts/main.score', [
    'locale' => ['code' => $locale],
    'page' => ['title' => $pageTitle, 'summary' => $pageSummary],
    'brand' => ['name' => $t('brand.name'), 'long_name' => $t('brand.subtitle')],
    'state' => ['id' => $state],
    'routes' => ['home' => $link('/')],
    'assets' => [
        'theme_css' => $asset('/asset/themes/owasys/css/theme.css'),
        'theme_js' => $asset('/asset/themes/owasys/js/theme.js'),
    ],
    'auth' => [
        'authenticated' => $isAuthenticated,
        'label' => is_array($user) ? (string) ($user['label'] ?? '') : '',
        'profile' => is_array($user) ? (string) ($user['profile'] ?? '') : '',
    ],
    'current_application' => [
        'present' => is_array($currentApp),
        'id' => is_array($currentApp) ? (string) ($currentApp['id'] ?? '') : '',
        'name' => is_array($currentApp) ? (string) ($currentApp['name'] ?? $currentApp['id'] ?? '') : '',
    ],
    'navigation' => $navigationViewModel,
    'locales' => array_map(static fn (string $code): array => [
        'code' => $code,
        'label' => $code,
        'selected' => $code === $locale,
    ], $locales),
    'locale_switcher' => ['action' => $link($path), 'label' => $t('common.language')],
    'security' => ['csrf' => $csrf],
    'content' => ['html' => $contentHtml],
]);

echo $document;
PHP;

$tmp = $bootstrap . '.tmp';
if (file_put_contents($tmp, $source) === false) {
    $fail('OWASYS_SCORE_BOOTSTRAP_MIGRATION_WRITE_FAILED');
}
$lint = [];
$code = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $lint, $code);
if ($code !== 0) {
    @unlink($tmp);
    $fail('OWASYS_SCORE_BOOTSTRAP_MIGRATION_LINT_FAILED:' . implode('|', $lint));
}
if (!rename($tmp, $bootstrap)) {
    @unlink($tmp);
    $fail('OWASYS_SCORE_BOOTSTRAP_MIGRATION_REPLACE_FAILED');
}

echo 'OWASYS_SCORE_BOOTSTRAP_MIGRATION_OK' . PHP_EOL;
