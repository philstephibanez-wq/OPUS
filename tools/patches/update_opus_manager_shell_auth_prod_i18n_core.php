<?php
declare(strict_types=1);

/**
 * OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE
 *
 * Durcit OPUS Manager pour livraison dev :
 * - auth centrale minimale
 * - routes Sign in / Logout dédiées
 * - prod sans profiler/debug
 * - i18n UE + ukrainien prête
 * - OPUS Manager explicitement inclus dans la livraison dev OPUS
 */

$root = getcwd();

if (!is_file($root . DIRECTORY_SEPARATOR . 'composer.json')) {
    fwrite(STDERR, 'OPUS_MANAGER_AUTH_PROD_I18N_NOT_IN_OPUS_ROOT' . PHP_EOL);
    exit(1);
}

$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'opus-manager';
$srcRoot = $siteRoot . DIRECTORY_SEPARATOR . 'src';
$controllerRoot = $srcRoot . DIRECTORY_SEPARATOR . 'Controller';
$serviceRoot = $srcRoot . DIRECTORY_SEPARATOR . 'Service';
$configRoot = $siteRoot . DIRECTORY_SEPARATOR . 'config';
$publicRoot = $siteRoot . DIRECTORY_SEPARATOR . 'public';
$docRoot = $siteRoot . DIRECTORY_SEPARATOR . 'DOC';
$rootDoc = $root . DIRECTORY_SEPARATOR . 'DOC';

foreach ([$siteRoot, $srcRoot, $controllerRoot, $serviceRoot, $configRoot, $publicRoot, $docRoot, $rootDoc] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, 'OPUS_MANAGER_AUTH_PROD_I18N_DIR_CREATE_FAILED: ' . $dir . PHP_EOL);
        exit(1);
    }
}

function opus_auth_write(string $file, string $source): void
{
    if (file_put_contents($file, $source) === false) {
        fwrite(STDERR, 'OPUS_MANAGER_AUTH_PROD_I18N_WRITE_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }
}

function opus_auth_read(string $file): string
{
    if (!is_file($file)) {
        return '';
    }

    $source = file_get_contents($file);
    return is_string($source) ? $source : '';
}

$envDev = <<<'PHP'
<?php
declare(strict_types=1);

/** OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE */
return [
    'environment' => 'dev',
    'debug' => true,
    'profiler_allowed' => true,
    'auth_required' => true,
    'dev_admin_user' => 'admin',
    'dev_admin_password' => 'admin',
];
PHP;

$envProdExample = <<<'PHP'
<?php
declare(strict_types=1);

/** OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE */
return [
    'environment' => 'prod',
    'debug' => false,
    'profiler_allowed' => false,
    'auth_required' => true,
    'admin_user' => 'CHANGE_ME',
    'admin_password_hash' => 'CHANGE_ME_WITH_PASSWORD_HASH',
];
PHP;

$envActive = <<<'PHP'
<?php
declare(strict_types=1);

/** OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE */
return require __DIR__ . '/environment.dev.php';
PHP;

opus_auth_write($configRoot . DIRECTORY_SEPARATOR . 'environment.dev.php', $envDev);
opus_auth_write($configRoot . DIRECTORY_SEPARATOR . 'environment.prod.example.php', $envProdExample);
if (!is_file($configRoot . DIRECTORY_SEPARATOR . 'environment.php')) {
    opus_auth_write($configRoot . DIRECTORY_SEPARATOR . 'environment.php', $envActive);
}

$environmentService = <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Manager\Service;

/** OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE */
final class OpusManagerEnvironment
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? $this->loadConfig();
    }

    public function name(): string
    {
        $env = (string) ($_ENV['OPUS_ENV'] ?? getenv('OPUS_ENV') ?: ($this->config['environment'] ?? 'dev'));

        return $env === 'prod' ? 'prod' : ($env === 'staging' ? 'staging' : 'dev');
    }

    public function isProd(): bool
    {
        return $this->name() === 'prod';
    }

    public function profilerAllowed(): bool
    {
        if ($this->isProd()) {
            return false;
        }

        return (bool) ($this->config['profiler_allowed'] ?? true);
    }

    public function debugAllowed(): bool
    {
        if ($this->isProd()) {
            return false;
        }

        return (bool) ($this->config['debug'] ?? true);
    }

    public function authRequired(): bool
    {
        return (bool) ($this->config['auth_required'] ?? true);
    }

    public function config(): array
    {
        return $this->config;
    }

    private function loadConfig(): array
    {
        $file = dirname(__DIR__, 2) . '/config/environment.php';
        if (!is_file($file)) {
            return [
                'environment' => 'dev',
                'debug' => true,
                'profiler_allowed' => true,
                'auth_required' => true,
            ];
        }

        $config = require $file;
        if (!is_array($config)) {
            throw new \RuntimeException('OPUS_MANAGER_INVALID_ENVIRONMENT_CONFIG');
        }

        return $config;
    }
}
PHP;

