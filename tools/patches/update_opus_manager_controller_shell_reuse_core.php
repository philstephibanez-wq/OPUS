<?php
declare(strict_types=1);

/**
 * OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE
 *
 * Crée un shell OPUS Manager réutilisant l'existant.
 * Règles :
 * - Create Site Wizard en entrée principale.
 * - Un controller par fonctionnalité/page.
 * - LSTSAR et ODBC sont branchés comme modules, pas recréés.
 * - Briques OPUS uniquement.
 */

$root = getcwd();
$workspace = 'H:\\MAESTRO_WORKSPACE';

if (!is_file($root . DIRECTORY_SEPARATOR . 'composer.json')) {
    fwrite(STDERR, 'OPUS_MANAGER_SHELL_NOT_IN_OPUS_ROOT' . PHP_EOL);
    exit(1);
}

$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'opus-manager';
$srcRoot = $siteRoot . DIRECTORY_SEPARATOR . 'src';
$controllerRoot = $srcRoot . DIRECTORY_SEPARATOR . 'Controller';
$serviceRoot = $srcRoot . DIRECTORY_SEPARATOR . 'Service';
$publicRoot = $siteRoot . DIRECTORY_SEPARATOR . 'public';
$docRoot = $siteRoot . DIRECTORY_SEPARATOR . 'DOC';
$rootDoc = $root . DIRECTORY_SEPARATOR . 'DOC';

foreach ([$siteRoot, $srcRoot, $controllerRoot, $serviceRoot, $publicRoot, $docRoot, $rootDoc] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, 'OPUS_MANAGER_SHELL_DIR_CREATE_FAILED: ' . $dir . PHP_EOL);
        exit(1);
    }
}

function opus_shell_write(string $file, string $source): void
{
    if (file_put_contents($file, $source) === false) {
        fwrite(STDERR, 'OPUS_MANAGER_SHELL_WRITE_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }
}

function opus_shell_read(string $file): string
{
    if (!is_file($file)) {
        return '';
    }

    $source = file_get_contents($file);
    return is_string($source) ? $source : '';
}

function opus_shell_rel(string $root, string $file): string
{
    $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return str_starts_with($file, $prefix)
        ? str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($prefix)))
        : str_replace(DIRECTORY_SEPARATOR, '/', $file);
}

