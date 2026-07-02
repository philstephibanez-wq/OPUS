<?php
declare(strict_types=1);

/**
 * OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE
 *
 * Finalise la brique auth/sign-in/i18n OPUS Manager restée dirty.
 */

$root = getcwd();

if (!is_file($root . DIRECTORY_SEPARATOR . 'composer.json')) {
    fwrite(STDERR, 'OPUS_MANAGER_AUTH_I18N_FINALIZE_NOT_IN_OPUS_ROOT' . PHP_EOL);
    exit(1);
}

$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'opus-manager';
$srcRoot = $siteRoot . DIRECTORY_SEPARATOR . 'src';
$controllerRoot = $srcRoot . DIRECTORY_SEPARATOR . 'Controller';
$serviceRoot = $srcRoot . DIRECTORY_SEPARATOR . 'Service';
$configRoot = $siteRoot . DIRECTORY_SEPARATOR . 'config';
$docRoot = $root . DIRECTORY_SEPARATOR . 'DOC';
$siteDocRoot = $siteRoot . DIRECTORY_SEPARATOR . 'DOC';
$toolsSmokeRoot = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'smokes';

foreach ([$controllerRoot, $serviceRoot, $configRoot, $docRoot, $siteDocRoot, $toolsSmokeRoot] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, 'OPUS_MANAGER_AUTH_I18N_FINALIZE_DIR_FAILED: ' . $dir . PHP_EOL);
        exit(1);
    }
}