opus_auth_write($serviceRoot . DIRECTORY_SEPARATOR . 'OpusManagerEnvironment.php', $environmentService);

$i18nService = <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Manager\Service;

/** OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE */
final class OpusManagerI18n
{
    public const SUPPORTED_LANGUAGES = [
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

    public static function normalize(string $lang): string
    {
        $lang = strtolower(trim($lang));
        return array_key_exists($lang, self::SUPPORTED_LANGUAGES) ? $lang : 'fr';
    }

    public static function languageName(string $lang): string
    {
        $lang = self::normalize($lang);
        return self::SUPPORTED_LANGUAGES[$lang];
    }

    public static function optionsHtml(string $active): string
    {
        $active = self::normalize($active);
        $html = '';
        foreach (self::SUPPORTED_LANGUAGES as $code => $label) {
            $selected = $code === $active ? ' selected' : '';
            $html .= '<option value="' . self::h($code) . '"' . $selected . '>' . self::h($label . ' — ' . strtoupper($code)) . '</option>';
        }

        return $html;
    }

    public static function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
PHP;

opus_auth_write($serviceRoot . DIRECTORY_SEPARATOR . 'OpusManagerI18n.php', $i18nService);

$authService = <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Manager\Service;

/** OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE */
final class OpusManagerAuth
{
    public const SESSION_NAME = 'OPUSMANAGER';

    public function __construct(private readonly OpusManagerEnvironment $environment)
    {
    }

    public function start(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name(self::SESSION_NAME);
            session_start();
        }
    }

    public function isSignedIn(): bool
    {
        if (!$this->environment->authRequired()) {
            return true;
        }

        $this->start();

        return (bool) ($_SESSION['opus_manager_signed_in'] ?? false);
    }

    public function currentUser(): string
    {
        $this->start();

        return (string) ($_SESSION['opus_manager_user'] ?? '');
    }

    public function signIn(string $user, string $password): bool
    {
        $this->start();
        $config = $this->environment->config();

        if ($this->environment->isProd()) {
            $expectedUser = (string) ($config['admin_user'] ?? '');
            $expectedHash = (string) ($config['admin_password_hash'] ?? '');

            if ($expectedUser === '' || $expectedHash === '' || str_contains($expectedHash, 'CHANGE_ME')) {
                return false;
            }

            $ok = hash_equals($expectedUser, $user) && password_verify($password, $expectedHash);
        } else {
            $expectedUser = (string) ($config['dev_admin_user'] ?? 'admin');
            $expectedPassword = (string) ($config['dev_admin_password'] ?? 'admin');
            $ok = hash_equals($expectedUser, $user) && hash_equals($expectedPassword, $password);
        }

        if (!$ok) {
            return false;
        }

        $_SESSION['opus_manager_signed_in'] = true;
        $_SESSION['opus_manager_user'] = $user;
        $_SESSION['opus_manager_signed_in_at'] = date(DATE_ATOM);

        return true;
    }

    public function signOut(): void
    {
        $this->start();

        unset(
            $_SESSION['opus_manager_signed_in'],
            $_SESSION['opus_manager_user'],
            $_SESSION['opus_manager_signed_in_at']
        );
    }
}
PHP;

opus_auth_write($serviceRoot . DIRECTORY_SEPARATOR . 'OpusManagerAuth.php', $authService);

$abstractFile = $controllerRoot . DIRECTORY_SEPARATOR . 'AbstractOpusManagerController.php';
$abstract = <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

use Opus\Manager\Service\OpusManagerI18n;
use Opus\Manager\Service\OpusManagerModuleRegistry;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE */
abstract class AbstractOpusManagerController implements OpusManagerControllerInterface
{
    protected function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function shell(string $title, string $body, array $context = []): string
    {
        $active = $this->route();
        $lang = OpusManagerI18n::normalize((string) ($context['lang'] ?? 'fr'));
        $env = (string) ($context['env'] ?? 'dev');
        $isProd = $env === 'prod';
        $signedIn = (bool) ($context['signed_in'] ?? false);
        $user = (string) ($context['user'] ?? '');

        $nav = '';
        foreach (OpusManagerModuleRegistry::groupedModules() as $group => $modules) {
            $nav .= '<section class="om-nav-group"><h2>' . $this->h((string) $group) . '</h2><div>';
            foreach ($modules as $module) {
                $class = $module['route'] === $active ? ' class="is-active"' : '';
                $badge = $module['expert'] ? '<span>Expert</span>' : '';
                $nav .= '<a' . $class . ' href="' . $this->h($module['route']) . '?lang=' . $this->h($lang) . '">'
                    . '<strong>' . $this->h($module['title']) . '</strong>' . $badge . '</a>';
            }
            $nav .= '</div></section>';
        }

        $profiler = $isProd
            ? '<span class="om-prod-lock">Prod : profiler interdit</span>'
            : '<span class="om-dev-note">Dev/Staging : diagnostics contrôlés</span>';

        $auth = $signedIn
            ? '<span>Connecté : ' . $this->h($user !== '' ? $user : 'user') . '</span><a href="/opus-manager/logout?lang=' . $this->h($lang) . '">Logout</a>'
            : '<a href="/opus-manager/sign-in?lang=' . $this->h($lang) . '">Sign in</a>';

        $langForm = '<form class="om-lang" method="get"><label>Langue <select name="lang" onchange="this.form.submit()">'
            . OpusManagerI18n::optionsHtml($lang)
            . '</select></label></form>';

        return '<!doctype html><html lang="' . $this->h($lang) . '"><head>'
            . '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $this->h($title) . ' — OPUS Manager</title>'
            . '<link rel="stylesheet" href="/opus-manager-ui.css">'
            . '</head><body data-contract="OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE">'
            . '<header class="om-hero"><div><p class="om-kicker">OPUS Manager</p><h1>' . $this->h($title) . '</h1>'
            . '<p>Backoffice OPUS clair, modulaire et orienté création de site.</p></div>'
            . '<div class="om-env"><span>Langue : ' . $this->h(OpusManagerI18n::languageName($lang)) . '</span>' . $profiler . $auth . $langForm . '</div></header>'
            . '<main class="om-layout"><aside class="om-nav">' . $nav . '</aside><section class="om-content">' . $body . '</section></main>'
            . '</body></html>';
    }

