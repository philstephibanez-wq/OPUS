<?php
declare(strict_types=1);

use OpusLstsarManager\Controller\DashboardController;
use OpusLstsarManager\Controller\DeclarationsController;
use OpusLstsarManager\Controller\DryRunController;

return [
    'opus_lstsar_manager_dashboard' => [
        'path' => '/opus-lstsar-manager',
        'controller' => DashboardController::class . '::dashboard',
        'template' => 'dashboard.score',
        'methods' => ['GET'],
        'permission' => 'opus.lstsar_manager.access',
        'profiler' => ['category' => 'opus.lstsar_manager', 'action' => 'dashboard'],
    ],
    'opus_lstsar_manager_declarations' => [
        'path' => '/opus-lstsar-manager/declarations',
        'controller' => DeclarationsController::class . '::declarations',
        'template' => 'declarations.score',
        'methods' => ['GET'],
        'permission' => 'opus.lstsar_manager.declare',
        'profiler' => ['category' => 'opus.lstsar_manager', 'action' => 'declarations'],
    ],
    'opus_lstsar_manager_sources' => [
        'path' => '/opus-lstsar-manager/sources',
        'controller' => DeclarationsController::class . '::sources',
        'template' => 'endpoint.score',
        'methods' => ['GET'],
        'permission' => 'opus.lstsar_manager.source',
        'profiler' => ['category' => 'opus.lstsar_manager', 'action' => 'sources'],
    ],
    'opus_lstsar_manager_destinations' => [
        'path' => '/opus-lstsar-manager/destinations',
        'controller' => DeclarationsController::class . '::destinations',
        'template' => 'endpoint.score',
        'methods' => ['GET'],
        'permission' => 'opus.lstsar_manager.destination',
        'profiler' => ['category' => 'opus.lstsar_manager', 'action' => 'destinations'],
    ],
    'opus_lstsar_manager_mappings' => [
        'path' => '/opus-lstsar-manager/mappings',
        'controller' => DeclarationsController::class . '::mappings',
        'template' => 'mapping.score',
        'methods' => ['GET'],
        'permission' => 'opus.lstsar_manager.mapping',
        'profiler' => ['category' => 'opus.lstsar_manager', 'action' => 'mappings'],
    ],
    'opus_lstsar_manager_rules' => [
        'path' => '/opus-lstsar-manager/rules',
        'controller' => DeclarationsController::class . '::rules',
        'template' => 'rules.score',
        'methods' => ['GET'],
        'permission' => 'opus.lstsar_manager.rules',
        'profiler' => ['category' => 'opus.lstsar_manager', 'action' => 'rules'],
    ],
    'opus_lstsar_manager_archive_report' => [
        'path' => '/opus-lstsar-manager/archive-report',
        'controller' => DeclarationsController::class . '::archiveReport',
        'template' => 'archive-report.score',
        'methods' => ['GET'],
        'permission' => 'opus.lstsar_manager.archive_report',
        'profiler' => ['category' => 'opus.lstsar_manager', 'action' => 'archive_report'],
    ],
    'opus_lstsar_manager_dry_run' => [
        'path' => '/opus-lstsar-manager/dry-run',
        'controller' => DryRunController::class . '::dryRunForm',
        'template' => 'dry-run.score',
        'methods' => ['GET'],
        'permission' => 'opus.lstsar_manager.dry_run',
        'profiler' => ['category' => 'opus.lstsar_manager', 'action' => 'dry_run_form'],
    ],
    'opus_lstsar_manager_dry_run_preview' => [
        'path' => '/opus-lstsar-manager/dry-run/preview',
        'controller' => DryRunController::class . '::preview',
        'template' => 'dry-run.score',
        'methods' => ['POST'],
        'permission' => 'opus.lstsar_manager.dry_run',
        'profiler' => ['category' => 'opus.lstsar_manager', 'action' => 'dry_run_preview'],
    ],
];
