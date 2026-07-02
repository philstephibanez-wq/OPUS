<?php
declare(strict_types=1);

/**
 * OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE
 *
 * Verrouille la règle :
 * - un controller par fonctionnalité/page OPUS Manager
 * - shell d'orchestration séparé
 * - réutilisation de l'existant
 * - briques OPUS uniquement
 */

$root = getcwd();
$workspace = 'H:\\MAESTRO_WORKSPACE';

if (!is_file($root . DIRECTORY_SEPARATOR . 'composer.json')) {
    fwrite(STDERR, 'OPUS_MANAGER_CONTROLLER_ARCHITECTURE_NOT_IN_OPUS_ROOT' . PHP_EOL);
    exit(1);
}

$controllers = [
    'OpusManagerDashboardController' => [
        'module' => 'Dashboard',
        'route' => '/opus-manager',
        'responsibility' => 'Vue d’ensemble du backoffice OPUS Manager.',
    ],
    'UsersManagerController' => [
        'module' => 'Users / Identity',
        'route' => '/opus-manager/users',
        'responsibility' => 'Utilisateurs, identité, comptes, état des accès.',
    ],
    'AclManagerController' => [
        'module' => 'ACL / RBAC',
        'route' => '/opus-manager/acl',
        'responsibility' => 'Permissions, rôles, policies et droits par module.',
    ],
    'RbacManagerController' => [
        'module' => 'RBAC',
        'route' => '/opus-manager/rbac',
        'responsibility' => 'Rôles métiers, héritage et assignations.',
    ],
    'SsoManagerController' => [
        'module' => 'SSO',
        'route' => '/opus-manager/sso',
        'responsibility' => 'Providers SSO, configuration et état des connecteurs.',
    ],
    'SessionsManagerController' => [
        'module' => 'Sessions',
        'route' => '/opus-manager/sessions',
        'responsibility' => 'Sessions actives, révocation et contrôle d’accès.',
    ],
    'AuthAuditController' => [
        'module' => 'Auth Audit',
        'route' => '/opus-manager/auth-audit',
        'responsibility' => 'Audit des connexions, déconnexions et décisions d’accès.',
    ],
    'FsmManagerController' => [
        'module' => 'FSM',
        'route' => '/opus-manager/fsm',
        'responsibility' => 'Machines d’état, transitions et diagnostics FSM.',
    ],
    'ClManagerController' => [
        'module' => 'CL',
        'route' => '/opus-manager/cl',
        'responsibility' => 'CL et orchestration des couches OPUS associées.',
    ],
    'ModelsManagerController' => [
        'module' => 'Models',
        'route' => '/opus-manager/models',
        'responsibility' => 'Modèles, schémas, objets typés et diagnostics.',
    ],
    'DatabaseManagerController' => [
        'module' => 'Database',
        'route' => '/opus-manager/database',
        'responsibility' => 'Tables, colonnes, contraintes, types attendus et sources.',
    ],
    'OdbcManagerController' => [
        'module' => 'ODBC Manager',
        'route' => '/opus-manager/odbc',
        'responsibility' => 'DSN, drivers, tests de connexion et contrats ODBC.',
    ],
    'LstsarManagerController' => [
        'module' => 'LSTSAR Manager',
        'route' => '/opus-manager/lstsar',
        'responsibility' => 'Load / Secure / Transform / Store / Audit.',
    ],
    'ComposerManagerController' => [
        'module' => 'Composer',
        'route' => '/opus-manager/composer',
        'responsibility' => 'Composer validate/install/no-dev/autoload et packages.',
    ],
    'CreateSiteController' => [
        'module' => 'Create Site',
        'route' => '/opus-manager/create-site',
        'responsibility' => 'Création contrôlée de sites OPUS via recettes Composer.',
    ],
    'CreatePackageController' => [
        'module' => 'Create Package',
        'route' => '/opus-manager/create-package',
        'responsibility' => 'Création contrôlée de packages OPUS via recettes Composer.',
    ],
    'RefBookController' => [
        'module' => 'Ref Book',
        'route' => '/opus-manager/ref-book',
        'responsibility' => 'Documentation technique, API, classes, routes et manifests.',
    ],
    'UserBookController' => [
        'module' => 'User Book',
        'route' => '/opus-manager/user-book',
        'responsibility' => 'Documentation utilisateur, parcours, écrans et exploitation.',
    ],
    'LogsController' => [
        'module' => 'Logs',
        'route' => '/opus-manager/logs',
        'responsibility' => 'Accès contrôlé aux journaux autorisés.',
    ],
    'DiagnosticsController' => [
        'module' => 'Diagnostics',
        'route' => '/opus-manager/diagnostics',
        'responsibility' => 'Santé système, contrôles et diagnostics non sensibles.',
    ],
];