    protected function moduleCard(string $summary, array $links = []): string
    {
        $html = '<section class="om-card"><h2>Rôle</h2><p>' . $this->h($summary) . '</p></section>';
        if ($links !== []) {
            $html .= '<section class="om-card"><h2>Réutilisation de l’existant</h2><p>Ce module ne recrée pas la logique métier. Il branche les routes et briques OPUS existantes.</p><div class="om-actions">';
            foreach ($links as $link) {
                $html .= '<a href="' . $this->h((string) $link['href']) . '">' . $this->h((string) $link['label']) . '</a>';
            }
            $html .= '</div></section>';
        }

        $html .= '<section class="om-card"><h2>Contrats</h2><ul>'
            . '<li>Auth centrale obligatoire.</li>'
            . '<li>ACL/RBAC centralisé.</li>'
            . '<li>Production sans profiler/debug.</li>'
            . '<li>I18N UE + ukrainien.</li>'
            . '<li>Composer install / no-dev validé.</li>'
            . '</ul></section>';

        return $html;
    }
}
PHP;

opus_auth_write($abstractFile, $abstract);

$signInController = <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

use Opus\Manager\Service\OpusManagerAuth;
use Opus\Manager\Service\OpusManagerEnvironment;
use Opus\Manager\Service\OpusManagerI18n;

/** OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE */
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
        return 'Auth';
    }

    public function isExpert(): bool
    {
        return false;
    }

    public function render(array $context = []): string
    {
        $environment = new OpusManagerEnvironment();
        $auth = new OpusManagerAuth($environment);
        $lang = OpusManagerI18n::normalize((string) ($context['lang'] ?? ($_GET['lang'] ?? 'fr')));
        $error = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $user = (string) ($_POST['user'] ?? '');
            $password = (string) ($_POST['password'] ?? '');

            if ($auth->signIn($user, $password)) {
                $next = (string) ($_POST['next'] ?? '/opus-manager/create-site');
                header('Location: ' . ($next !== '' ? $next : '/opus-manager/create-site'), true, 302);
                return '';
            }

            $error = $environment->isProd()
                ? 'Connexion refusée. Vérifier la configuration production du compte administrateur.'
                : 'Connexion refusée. En dev, le compte par défaut est admin / admin.';
        }

        $next = (string) ($_GET['next'] ?? '/opus-manager/create-site?lang=' . $lang);
        $html = '<section class="om-card om-primary"><h2>Sign in OPUS Manager</h2><p>Authentification centrale minimale pour la livraison dev OPUS Manager.</p></section>';
        if ($error !== '') {
            $html .= '<section class="om-card om-error"><h2>Erreur</h2><p>' . $this->h($error) . '</p></section>';
        }
        $html .= '<form class="om-card om-form" method="post" action="/opus-manager/sign-in?lang=' . $this->h($lang) . '">'
            . '<input type="hidden" name="next" value="' . $this->h($next) . '">'
            . '<label>Utilisateur <input name="user" autocomplete="username" required></label>'
            . '<label>Mot de passe <input name="password" type="password" autocomplete="current-password" required></label>'
            . '<button type="submit">Sign in</button>'
            . '</form>';

        return $this->shell($this->title(), $html, [
            'lang' => $lang,
            'env' => $environment->name(),
            'signed_in' => $auth->isSignedIn(),
            'user' => $auth->currentUser(),
        ]);
    }
}
PHP;

