<?php
declare(strict_types=1);

echo 'OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$doc = $root . '/DOC/OPUS_MANAGER_CONTROLLER_ARCHITECTURE.md';

if (!is_file($doc)) {
    throw new RuntimeException('OPUS_MANAGER_CONTROLLER_ARCHITECTURE_DOC_MISSING');
}

$source = file_get_contents($doc);
if (!is_string($source)) {
    throw new RuntimeException('OPUS_MANAGER_CONTROLLER_ARCHITECTURE_DOC_READ_FAILED');
}

$markers = [
    'OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE',
    'un controller par fonctionnalité/page',
    'Aucun gros controller fourre-tout',
    'OpusManagerDashboardController',
    'UsersManagerController',
    'AclManagerController',
    'RbacManagerController',
    'SsoManagerController',
    'SessionsManagerController',
    'AuthAuditController',
    'FsmManagerController',
    'ClManagerController',
    'ModelsManagerController',
    'DatabaseManagerController',
    'OdbcManagerController',
    'LstsarManagerController',
    'ComposerManagerController',
    'CreateSiteController',
    'CreatePackageController',
    'RefBookController',
    'UserBookController',
    'LogsController',
    'DiagnosticsController',
    'OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE',
];

foreach ($markers as $marker) {
    if (!str_contains($source, $marker)) {
        throw new RuntimeException('OPUS_MANAGER_CONTROLLER_ARCHITECTURE_MARKER_MISSING: ' . $marker);
    }
}

$scope = $root . '/DOC/P7_OPS_FINAL_CLOSURE_SCOPE.md';
if (is_file($scope)) {
    $scopeSource = file_get_contents($scope);
    if (!is_string($scopeSource) || !str_contains($scopeSource, 'OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE')) {
        throw new RuntimeException('OPUS_MANAGER_CONTROLLER_ARCHITECTURE_SCOPE_NOT_UPDATED');
    }
}

echo 'CHECK_OPUS_MANAGER_CONTROLLER_ARCHITECTURE_DOC=OK' . PHP_EOL;
echo 'OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE_SMOKE_OK' . PHP_EOL;