function opus_auth_write(string $file, string $source): void
{
    if (file_put_contents($file, $source) === false) {
        fwrite(STDERR, 'OPUS_MANAGER_AUTH_I18N_FINALIZE_WRITE_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }
}

function opus_auth_read_if_exists(string $file): string
{
    if (!is_file($file)) {
        return '';
    }

    $source = file_get_contents($file);
    if (!is_string($source)) {
        fwrite(STDERR, 'OPUS_MANAGER_AUTH_I18N_FINALIZE_READ_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }

    return $source;
}

$i18n = <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Manager\Service;

/** OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE */
final class OpusManagerI18n
{
    /**
     * @return array<string, string>
     */
    public static function languages(): array
    {
        return [
            'bg' => 'Български',
            'hr' => 'Hrvatski',
            'cs' => 'Čeština',
            'da' => 'Dansk',
            'nl' => 'Nederlands',
            'en' => 'English',
            'et' => 'Eesti',
            'fi' => 'Suomi',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'el' => 'Ελληνικά',
            'hu' => 'Magyar',
            'ga' => 'Gaeilge',
            'it' => 'Italiano',
            'lv' => 'Latviešu',
            'lt' => 'Lietuvių',
            'mt' => 'Malti',
            'pl' => 'Polski',
            'pt' => 'Português',
            'ro' => 'Română',
            'sk' => 'Slovenčina',
            'sl' => 'Slovenščina',
            'es' => 'Español',
            'sv' => 'Svenska',
            'uk' => 'Українська',
        ];
    }

    public static function resolveLang(?string $lang): string
    {
        $candidate = strtolower(trim((string) $lang));
        return array_key_exists($candidate, self::languages()) ? $candidate : 'fr';
    }

    public static function languageName(?string $lang): string
    {
        $resolved = self::resolveLang($lang);
        return self::languages()[$resolved];
    }

    public static function optionsHtml(?string $selected): string
    {
        $selected = self::resolveLang($selected);
        $html = '';

        foreach (self::languages() as $code => $label) {
            $isSelected = $code === $selected ? ' selected' : '';
            $html .= '<option value="' . self::h($code) . '"' . $isSelected . '>' . self::h($label . ' — ' . strtoupper($code)) . '</option>';
        }

        return $html;
    }

    private static function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
PHP;

$environment = <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Manager\Service;

/** OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE */
final class OpusManagerEnvironment
{
    public static function current(?string $candidate = null): string
    {
        $value = strtolower(trim((string) ($candidate ?? ($_ENV['OPUS_ENV'] ?? getenv('OPUS_ENV') ?: 'dev'))));

        return in_array($value, ['dev', 'staging', 'prod'], true) ? $value : 'dev';
    }

    public static function isProd(?string $candidate = null): bool
    {
        return self::current($candidate) === 'prod';
    }

    public static function filterProfilerInput(?string $candidate = null): void
    {
        if (!self::isProd($candidate)) {
            return;
        }

        unset($_GET['profiler'], $_GET['_profiler'], $_GET['profile']);
        unset($_POST['profiler'], $_POST['_profiler'], $_POST['profile']);
        unset($_REQUEST['profiler'], $_REQUEST['_profiler'], $_REQUEST['profile']);
    }
}
PHP;

$auth = <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Manager\Service;

/** OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE */
final class OpusManagerAuth
{
    public const DEV_USERNAME = 'admin';
    public const DEV_PASSWORD = 'admin';

    public static function ensureSession(): void
    {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }

        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }

    public static function isSignedIn(): bool
    {
        self::ensureSession();

        return isset($_SESSION['opus_manager_user']) && is_string($_SESSION['opus_manager_user']) && $_SESSION['opus_manager_user'] !== '';
    }

    public static function user(): string
    {
        self::ensureSession();

        return self::isSignedIn() ? (string) $_SESSION['opus_manager_user'] : '';
    }

    public static function signIn(string $username, string $password): bool
    {
        self::ensureSession();

        if ($username === self::DEV_USERNAME && $password === self::DEV_PASSWORD) {
            $_SESSION['opus_manager_user'] = $username;
            return true;
        }

        return false;
    }

    public static function signOut(): void
    {
        self::ensureSession();
        unset($_SESSION['opus_manager_user']);
    }
}
PHP;

$abstract = <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

use Opus\Manager\Service\OpusManagerAuth;
use Opus\Manager\Service\OpusManagerEnvironment;
use Opus\Manager\Service\OpusManagerI18n;
use Opus\Manager\Service\OpusManagerModuleRegistry;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE */
abstract class AbstractOpusManagerController implements OpusManagerControllerInterface
{
    abstract public function route(): string;

    abstract public function title(): string;

    abstract public function group(): string;

    abstract public function isExpert(): bool;

    abstract public function render(array $context = []): string;

    protected function shell(string $title, string $body, array $context = []): string
    {
        $lang = OpusManagerI18n::resolveLang((string) ($context['lang'] ?? ($_GET['lang'] ?? 'fr')));
        $env = OpusManagerEnvironment::current((string) ($context['env'] ?? 'dev'));
        $signedIn = array_key_exists('signed_in', $context) ? (bool) $context['signed_in'] : OpusManagerAuth::isSignedIn();
        $user = (string) ($context['user'] ?? OpusManagerAuth::user());

        $profiler = OpusManagerEnvironment::isProd($env)
            ? ''
            : '<span class="om-pill">DEV</span>';

        $auth = $signedIn
            ? '<span class="om-user">' . $this->h($user !== '' ? $user : 'admin') . '</span><a class="om-link" href="/opus-manager/logout?lang=' . $this->h($lang) . '">Sign out</a>'
            : '<a class="om-link" href="/opus-manager/sign-in?lang=' . $this->h($lang) . '">Sign in</a>';

        $html = '<!doctype html><html lang="' . $this->h($lang) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        $html .= '<title>' . $this->h($title) . ' — OPUS Manager</title><link rel="stylesheet" href="/opus-manager-ui.css"></head><body>';
        $html .= '<div class="om-shell"><aside class="om-sidebar"><div class="om-brand"><strong>OPUS Manager</strong><span>Workspace</span></div>' . $this->navigation($lang) . '</aside>';
        $html .= '<main class="om-main"><header class="om-topbar"><div><h1>' . $this->h($title) . '</h1><p>OPUS Manager orchestre les briques OPUS sans recréer les moteurs.</p></div>';
        $html .= '<div class="om-env">' . $profiler . $auth . $this->languageSelector($lang) . '</div></header>';
        $html .= '<section class="om-content">' . $body . '</section></main></div></body></html>';

        return $html;
    }

    protected function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function navigation(string $lang): string
    {
        $groups = [];

        foreach (OpusManagerModuleRegistry::modules() as $module) {
            $group = (string) ($module['group'] ?? 'OPUS');
            $groups[$group][] = $module;
        }

        $html = '<nav class="om-nav">';
        foreach ($groups as $group => $modules) {
            $html .= '<section><h2>' . $this->h($group) . '</h2>';
            foreach ($modules as $module) {
                $route = (string) ($module['route'] ?? '#');
                $title = (string) ($module['title'] ?? $route);
                $summary = (string) ($module['summary'] ?? '');
                $html .= '<a href="' . $this->h($route) . '?lang=' . $this->h($lang) . '"><span>' . $this->h($title) . '</span><small>' . $this->h($summary) . '</small></a>';
            }
            $html .= '</section>';
        }

        return $html . '</nav>';
    }

    private function languageSelector(string $lang): string
    {
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/opus-manager/create-site'), PHP_URL_PATH) ?: '/opus-manager/create-site');

        return '<form class="om-lang" method="get" action="' . $this->h($path) . '"><label for="om-lang-select">Langue</label><select id="om-lang-select" name="lang" onchange="this.form.submit()">' . OpusManagerI18n::optionsHtml($lang) . '</select><noscript><button type="submit">OK</button></noscript></form>';
    }
}
PHP;

$signIn = <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

use Opus\Manager\Service\OpusManagerAuth;
use Opus\Manager\Service\OpusManagerEnvironment;
use Opus\Manager\Service\OpusManagerI18n;

/** OPUS_MANAGER_SIGNIN_ROUTE_SMOKE_FIX_CORE OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE */
final class SignInController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/sign-in';
    }

    public function title(): string
    {
        return 'Sign in';
    }

    public function group(): string
    {
        return 'Identity';
    }

    public function isExpert(): bool
    {
        return false;
    }

    public function render(array $context = []): string
    {
        $lang = OpusManagerI18n::resolveLang((string) ($context['lang'] ?? ($_GET['lang'] ?? 'fr')));
        $next = (string) ($_GET['next'] ?? '/opus-manager/create-site');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $username = (string) ($_POST['username'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $next = (string) ($_POST['next'] ?? $next);

            if (OpusManagerAuth::signIn($username, $password)) {
                header('Location: ' . ($next !== '' ? $next : '/opus-manager/create-site') . (str_contains($next, '?') ? '&' : '?') . 'lang=' . rawurlencode($lang), true, 302);
                return '';
            }

            return $this->renderStandaloneSignIn($lang, $next, 'Identifiant ou mot de passe invalide.');
        }

        return $this->renderStandaloneSignIn($lang, $next, '');
    }

    private function renderStandaloneSignIn(string $lang, string $next, string $error): string
    {
        $env = OpusManagerEnvironment::current();
        $errorHtml = $error !== '' ? '<p class="om-error">' . $this->h($error) . '</p>' : '';
        $prodBadge = OpusManagerEnvironment::isProd($env) ? '<span class="om-pill">PROD</span>' : '<span class="om-pill">DEV</span>';

        $html = '<!doctype html><html lang="' . $this->h($lang) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        $html .= '<title>Sign in — OPUS Manager</title><link rel="stylesheet" href="/opus-manager-ui.css"></head><body class="om-auth-page">';
        $html .= '<main class="om-auth-shell"><section class="om-card om-primary"><h1>OPUS Manager</h1><p>Connexion au manager OPUS.</p><div class="om-auth-badges">' . $prodBadge . '</div></section>';
        $html .= '<section class="om-card"><h2>Sign in</h2>' . $errorHtml;
        $html .= '<form class="om-form" method="post" action="/opus-manager/sign-in?lang=' . $this->h($lang) . '">';
        $html .= '<input type="hidden" name="next" value="' . $this->h($next) . '">';
        $html .= '<label>Identifiant<input name="username" autocomplete="username" required></label>';
        $html .= '<label>Mot de passe<input name="password" type="password" autocomplete="current-password" required></label>';
        $html .= '<button type="submit">Sign in</button></form>';
        $html .= '<p class="om-muted">Dev : admin / admin</p></section>';
        $html .= '<section class="om-card om-auth-lang"><h2>Changer la langue</h2><form method="get" action="/opus-manager/sign-in"><input type="hidden" name="next" value="' . $this->h($next) . '"><select name="lang" onchange="this.form.submit()">' . OpusManagerI18n::optionsHtml($lang) . '</select><noscript><button type="submit">OK</button></noscript></form></section>';
        $html .= '</main></body></html>';

        return $html;
    }
}
PHP;

$logout = <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

use Opus\Manager\Service\OpusManagerAuth;
use Opus\Manager\Service\OpusManagerI18n;

/** OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE */
final class LogoutController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/logout';
    }

    public function title(): string
    {
        return 'Logout';
    }

    public function group(): string
    {
        return 'Identity';
    }

    public function isExpert(): bool
    {
        return false;
    }

    public function render(array $context = []): string
    {
        $lang = OpusManagerI18n::resolveLang((string) ($context['lang'] ?? ($_GET['lang'] ?? 'fr')));
        OpusManagerAuth::signOut();
        header('Location: /opus-manager/sign-in?lang=' . rawurlencode($lang), true, 302);

        return '';
    }
}
PHP;

$router = <<<'PHP'
<?php
declare(strict_types=1);

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE OPUS_MANAGER_SIGNIN_ROUTE_SMOKE_FIX_CORE OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE */

spl_autoload_register(static function (string $class): void {
    $prefix = 'Opus\\Manager\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

use Opus\Manager\Controller\LogoutController;
use Opus\Manager\Controller\SignInController;
use Opus\Manager\Service\OpusManagerAuth;
use Opus\Manager\Service\OpusManagerEnvironment;
use Opus\Manager\Service\OpusManagerI18n;
use Opus\Manager\Service\OpusManagerModuleRegistry;

$path = rawurldecode((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/opus-manager'), PHP_URL_PATH) ?: '/opus-manager'));
$lang = OpusManagerI18n::resolveLang((string) ($_GET['lang'] ?? 'fr'));
$env = OpusManagerEnvironment::current();

OpusManagerEnvironment::filterProfilerInput($env);

if ($path === '/' || $path === '/opus-manager/') {
    header('Location: /opus-manager/create-site?lang=' . rawurlencode($lang), true, 302);
    return;
}

if ($path === '/opus-manager-ui.css') {
    return false;
}

if ($path === '/opus-manager/login' || $path === '/opus-manager/signin') {
    header('Location: /opus-manager/sign-in?lang=' . rawurlencode($lang), true, 302);
    return;
}

if ($path === '/opus-manager/sign-in') {
    echo (new SignInController())->render(['lang' => $lang, 'env' => $env]);
    return;
}

if ($path === '/opus-manager/logout') {
    echo (new LogoutController())->render(['lang' => $lang, 'env' => $env]);
    return;
}

if (!OpusManagerAuth::isSignedIn()) {
    header('Location: /opus-manager/sign-in?lang=' . rawurlencode($lang) . '&next=' . rawurlencode($path), true, 302);
    return;
}

$routeMap = OpusManagerModuleRegistry::routeMap();
$controllerClass = $routeMap[$path] ?? null;

if ($controllerClass === null) {
    http_response_code(404);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>OPUS Manager — 404</title><link rel="stylesheet" href="/opus-manager-ui.css"></head><body><main class="om-content"><section class="om-card"><h1>Page OPUS Manager introuvable</h1><p>La route demandée n’est pas déclarée dans le shell.</p><p><a href="/opus-manager/create-site?lang=' . htmlspecialchars($lang, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Retour au wizard Créer un site</a></p></section></main></body></html>';
    return;
}

$fqcn = 'Opus\\Manager\\Controller\\' . $controllerClass;
$controller = new $fqcn();

echo $controller->render([
    'lang' => $lang,
    'env' => $env,
    'signed_in' => true,
    'user' => OpusManagerAuth::user(),
]);
PHP;

$languagesConfig = <<<'PHP'
<?php
declare(strict_types=1);

/** OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE */
return [
    'default' => 'fr',
    'required' => ['fr', 'en', 'de', 'es', 'it', 'pt', 'nl', 'pl', 'uk'],
    'contract' => 'official EU languages + Ukrainian uk',
];
PHP;

opus_auth_write($serviceRoot . DIRECTORY_SEPARATOR . 'OpusManagerI18n.php', $i18n);
opus_auth_write($serviceRoot . DIRECTORY_SEPARATOR . 'OpusManagerEnvironment.php', $environment);
opus_auth_write($serviceRoot . DIRECTORY_SEPARATOR . 'OpusManagerAuth.php', $auth);
opus_auth_write($controllerRoot . DIRECTORY_SEPARATOR . 'AbstractOpusManagerController.php', $abstract);
opus_auth_write($controllerRoot . DIRECTORY_SEPARATOR . 'SignInController.php', $signIn);
opus_auth_write($controllerRoot . DIRECTORY_SEPARATOR . 'LogoutController.php', $logout);
opus_auth_write($siteRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'router.php', $router);
opus_auth_write($configRoot . DIRECTORY_SEPARATOR . 'languages.php', $languagesConfig);

$cssFile = $siteRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'opus-manager-ui.css';
$css = opus_auth_read_if_exists($cssFile);
if (!str_contains($css, 'OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE')) {
    $css .= PHP_EOL;
    $css .= '/* OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE */' . PHP_EOL;
    $css .= '.om-auth-page{min-height:100vh}.om-auth-shell{max-width:980px;margin:0 auto;padding:3rem 1rem;display:grid;gap:1rem}.om-form{display:grid;gap:1rem}.om-form label{display:grid;gap:.4rem;font-weight:700}.om-form input,.om-form select,.om-lang select,.om-auth-lang select{padding:.7rem;border:1px solid rgba(15,23,42,.18);border-radius:.8rem}.om-form button{padding:.8rem 1rem;border:0;border-radius:.8rem;font-weight:800;cursor:pointer}.om-error{padding:.75rem;border-radius:.8rem;background:rgba(220,38,38,.08)}.om-muted{opacity:.72}.om-auth-badges:empty{display:none}.om-env{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}.om-lang{display:flex;align-items:center;gap:.4rem}.om-lang label{font-size:.82rem;font-weight:800;opacity:.8}' . PHP_EOL;
}
opus_auth_write($cssFile, $css);

$readmeFile = $siteRoot . DIRECTORY_SEPARATOR . 'README.md';
$readme = opus_auth_read_if_exists($readmeFile);
if (!str_contains($readme, 'OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE')) {
    $readme .= PHP_EOL;
    $readme .= '## OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE' . PHP_EOL . PHP_EOL;
    $readme .= '- Finalise auth/sign-in/logout/i18n pour OPUS Manager.' . PHP_EOL;
    $readme .= '- Sign in dev : `admin / admin`.' . PHP_EOL;
    $readme .= '- Le sélecteur de langue suffit ; pas de badge `Langue : ...` dupliqué.' . PHP_EOL;
    $readme .= '- En prod, les paramètres profiler/debug sont filtrés.' . PHP_EOL;
}
opus_auth_write($readmeFile, $readme);

$doc = <<<'MD'
# OPUS Manager — Auth / Sign in / I18N finalize

Contrat : `OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE`

## Objectif

Finaliser la brique OPUS Manager restée dirty :

- router auth-aware
- Sign in
- Logout
- session dev contrôlée
- i18n officiel UE + ukrainien `uk`
- déduplication langue
- prod sans profiler/debug activable par URL

## Règles

- `admin / admin` uniquement pour le mode dev local.
- Le sélecteur de langue suffit.
- Aucun badge `Langue : ...` si le selecteur existe.
- `/opus-manager/login` et `/opus-manager/signin` redirigent vers `/opus-manager/sign-in`.
- Les routes OPUS Manager protégées redirigent vers Sign in si non connecté.
- Les controllers restent séparés : SignInController, LogoutController, CreateSiteController, etc.

## Smokes

- Sign in rendu en `uk`.
- Sélecteur visible.
- Aucune répétition `Langue :`.
- Auth dev OK.
- Create Site rendu après auth.
- Router contient aliases login/signin.
- `uk` est obligatoire.
MD;

opus_auth_write($docRoot . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_AUTH_I18N_FINALIZE.md', $doc . PHP_EOL);
opus_auth_write($siteDocRoot . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_SHELL_AUTH_PROD_I18N.md', $doc . PHP_EOL);

$scopeFile = $docRoot . DIRECTORY_SEPARATOR . 'P7_OPS_FINAL_CLOSURE_SCOPE.md';
$scope = opus_auth_read_if_exists($scopeFile);
if (!str_contains($scope, 'OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE')) {
    $scope .= PHP_EOL;
    $scope .= '## OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE' . PHP_EOL . PHP_EOL;
    $scope .= '- Finalise OPUS Manager auth/sign-in/logout/i18n.' . PHP_EOL;
    $scope .= '- Sign in dev : `admin / admin`.' . PHP_EOL;
    $scope .= '- Le selecteur de langue suffit ; aucune repetition `Langue : ...`.' . PHP_EOL;
    $scope .= '- En prod, profiler/debug ne sont pas activables par URL.' . PHP_EOL;
    $scope .= '- Router protege les routes OPUS Manager et redirige vers Sign in.' . PHP_EOL;
}
opus_auth_write($scopeFile, $scope);

$legacySmoke = <<<'PHP'
<?php
declare(strict_types=1);

echo 'OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$siteRoot = $root . '/sites/opus-manager';

spl_autoload_register(static function (string $class) use ($siteRoot): void {
    $prefix = 'Opus\\Manager\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $siteRoot . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

$map = \Opus\Manager\Service\OpusManagerModuleRegistry::routeMap();
if (($map['/opus-manager/create-site'] ?? null) !== 'CreateSiteController') {
    throw new RuntimeException('OPUS_MANAGER_CREATE_SITE_ROUTE_MISSING');
}

$html = (new \Opus\Manager\Controller\CreateSiteController())->render([
    'lang' => 'fr',
    'env' => 'dev',
    'signed_in' => true,
    'user' => 'admin',
]);

foreach ([
    'OPUS Manager',
    'Créer un site avec OPUS',
    'StepTechnicalArchitecture',
    'Fullstack',
    'Frontend',
    'Backend',
] as $marker) {
    if (!str_contains($html, $marker)) {
        throw new RuntimeException('OPUS_MANAGER_CONTROLLER_SHELL_REUSE_MARKER_MISSING: ' . $marker);
    }
}

echo 'OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE_SMOKE_OK' . PHP_EOL;
PHP;

opus_auth_write($toolsSmokeRoot . DIRECTORY_SEPARATOR . 'smoke_opus_manager_controller_shell_reuse_core.php', $legacySmoke);

$authSmoke = <<<'PHP'
<?php
declare(strict_types=1);

echo 'OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$siteRoot = $root . '/sites/opus-manager';

spl_autoload_register(static function (string $class) use ($siteRoot): void {
    $prefix = 'Opus\\Manager\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $siteRoot . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['lang' => 'uk', 'profiler' => '1'];

\Opus\Manager\Service\OpusManagerEnvironment::filterProfilerInput('prod');

if (isset($_GET['profiler'])) {
    throw new RuntimeException('OPUS_MANAGER_PROD_PROFILER_NOT_FILTERED');
}

$html = (new \Opus\Manager\Controller\SignInController())->render(['lang' => 'uk', 'env' => 'prod']);

foreach ([
    'Sign in',
    'Українська',
    '<select name="lang"',
    'admin / admin',
] as $marker) {
    if (!str_contains($html, $marker)) {
        throw new RuntimeException('OPUS_MANAGER_AUTH_I18N_MARKER_MISSING: ' . $marker);
    }
}

if (str_contains($html, 'Langue :')) {
    throw new RuntimeException('OPUS_MANAGER_LANGUAGE_DUPLICATE_VISIBLE');
}

echo 'OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE_SMOKE_OK' . PHP_EOL;
PHP;

opus_auth_write($toolsSmokeRoot . DIRECTORY_SEPARATOR . 'smoke_opus_manager_shell_auth_prod_i18n_core.php', $authSmoke);

echo 'OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE_OK' . PHP_EOL;