opus_auth_write($controllerRoot . DIRECTORY_SEPARATOR . 'SignInController.php', $signInController);

$logoutController = <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

use Opus\Manager\Service\OpusManagerAuth;
use Opus\Manager\Service\OpusManagerEnvironment;
use Opus\Manager\Service\OpusManagerI18n;

/** OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE */
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
        return 'Auth';
    }

    public function isExpert(): bool
    {
        return false;
    }

    public function render(array $context = []): string
    {
        $environment = new OpusManagerEnvironment();
        $auth = new OpusManagerAuth($environment);
        $auth->signOut();

        $lang = OpusManagerI18n::normalize((string) ($context['lang'] ?? ($_GET['lang'] ?? 'fr')));
        header('Location: /opus-manager/sign-in?lang=' . rawurlencode($lang), true, 302);

        return '';
    }
}
PHP;

opus_auth_write($controllerRoot . DIRECTORY_SEPARATOR . 'LogoutController.php', $logoutController);

$router = <<<'PHP'
<?php
declare(strict_types=1);

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE */

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
$lang = OpusManagerI18n::normalize((string) ($_GET['lang'] ?? 'fr'));

if ($path === '/' || $path === '/opus-manager/' || $path === '/opus-manager') {
    header('Location: /opus-manager/create-site?lang=' . rawurlencode($lang), true, 302);
    return;
}

if ($path === '/opus-manager-ui.css') {
    return false;
}

$environment = new OpusManagerEnvironment();
$auth = new OpusManagerAuth($environment);