$controllers = [
    'OpusManagerDashboardController' => [
        'title' => 'Dashboard',
        'route' => '/opus-manager',
        'group' => 'Accueil',
        'summary' => 'Vue d’ensemble du backoffice OPUS Manager.',
        'expert' => false,
        'links' => [],
    ],
    'CreateSiteController' => [
        'title' => 'Créer un site',
        'route' => '/opus-manager/create-site',
        'group' => 'Créer',
        'summary' => 'Assistant principal pour créer un site avec OPUS.',
        'expert' => false,
        'links' => [],
    ],
    'CreatePackageController' => [
        'title' => 'Créer un package',
        'route' => '/opus-manager/create-package',
        'group' => 'Créer',
        'summary' => 'Préparation contrôlée d’un package OPUS via recettes Composer.',
        'expert' => false,
        'links' => [],
    ],
    'UsersManagerController' => [
        'title' => 'Users / Identity',
        'route' => '/opus-manager/users',
        'group' => 'Identité',
        'summary' => 'Utilisateurs, comptes, identité et état des accès.',
        'expert' => true,
        'links' => [],
    ],
    'AclManagerController' => [
        'title' => 'ACL',
        'route' => '/opus-manager/acl',
        'group' => 'Identité',
        'summary' => 'Permissions, policies et droits par module.',
        'expert' => true,
        'links' => [],
    ],
    'RbacManagerController' => [
        'title' => 'RBAC',
        'route' => '/opus-manager/rbac',
        'group' => 'Identité',
        'summary' => 'Rôles métiers, héritage et assignations.',
        'expert' => true,
        'links' => [],
    ],
    'SsoManagerController' => [
        'title' => 'SSO',
        'route' => '/opus-manager/sso',
        'group' => 'Identité',
        'summary' => 'Providers SSO et configuration de fédération d’identité.',
        'expert' => true,
        'links' => [],
    ],
    'SessionsManagerController' => [
        'title' => 'Sessions',
        'route' => '/opus-manager/sessions',
        'group' => 'Identité',
        'summary' => 'Sessions actives, révocation et contrôle.',
        'expert' => true,
        'links' => [],
    ],
    'AuthAuditController' => [
        'title' => 'Auth Audit',
        'route' => '/opus-manager/auth-audit',
        'group' => 'Identité',
        'summary' => 'Audit des connexions, déconnexions et décisions d’accès.',
        'expert' => true,
        'links' => [],
    ],
    'FsmManagerController' => [
        'title' => 'FSM',
        'route' => '/opus-manager/fsm',
        'group' => 'Moteurs',
        'summary' => 'Machines d’état, transitions et diagnostics FSM.',
        'expert' => true,
        'links' => [['label' => 'Route OPS FSM existante', 'href' => '/opus-lstsar-manager/fsm']],
    ],
    'ClManagerController' => [
        'title' => 'CL',
        'route' => '/opus-manager/cl',
        'group' => 'Moteurs',
        'summary' => 'CL et orchestration des couches OPUS associées.',
        'expert' => true,
        'links' => [['label' => 'Route OPS CL existante', 'href' => '/opus-lstsar-manager/cl']],
    ],
    'ModelsManagerController' => [
        'title' => 'Models',
        'route' => '/opus-manager/models',
        'group' => 'Moteurs',
        'summary' => 'Modèles, schémas, objets typés et diagnostics.',
        'expert' => true,
        'links' => [['label' => 'Route OPS Models existante', 'href' => '/opus-lstsar-manager/models']],
    ],
    'DatabaseManagerController' => [
        'title' => 'Database',
        'route' => '/opus-manager/database',
        'group' => 'Données',
        'summary' => 'Tables, colonnes, contraintes, types attendus et sources.',
        'expert' => true,
        'links' => [],
    ],
    'OdbcManagerController' => [
        'title' => 'ODBC Manager',
        'route' => '/opus-manager/odbc',
        'group' => 'Données',
        'summary' => 'DSN, drivers, tests de connexion et contrats ODBC.',
        'expert' => true,
        'links' => [['label' => 'ODBC Manager OPS existant', 'href' => '/opus-lstsar-manager/odbc-manager']],
    ],
    'LstsarManagerController' => [
        'title' => 'LSTSAR Manager',
        'route' => '/opus-manager/lstsar',
        'group' => 'Données',
        'summary' => 'Load / Secure / Transform / Store / Audit.',
        'expert' => true,
        'links' => [
            ['label' => 'Chaîne LSTSAR OPS existante', 'href' => '/opus-lstsar-manager/chain'],
            ['label' => 'Operations LSTSAR OPS existantes', 'href' => '/opus-lstsar-manager/operations'],
        ],
    ],
    'ComposerManagerController' => [
        'title' => 'Composer',
        'route' => '/opus-manager/composer',
        'group' => 'Installation',
        'summary' => 'Composer validate/install/no-dev/autoload et packages.',
        'expert' => true,
        'links' => [],
    ],
    'RefBookController' => [
        'title' => 'Ref Book',
        'route' => '/opus-manager/ref-book',
        'group' => 'Documentation',
        'summary' => 'Documentation technique, API, classes, routes et manifests.',
        'expert' => false,
        'links' => [],
    ],
    'UserBookController' => [
        'title' => 'User Book',
        'route' => '/opus-manager/user-book',
        'group' => 'Documentation',
        'summary' => 'Documentation utilisateur, parcours, écrans et exploitation.',
        'expert' => false,
        'links' => [],
    ],
    'LogsController' => [
        'title' => 'Logs',
        'route' => '/opus-manager/logs',
        'group' => 'Exploitation',
        'summary' => 'Accès contrôlé aux journaux autorisés.',
        'expert' => true,
        'links' => [],
    ],
    'DiagnosticsController' => [
        'title' => 'Diagnostics',
        'route' => '/opus-manager/diagnostics',
        'group' => 'Exploitation',
        'summary' => 'Santé système, contrôles et diagnostics non sensibles.',
        'expert' => false,
        'links' => [['label' => 'Health OPS existant', 'href' => '/opus-lstsar-manager/health']],
    ],
];

$interface = <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
interface OpusManagerControllerInterface
{
    public function route(): string;

    public function title(): string;

    public function group(): string;

    public function isExpert(): bool;

    public function render(array $context = []): string;
}
PHP;

