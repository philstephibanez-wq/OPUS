<?php
declare(strict_types=1);

namespace Opus\Manager\Service;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class OpusManagerModuleRegistry
{
    public static function modules(): array
    {
        return array (
  0 => 
  array (
    'controller' => 'OpusManagerDashboardController',
    'title' => 'Dashboard',
    'route' => '/opus-manager',
    'group' => 'Accueil',
    'expert' => false,
    'summary' => 'Vue d’ensemble du backoffice OPUS Manager.',
  ),
  1 => 
  array (
    'controller' => 'CreateSiteController',
    'title' => 'Créer un site',
    'route' => '/opus-manager/create-site',
    'group' => 'Créer',
    'expert' => false,
    'summary' => 'Assistant principal pour créer un site avec OPUS.',
  ),
  2 => 
  array (
    'controller' => 'CreatePackageController',
    'title' => 'Créer un package',
    'route' => '/opus-manager/create-package',
    'group' => 'Créer',
    'expert' => false,
    'summary' => 'Préparation contrôlée d’un package OPUS via recettes Composer.',
  ),
  3 => 
  array (
    'controller' => 'UsersManagerController',
    'title' => 'Users / Identity',
    'route' => '/opus-manager/users',
    'group' => 'Identité',
    'expert' => true,
    'summary' => 'Utilisateurs, comptes, identité et état des accès.',
  ),
  4 => 
  array (
    'controller' => 'AclManagerController',
    'title' => 'ACL',
    'route' => '/opus-manager/acl',
    'group' => 'Identité',
    'expert' => true,
    'summary' => 'Permissions, policies et droits par module.',
  ),
  5 => 
  array (
    'controller' => 'RbacManagerController',
    'title' => 'RBAC',
    'route' => '/opus-manager/rbac',
    'group' => 'Identité',
    'expert' => true,
    'summary' => 'Rôles métiers, héritage et assignations.',
  ),
  6 => 
  array (
    'controller' => 'SsoManagerController',
    'title' => 'SSO',
    'route' => '/opus-manager/sso',
    'group' => 'Identité',
    'expert' => true,
    'summary' => 'Providers SSO et configuration de fédération d’identité.',
  ),
  7 => 
  array (
    'controller' => 'SessionsManagerController',
    'title' => 'Sessions',
    'route' => '/opus-manager/sessions',
    'group' => 'Identité',
    'expert' => true,
    'summary' => 'Sessions actives, révocation et contrôle.',
  ),
  8 => 
  array (
    'controller' => 'AuthAuditController',
    'title' => 'Auth Audit',
    'route' => '/opus-manager/auth-audit',
    'group' => 'Identité',
    'expert' => true,
    'summary' => 'Audit des connexions, déconnexions et décisions d’accès.',
  ),
  9 => 
  array (
    'controller' => 'FsmManagerController',
    'title' => 'FSM',
    'route' => '/opus-manager/fsm',
    'group' => 'Moteurs',
    'expert' => true,
    'summary' => 'Machines d’état, transitions et diagnostics FSM.',
  ),
  10 => 
  array (
    'controller' => 'ClManagerController',
    'title' => 'CL',
    'route' => '/opus-manager/cl',
    'group' => 'Moteurs',
    'expert' => true,
    'summary' => 'CL et orchestration des couches OPUS associées.',
  ),
  11 => 
  array (
    'controller' => 'ModelsManagerController',
    'title' => 'Models',
    'route' => '/opus-manager/models',
    'group' => 'Moteurs',
    'expert' => true,
    'summary' => 'Modèles, schémas, objets typés et diagnostics.',
  ),
  12 => 
  array (
    'controller' => 'DatabaseManagerController',
    'title' => 'Database',
    'route' => '/opus-manager/database',
    'group' => 'Données',
    'expert' => true,
    'summary' => 'Tables, colonnes, contraintes, types attendus et sources.',
  ),
  13 => 
  array (
    'controller' => 'OdbcManagerController',
    'title' => 'ODBC Manager',
    'route' => '/opus-manager/odbc',
    'group' => 'Données',
    'expert' => true,
    'summary' => 'DSN, drivers, tests de connexion et contrats ODBC.',
  ),
  14 => 
  array (
    'controller' => 'LstsarManagerController',
    'title' => 'LSTSAR Manager',
    'route' => '/opus-manager/lstsar',
    'group' => 'Données',
    'expert' => true,
    'summary' => 'Load / Secure / Transform / Store / Audit.',
  ),
  15 => 
  array (
    'controller' => 'ComposerManagerController',
    'title' => 'Composer',
    'route' => '/opus-manager/composer',
    'group' => 'Installation',
    'expert' => true,
    'summary' => 'Composer validate/install/no-dev/autoload et packages.',
  ),
  16 => 
  array (
    'controller' => 'RefBookController',
    'title' => 'Ref Book',
    'route' => '/opus-manager/ref-book',
    'group' => 'Documentation',
    'expert' => false,
    'summary' => 'Documentation technique, API, classes, routes et manifests.',
  ),
  17 => 
  array (
    'controller' => 'UserBookController',
    'title' => 'User Book',
    'route' => '/opus-manager/user-book',
    'group' => 'Documentation',
    'expert' => false,
    'summary' => 'Documentation utilisateur, parcours, écrans et exploitation.',
  ),
  18 => 
  array (
    'controller' => 'LogsController',
    'title' => 'Logs',
    'route' => '/opus-manager/logs',
    'group' => 'Exploitation',
    'expert' => true,
    'summary' => 'Accès contrôlé aux journaux autorisés.',
  ),
  19 => 
  array (
    'controller' => 'DiagnosticsController',
    'title' => 'Diagnostics',
    'route' => '/opus-manager/diagnostics',
    'group' => 'Exploitation',
    'expert' => false,
    'summary' => 'Santé système, contrôles et diagnostics non sensibles.',
  ),
);
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