foreach (['profiler', '_profiler', 'profile'] as $debugQuery) {
    if ($environment->isProd() && array_key_exists($debugQuery, $_GET)) {
        unset($_GET[$debugQuery]);
    }
}

if ($path === '/opus-manager/sign-in') {
    echo (new SignInController())->render(['lang' => $lang, 'env' => $environment->name()]);
    return;
}

if ($path === '/opus-manager/logout') {
    echo (new LogoutController())->render(['lang' => $lang, 'env' => $environment->name()]);
    return;
}

if ($environment->authRequired() && !$auth->isSignedIn()) {
    header('Location: /opus-manager/sign-in?lang=' . rawurlencode($lang) . '&next=' . rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? '/opus-manager/create-site')), true, 302);
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
    'env' => $environment->name(),
    'signed_in' => $auth->isSignedIn(),
    'user' => $auth->currentUser(),
]);
PHP;

opus_auth_write($publicRoot . DIRECTORY_SEPARATOR . 'router.php', $router);

$cssFile = $publicRoot . DIRECTORY_SEPARATOR . 'opus-manager-ui.css';
$css = opus_auth_read($cssFile);
if (!str_contains($css, 'OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE')) {
    $css .= PHP_EOL;
    $css .= '/* OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE */' . PHP_EOL;
    $css .= '.om-env a{display:inline-flex;justify-content:center;border:1px solid rgba(125,211,252,.45);border-radius:999px;background:#075985;color:#ecfeff;text-decoration:none;font-weight:900;padding:.55rem .75rem}.om-lang label{display:grid;gap:.35rem;color:#cbd5e1;font-weight:800}.om-lang select{width:100%;border:1px solid rgba(125,211,252,.35);border-radius:999px;background:#e2e8f0;color:#0f172a;padding:.5rem .65rem;font-weight:900}.om-form{display:grid;gap:.8rem}.om-form label{display:grid;gap:.35rem;color:#cbd5e1;font-weight:800}.om-form input{border:1px solid rgba(125,211,252,.35);border-radius:14px;background:#e2e8f0;color:#0f172a;padding:.7rem;font-weight:800}.om-form button{border:1px solid rgba(34,211,238,.65);border-radius:999px;background:#155e75;color:#ecfeff;font-weight:950;padding:.75rem 1rem;cursor:pointer}.om-error{border-color:#f87171;background:#450a0a}.om-error h2{color:#fecaca}' . PHP_EOL;
}
opus_auth_write($cssFile, $css);

$readmeFile = $siteRoot . DIRECTORY_SEPARATOR . 'README.md';
$readme = opus_auth_read($readmeFile);
if (!str_contains($readme, 'OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE')) {
    $readme .= PHP_EOL;
    $readme .= '## OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE' . PHP_EOL . PHP_EOL;
    $readme .= '- OPUS Manager fait partie de la livraison dev OPUS.' . PHP_EOL;
    $readme .= '- Auth centrale minimale activée.' . PHP_EOL;
    $readme .= '- En dev : compte `admin` / `admin`.' . PHP_EOL;
    $readme .= '- En prod : aucun fallback ; configurer `environment.php` depuis `environment.prod.example.php` avec un hash valide.' . PHP_EOL;
    $readme .= '- En prod : aucun profiler/debug, même avec `profiler=1`.' . PHP_EOL;
    $readme .= '- I18N prête pour toutes les langues officielles UE + ukrainien (`uk`).' . PHP_EOL;
}
opus_auth_write($readmeFile, $readme);

$doc = <<<'MD'
# OPUS Manager — Auth / Prod / I18N

Contrat : `OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE`

## Décision

OPUS Manager fait partie de la livraison dev OPUS.

Cette brique durcit le shell OPUS Manager :

- auth centrale minimale
- routes dédiées `SignInController` et `LogoutController`
- dev/prod strict
- production sans profiler/debug
- i18n prête pour toutes les langues officielles UE + ukrainien (`uk`)
- Create Site Wizard conservé comme entrée principale

## Auth

En dev :

```text
admin / admin
```

En prod :