opus_shell_write($controllerRoot . DIRECTORY_SEPARATOR . 'OpusManagerControllerInterface.php', $interface);

$abstract = <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

use Opus\Manager\Service\OpusManagerModuleRegistry;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
abstract class AbstractOpusManagerController implements OpusManagerControllerInterface
{
    protected function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function shell(string $title, string $body, array $context = []): string
    {
        $active = $this->route();
        $lang = (string) ($context['lang'] ?? 'fr');
        $env = (string) ($context['env'] ?? 'dev');
        $isProd = $env === 'prod';

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

        return '<!doctype html><html lang="' . $this->h($lang) . '"><head>'
            . '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $this->h($title) . ' — OPUS Manager</title>'
            . '<link rel="stylesheet" href="/opus-manager-ui.css">'
            . '</head><body data-contract="OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE">'
            . '<header class="om-hero"><div><p class="om-kicker">OPUS Manager</p><h1>' . $this->h($title) . '</h1>'
            . '<p>Backoffice OPUS clair, modulaire et orienté création de site.</p></div>'
            . '<div class="om-env"><span>Langue : ' . $this->h($lang) . '</span>' . $profiler . '</div></header>'
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

opus_shell_write($controllerRoot . DIRECTORY_SEPARATOR . 'AbstractOpusManagerController.php', $abstract);

$registry = <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Manager\Service;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class OpusManagerModuleRegistry
{
    public static function modules(): array
    {
        return __MODULES__;
    }

    public static function groupedModules(): array
    {
        $groups = [];
        foreach (self::modules() as $module) {
            $groups[(string) $module['group']][] = $module;
        }

        return $groups;
    }

    public static function routeMap(): array
    {
        $map = [];
        foreach (self::modules() as $module) {
            $map[(string) $module['route']] = (string) $module['controller'];
        }

        return $map;
    }

    public static function primaryRoute(): string
    {
        return '/opus-manager/create-site';
    }
}
PHP;

$moduleExport = [];
foreach ($controllers as $class => $meta) {
    $moduleExport[] = [
        'controller' => $class,
        'title' => $meta['title'],
        'route' => $meta['route'],
        'group' => $meta['group'],
        'expert' => $meta['expert'],
        'summary' => $meta['summary'],
    ];
}
$registry = str_replace('__MODULES__', var_export($moduleExport, true), $registry);
opus_shell_write($serviceRoot . DIRECTORY_SEPARATOR . 'OpusManagerModuleRegistry.php', $registry);

foreach ($controllers as $class => $meta) {
    $links = var_export($meta['links'], true);
    $summary = var_export($meta['summary'], true);
    $title = var_export($meta['title'], true);
    $route = var_export($meta['route'], true);
    $group = var_export($meta['group'], true);
    $expert = $meta['expert'] ? 'true' : 'false';

    if ($class === 'CreateSiteController') {
        $body = <<<'PHP'
        $steps = [
            'StepIdentity' => 'Nom du site, propriétaire, domaine et contexte.',
            'StepSiteType' => 'Type de site : portail, backoffice, démo, documentation ou application métier.',
            'StepTemplate' => 'Choix du modèle OPUS.',
            'StepLanguages' => 'Langues officielles UE + ukrainien.',
            'StepModules' => 'Modules : Ref Book, User Book, ODBC, LSTSAR, auth, logs.',
            'StepSecurity' => 'Users, ACL/RBAC, SSO, politiques d’accès.',
            'StepData' => 'Tables, colonnes, types attendus, contraintes.',
            'StepOdbc' => 'DSN, drivers et tests ODBC si nécessaire.',
            'StepLstsar' => 'Load / Secure / Transform / Store / Audit si nécessaire.',
            'StepComposerInstall' => 'composer validate/install/no-dev/autoload.',
            'StepSmokeTests' => 'Tests post-installation.',
            'StepSummary' => 'Résumé utilisateur, rapport technique, liens Ref Book/User Book.',
        ];

        $html = '<section class="om-card om-primary"><h2>Créer un site avec OPUS</h2><p>Le wizard est l’entrée principale. Il masque la complexité technique et orchestre les briques OPUS existantes.</p></section>';
        $html .= '<section class="om-steps">';
        foreach ($steps as $step => $description) {
            $html .= '<article><strong>' . $this->h($step) . '</strong><p>' . $this->h($description) . '</p></article>';
        }
        $html .= '</section>';
        $html .= '<section class="om-card"><h2>Règle de réutilisation</h2><p>ODBC Manager, LSTSAR Manager, FSM, ACL/RBAC, SSO et Composer sont des modules orchestrés. Ils ne sont pas recréés dans le wizard.</p></section>';

        return $this->shell($this->title(), $html, $context);
PHP;
    } elseif ($class === 'OpusManagerDashboardController') {
        $body = <<<'PHP'
        $html = '<section class="om-card om-primary"><h2>Créer un site</h2><p>Pour un utilisateur, le premier parcours est le wizard de création de site.</p><div class="om-actions"><a href="/opus-manager/create-site">Démarrer le wizard</a></div></section>';
        $html .= '<section class="om-card"><h2>Administration</h2><p>Les modules techniques restent disponibles pour les administrateurs et experts, avec un controller dédié par fonctionnalité.</p></section>';
        return $this->shell($this->title(), $html, $context);
PHP;
    } else {
        $body = "        return \$this->shell(\$this->title(), \$this->moduleCard({$summary}, {$links}), \$context);";
    }

    $source = <<<PHP
<?php
declare(strict_types=1);

namespace Opus\\Manager\\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class {$class} extends AbstractOpusManagerController
{
    public function route(): string
    {
        return {$route};
    }

    public function title(): string
    {
        return {$title};
    }

    public function group(): string
    {
        return {$group};
    }

    public function isExpert(): bool
    {
        return {$expert};
    }

    public function render(array \$context = []): string
    {
{$body}
    }
}
PHP;

    opus_shell_write($controllerRoot . DIRECTORY_SEPARATOR . $class . '.php', $source);
}

$router = <<<'PHP'
<?php
declare(strict_types=1);

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */

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

use Opus\Manager\Service\OpusManagerModuleRegistry;

$path = rawurldecode((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/opus-manager'), PHP_URL_PATH) ?: '/opus-manager'));

if ($path === '/' || $path === '/opus-manager/') {
    header('Location: /opus-manager/create-site', true, 302);
    return;
}

if ($path === '/opus-manager-ui.css') {
    return false;
}

$routeMap = OpusManagerModuleRegistry::routeMap();
$controllerClass = $routeMap[$path] ?? null;

if ($controllerClass === null) {
    http_response_code(404);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>OPUS Manager — 404</title><link rel="stylesheet" href="/opus-manager-ui.css"></head><body><main class="om-content"><section class="om-card"><h1>Page OPUS Manager introuvable</h1><p>La route demandée n’est pas déclarée dans le shell.</p><p><a href="/opus-manager/create-site">Retour au wizard Créer un site</a></p></section></main></body></html>';
    return;
}

$fqcn = 'Opus\\Manager\\Controller\\' . $controllerClass;
$controller = new $fqcn();

$env = (string) ($_ENV['OPUS_ENV'] ?? getenv('OPUS_ENV') ?: 'dev');
if ($env === 'prod' && (isset($_GET['profiler']) || isset($_GET['_profiler']) || isset($_GET['profile']))) {
    unset($_GET['profiler'], $_GET['_profiler'], $_GET['profile']);
}

echo $controller->render([
    'lang' => (string) ($_GET['lang'] ?? 'fr'),
    'env' => $env,
]);
PHP;

opus_shell_write($publicRoot . DIRECTORY_SEPARATOR . 'router.php', $router);

$index = <<<'PHP'
<?php
declare(strict_types=1);

require __DIR__ . '/router.php';
PHP;
opus_shell_write($publicRoot . DIRECTORY_SEPARATOR . 'index.php', $index);

$css = <<<'CSS'
/* OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
:root{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#e5edf7;background:#07111f}
*{box-sizing:border-box}
body{margin:0;background:radial-gradient(circle at top left,rgba(56,189,248,.18),transparent 32rem),#07111f;color:#e5edf7}
a{color:#bae6fd}
.om-hero{width:min(1440px,calc(100vw - 2rem));margin:1rem auto;display:flex;justify-content:space-between;gap:1rem;align-items:stretch;padding:1.2rem;border:1px solid rgba(125,211,252,.35);border-radius:24px;background:linear-gradient(180deg,rgba(15,23,42,.98),rgba(2,6,23,.96));box-shadow:0 22px 70px rgba(0,0,0,.35)}
.om-kicker{margin:0 0 .35rem;color:#67e8f9;text-transform:uppercase;letter-spacing:.12em;font-weight:900}
.om-hero h1{margin:.1rem 0;font-size:clamp(1.8rem,3vw,3.2rem)}
.om-hero p{margin:.25rem 0;color:#cbd5e1}
.om-env{display:grid;align-content:center;gap:.5rem;min-width:18rem}
.om-env span{border:1px solid rgba(125,211,252,.28);border-radius:999px;padding:.55rem .75rem;background:#020617;font-weight:800}
.om-prod-lock{color:#fecaca!important;border-color:#f87171!important;background:#450a0a!important}
.om-dev-note{color:#bbf7d0!important;border-color:#4ade80!important;background:#052e16!important}
.om-layout{width:min(1440px,calc(100vw - 2rem));margin:0 auto 2rem;display:grid;grid-template-columns:minmax(18rem,24rem) minmax(0,1fr);gap:1rem}
.om-nav{display:grid;gap:.75rem;align-content:start}
.om-nav-group{border:1px solid rgba(125,211,252,.25);border-radius:20px;background:rgba(2,6,23,.75);padding:.75rem}
.om-nav-group h2{margin:0 0 .55rem;color:#93c5fd;font-size:.78rem;text-transform:uppercase;letter-spacing:.1em}
.om-nav-group div{display:grid;gap:.45rem}
.om-nav-group a{display:flex;justify-content:space-between;align-items:center;gap:.5rem;text-decoration:none;border:1px solid rgba(125,211,252,.22);border-radius:14px;padding:.65rem .75rem;background:#0f172a;color:#f8fafc}
.om-nav-group a.is-active{border-color:#22d3ee;background:#155e75}
.om-nav-group a span{font-size:.72rem;color:#fde68a;border:1px solid rgba(251,191,36,.45);border-radius:999px;padding:.15rem .4rem}
.om-content{min-width:0;display:grid;gap:1rem}
.om-card,.om-steps article{border:1px solid rgba(125,211,252,.25);border-radius:22px;background:rgba(15,23,42,.82);padding:1rem;box-shadow:0 16px 45px rgba(0,0,0,.22)}
.om-card h2,.om-steps strong{margin-top:0;color:#67e8f9}
.om-card p,.om-card li,.om-steps p{color:#cbd5e1}
.om-primary{border-color:#22d3ee;background:linear-gradient(180deg,rgba(8,47,73,.95),rgba(15,23,42,.85))}
.om-actions{display:flex;flex-wrap:wrap;gap:.6rem;margin-top:.8rem}
.om-actions a{display:inline-flex;border:1px solid rgba(125,211,252,.45);border-radius:999px;background:#075985;color:#ecfeff;text-decoration:none;font-weight:900;padding:.55rem .8rem}
.om-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(15rem,1fr));gap:1rem}
@media(max-width:1000px){.om-layout,.om-hero{grid-template-columns:1fr;display:grid}.om-env{min-width:0}}
CSS;

opus_shell_write($publicRoot . DIRECTORY_SEPARATOR . 'opus-manager-ui.css', $css);

$readme = <<<'MD'
# OPUS Manager

Contrat : `OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE`

OPUS Manager est le backoffice central OPUS.

L’entrée principale utilisateur est :

```text
Créer un site avec OPUS
```

Les modules techniques restent disponibles derrière le shell, avec un controller dédié par fonctionnalité.

## Démarrage local

```text
php -S 127.0.0.1:8079 -t sites/opus-manager/public sites/opus-manager/public/router.php
```

Puis ouvrir :

```text
http://127.0.0.1:8079/opus-manager/create-site
```

## Règles

- Un controller par fonctionnalité/page.
- CreateSiteController est l’entrée principale utilisateur.
- LSTSAR Manager et ODBC Manager sont réutilisés, pas recréés.
- Les briques OPUS sont les seules briques autorisées.
- En prod : aucun profiler/debug.
- Ref Book et User Book doivent documenter le shell.
MD;
opus_shell_write($siteRoot . DIRECTORY_SEPARATOR . 'README.md', $readme . PHP_EOL);

$doc = <<<'MD'
# OPUS Manager — Controller Shell Reuse

Contrat : `OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE`

## Objectif

Créer le shell backoffice OPUS Manager en récupérant l’existant.

Le shell n’est pas une réécriture de LSTSAR Manager ou ODBC Manager. Il les expose comme modules internes et les relie aux routes OPUS existantes.

## Navigation utilisateur

Entrée principale :

```text
Créer un site avec OPUS
```

Navigation claire :

- Créer
  - Créer un site
  - Créer un package
- Identité
  - Users / Identity
  - ACL
  - RBAC
  - SSO
  - Sessions
  - Auth Audit
- Moteurs
  - FSM
  - CL
  - Models
- Données
  - Database
  - ODBC Manager
  - LSTSAR Manager
- Installation
  - Composer
- Documentation
  - Ref Book
  - User Book
- Exploitation
  - Logs
  - Diagnostics

## Réutilisation

- ODBC Manager pointe vers la route OPS existante `/opus-lstsar-manager/odbc-manager`.
- LSTSAR Manager pointe vers les routes OPS existantes `/opus-lstsar-manager/chain` et `/opus-lstsar-manager/operations`.
- FSM, CL et Models pointent vers les routes OPS existantes correspondantes.
- CreateSiteController orchestre les modules au lieu de les recréer.

## Production

En mode `OPUS_ENV=prod` :

- `profiler`, `_profiler` et `profile` sont supprimés du contexte de requête.
- Aucun profiler n’est affiché.
- La page rappelle que le profiler est interdit.

## Prochaines étapes

- Brancher l’auth centrale OPUS Manager.
- Brancher ACL/RBAC par route.
- Remplacer les pages placeholder par les services OPUS existants.
- Générer le Ref Book et le User Book depuis ce shell.
- Ajouter les tests installation serveur Composer.
MD;
opus_shell_write($docRoot . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_CONTROLLER_SHELL_REUSE.md', $doc . PHP_EOL);
opus_shell_write($rootDoc . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_CONTROLLER_SHELL_REUSE.md', $doc . PHP_EOL);

$scopeFile = $rootDoc . DIRECTORY_SEPARATOR . 'P7_OPS_FINAL_CLOSURE_SCOPE.md';
$scope = opus_shell_read($scopeFile);
if ($scope === '') {
    $scope = '# OPUS P7 — portée de clôture finale' . PHP_EOL;
}
if (!str_contains($scope, 'OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE')) {
    $scope .= PHP_EOL;
    $scope .= '## OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE' . PHP_EOL . PHP_EOL;
    $scope .= '- Crée le shell OPUS Manager.' . PHP_EOL;
    $scope .= '- L’entrée principale utilisateur est `Créer un site avec OPUS`.' . PHP_EOL;
    $scope .= '- Un controller par fonctionnalité/page.' . PHP_EOL;
    $scope .= '- ODBC Manager et LSTSAR Manager sont réutilisés via les routes OPUS existantes.' . PHP_EOL;
    $scope .= '- Le shell n’importe aucune pile externe.' . PHP_EOL;
    $scope .= '- En prod : aucun profiler/debug.' . PHP_EOL;
}
opus_shell_write($scopeFile, $scope);

if (is_dir($workspace)) {
    $workspaceProjectDir = $workspace . DIRECTORY_SEPARATOR . 'CONTEXT' . DIRECTORY_SEPARATOR . 'PROJECTS' . DIRECTORY_SEPARATOR . 'OPUS';
    $workspaceHandoffDir = $workspace . DIRECTORY_SEPARATOR . 'CONTEXT' . DIRECTORY_SEPARATOR . 'HANDOFFS';

    foreach ([$workspaceProjectDir, $workspaceHandoffDir] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    opus_shell_write($workspaceProjectDir . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_CONTROLLER_SHELL_REUSE.md', $doc . PHP_EOL);
    opus_shell_write($workspaceHandoffDir . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_CONTROLLER_SHELL_REUSE.md', $doc . PHP_EOL);
}

echo 'OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE' . PHP_EOL;
echo 'SITE=sites/opus-manager' . PHP_EOL;
echo 'PUBLIC=sites/opus-manager/public' . PHP_EOL;
echo 'CONTROLLERS=' . count($controllers) . PHP_EOL;
foreach (array_keys($controllers) as $controller) {
    echo 'CONTROLLER=' . $controller . PHP_EOL;
}
echo 'OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE_OK' . PHP_EOL;