$dirs = [
    $root . DIRECTORY_SEPARATOR . 'DOC',
    $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'audits',
    $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'smokes',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, 'OPUS_MANAGER_CONTROLLER_DIR_CREATE_FAILED: ' . $dir . PHP_EOL);
        exit(1);
    }
}

function opus_mgr_ctrl_rel(string $root, string $path): string
{
    $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return str_starts_with($path, $prefix)
        ? str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($prefix)))
        : str_replace(DIRECTORY_SEPARATOR, '/', $path);
}

function opus_mgr_ctrl_collect_controllers(string $root): array
{
    $targets = ['framework', 'packages', 'sites'];
    $files = [];

    foreach ($targets as $target) {
        $dir = $root . DIRECTORY_SEPARATOR . $target;
        if (!is_dir($dir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            $normalized = str_replace(DIRECTORY_SEPARATOR, '/', $path);
            if (str_contains($normalized, '/vendor/') || str_contains($normalized, '/.git/')) {
                continue;
            }

            if (preg_match('/Controller\.php$/', $file->getFilename())) {
                $files[] = opus_mgr_ctrl_rel($root, $path);
            }
        }
    }

    sort($files);
    return array_values(array_unique($files));
}

function opus_mgr_ctrl_markdown_row(array $cells): string
{
    return '| ' . implode(' | ', array_map(static function (string $cell): string {
        return str_replace(["\r", "\n", '|'], [' ', ' ', '\\|'], $cell);
    }, $cells)) . ' |';
}

$existingControllers = opus_mgr_ctrl_collect_controllers($root);

$report = [];
$report[] = '# OPUS Manager — architecture controllers';
$report[] = '';
$report[] = 'Contrat : `OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE`';
$report[] = '';
$report[] = '## Règle';
$report[] = '';
$report[] = 'OPUS Manager doit avoir autant de controllers dédiés que de pages/fonctionnalités métier.';
$report[] = '';
$report[] = 'Aucun gros controller fourre-tout ne doit porter Users, FSM, ACL, SSO, ODBC, LSTSAR et Composer en même temps.';
$report[] = '';
$report[] = 'Le shell OPUS Manager orchestre la navigation et le layout. Chaque controller porte une responsabilité claire.';
$report[] = '';
$report[] = '## Contraintes';
$report[] = '';
$report[] = '- Un controller par fonctionnalité/page.';
$report[] = '- Réutiliser les briques OPUS existantes.';
$report[] = '- Ne pas recréer LSTSAR Manager ou ODBC Manager.';
$report[] = '- Ne pas importer de pile externe.';
$report[] = '- Auth centrale obligatoire.';
$report[] = '- ACL/RBAC centralisé.';
$report[] = '- SSO prévu comme module.';
$report[] = '- En production : aucun profiler/debug.';
$report[] = '- I18N : toutes langues officielles UE + ukrainien (`uk`).';
$report[] = '- Ref Book et User Book obligatoires.';
$report[] = '';
$report[] = '## Controllers cibles';
$report[] = '';
$report[] = opus_mgr_ctrl_markdown_row(['Controller', 'Module', 'Route', 'Responsabilité']);
$report[] = opus_mgr_ctrl_markdown_row(['---', '---', '---', '---']);

foreach ($controllers as $controller => $meta) {
    $report[] = opus_mgr_ctrl_markdown_row([
        $controller,
        $meta['module'],
        $meta['route'],
        $meta['responsibility'],
    ]);
}

$report[] = '';
$report[] = '## Controllers existants détectés';
$report[] = '';

if ($existingControllers === []) {
    $report[] = 'Aucun controller existant détecté dans `framework`, `packages` ou `sites`.';
} else {
    foreach ($existingControllers as $controllerPath) {
        $report[] = '- `' . $controllerPath . '`';
    }
}

$report[] = '';
$report[] = '## Stratégie de migration';
$report[] = '';
$report[] = '1. Auditer les controllers et pages déjà présents.';
$report[] = '2. Raccorder les pages existantes dans le shell OPUS Manager.';
$report[] = '3. Créer uniquement les controllers manquants.';
$report[] = '4. Déléguer la logique aux services/briques OPUS existants.';
$report[] = '5. Ajouter les routes OPUS Manager sans casser les routes historiques.';
$report[] = '6. Documenter chaque controller dans le Ref Book.';
$report[] = '7. Expliquer chaque écran dans le User Book.';
$report[] = '';
$report[] = '## Prochaine brique';
$report[] = '';
$report[] = '`OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE`';
$report[] = '';
$report[] = 'Objectif : créer le shell backoffice OPUS Manager et y brancher les controllers dédiés, en récupérant les pages existantes.';
$report[] = '';

$reportText = implode(PHP_EOL, $report) . PHP_EOL;
$reportFile = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_CONTROLLER_ARCHITECTURE.md';

if (file_put_contents($reportFile, $reportText) === false) {
    fwrite(STDERR, 'OPUS_MANAGER_CONTROLLER_REPORT_WRITE_FAILED' . PHP_EOL);
    exit(1);
}

$scopeFile = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'P7_OPS_FINAL_CLOSURE_SCOPE.md';
if (is_file($scopeFile)) {
    $scope = file_get_contents($scopeFile);
    if (is_string($scope) && !str_contains($scope, 'OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE')) {
        $scope .= PHP_EOL;
        $scope .= '## OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE' . PHP_EOL . PHP_EOL;
        $scope .= '- OPUS Manager impose un controller par fonctionnalité/page.' . PHP_EOL;
        $scope .= '- Le shell orchestre ; chaque controller porte une responsabilité métier.' . PHP_EOL;
        $scope .= '- Les controllers cibles couvrent Users/Identity, ACL/RBAC, SSO, FSM, CL, Models, Database, ODBC, LSTSAR, Composer, Create Site, Create Package, Ref Book, User Book, Logs et Diagnostics.' . PHP_EOL;
        $scope .= '- Les briques existantes doivent être récupérées avant toute création.' . PHP_EOL;
        file_put_contents($scopeFile, $scope);
    }
}

if (is_dir($workspace)) {
    $workspaceProjectDir = $workspace . DIRECTORY_SEPARATOR . 'CONTEXT' . DIRECTORY_SEPARATOR . 'PROJECTS' . DIRECTORY_SEPARATOR . 'OPUS';
    $workspaceHandoffDir = $workspace . DIRECTORY_SEPARATOR . 'CONTEXT' . DIRECTORY_SEPARATOR . 'HANDOFFS';

    foreach ([$workspaceProjectDir, $workspaceHandoffDir] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    file_put_contents($workspaceProjectDir . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_CONTROLLER_ARCHITECTURE.md', $reportText);
    file_put_contents($workspaceHandoffDir . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_CONTROLLER_ARCHITECTURE.md', $reportText);
}

echo 'OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE' . PHP_EOL;
echo 'REPORT=' . opus_mgr_ctrl_rel($root, $reportFile) . PHP_EOL;
echo 'TARGET_CONTROLLERS=' . count($controllers) . PHP_EOL;
echo 'EXISTING_CONTROLLERS=' . count($existingControllers) . PHP_EOL;
foreach (array_keys($controllers) as $controller) {
    echo 'TARGET_CONTROLLER=' . $controller . PHP_EOL;
}
echo 'OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE_OK' . PHP_EOL;