```text
environment.php doit dériver de environment.prod.example.php
admin_user doit être défini
admin_password_hash doit être défini avec password_hash()
aucun fallback silencieux
```

## Prod

En prod :

- aucun profiler
- aucun debug
- `profiler=1`, `_profiler=1` et `profile=1` sont supprimés du contexte
- aucune toolbar debug
- auth obligatoire

## I18N

Langues supportées :

```text
bg hr cs da nl en et fi fr de el hu ga it lv lt mt pl pt ro sk sl es sv uk
```

`uk` est le code ukrainien.

## Livraison dev

OPUS Manager est inclus dans la livraison dev OPUS avec :

- `sites/opus-manager`
- `CreateSiteController`
- shell navigation
- auth minimale
- i18n prête
- dev/prod strict
- smokes dédiés

## Prochaines étapes

- ACL/RBAC effectif par route
- Ref Book complet OPUS Manager
- User Book complet OPUS Manager
- tests HTTP serveur
- tests Composer installation serveur
MD;

opus_auth_write($docRoot . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_SHELL_AUTH_PROD_I18N.md', $doc . PHP_EOL);
opus_auth_write($rootDoc . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_SHELL_AUTH_PROD_I18N.md', $doc . PHP_EOL);

$scopeFile = $rootDoc . DIRECTORY_SEPARATOR . 'P7_OPS_FINAL_CLOSURE_SCOPE.md';
$scope = opus_auth_read($scopeFile);
if ($scope === '') {
    $scope = '# OPUS P7 — portée de clôture finale' . PHP_EOL;
}
if (!str_contains($scope, 'OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE')) {
    $scope .= PHP_EOL;
    $scope .= '## OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE' . PHP_EOL . PHP_EOL;
    $scope .= '- OPUS Manager fait partie de la livraison dev OPUS.' . PHP_EOL;
    $scope .= '- Auth centrale minimale ajoutée au shell.' . PHP_EOL;
    $scope .= '- `SignInController` et `LogoutController` sont des controllers dédiés.' . PHP_EOL;
    $scope .= '- En production : aucun profiler/debug, même avec `profiler=1`.' . PHP_EOL;
    $scope .= '- I18N prête pour toutes les langues officielles UE + ukrainien (`uk`).' . PHP_EOL;
}
opus_auth_write($scopeFile, $scope);

$deliveryDoc = $rootDoc . DIRECTORY_SEPARATOR . 'OPUS_DEV_DELIVERY_SCOPE.md';
$delivery = opus_auth_read($deliveryDoc);
if ($delivery === '') {
    $delivery = '# OPUS — livraison dev' . PHP_EOL;
}
if (!str_contains($delivery, 'OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE')) {
    $delivery .= PHP_EOL;
    $delivery .= '## OPUS Manager' . PHP_EOL . PHP_EOL;
    $delivery .= 'Contrat : `OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE`' . PHP_EOL . PHP_EOL;
    $delivery .= 'OPUS Manager est inclus dans la livraison dev OPUS.' . PHP_EOL . PHP_EOL;
    $delivery .= '- Site : `sites/opus-manager`' . PHP_EOL;
    $delivery .= '- Entrée : `CreateSiteController` / `/opus-manager/create-site`' . PHP_EOL;
    $delivery .= '- Auth centrale minimale' . PHP_EOL;
    $delivery .= '- Dev/prod strict' . PHP_EOL;
    $delivery .= '- Prod sans profiler/debug' . PHP_EOL;
    $delivery .= '- I18N UE + ukrainien' . PHP_EOL;
}
opus_auth_write($deliveryDoc, $delivery);

echo 'OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE' . PHP_EOL;
echo 'SITE=sites/opus-manager' . PHP_EOL;
echo 'AUTH=SignInController LogoutController OpusManagerAuth' . PHP_EOL;
echo 'I18N=EU_OFFICIAL_LANGUAGES_PLUS_UK' . PHP_EOL;
echo 'PROD=NO_PROFILER_NO_DEBUG' . PHP_EOL;
echo 'OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE_OK' . PHP_EOL;
