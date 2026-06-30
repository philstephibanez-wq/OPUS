<?php
declare(strict_types=1);

namespace OpusLstsarManager\Operation;

use Opus\Lstsar\LstsarConfig;
use Opus\Model\ModelField;
use Opus\Model\TableModel;
use OpusLstsarManager\Config\LstsarManagerDeclarationRepository;

/**
 * Builds the first site/client-scoped LSTSAR operation dashboard model.
 *
 * The implementation is deterministic for this milestone. A future milestone can
 * replace it with persisted declarations and run history while keeping the same
 * dashboard contract.
 */
final class LstsarManagerOperationRepository
{
    private LstsarManagerDeclarationRepository $declarations;

    public function __construct(?LstsarManagerDeclarationRepository $declarations = null)
    {
        $this->declarations = $declarations ?? new LstsarManagerDeclarationRepository();
    }

    /** @return array<string,mixed> */
    public function dashboardForSite(string $siteId = 'site-demo'): array
    {
        $siteId = $this->safeSiteId($siteId);
        $operations = $this->operationsForSite($siteId);
        $active = 0;
        $ready = 0;
        $blocked = 0;
        foreach ($operations as $operation) {
            if (($operation['active'] ?? false) === true) {
                ++$active;
            }
            if (($operation['status'] ?? '') === 'ready') {
                ++$ready;
            }
            if (($operation['status'] ?? '') === 'blocked') {
                ++$blocked;
            }
        }

        return [
            'contract' => 'OPUS_LSTSAR_MANAGER_DASHBOARD_OPERATIONS_V1',
            'selected_site' => [
                'site_id' => $siteId,
                'client_id' => 'client-demo',
                'label' => 'Demo client site',
            ],
            'counters' => [
                'operations' => count($operations),
                'active' => $active,
                'ready' => $ready,
                'blocked' => $blocked,
            ],
            'operations' => $operations,
            'launch_policy' => [
                'manual_launch_enabled' => false,
                'scheduler_launch_enabled' => false,
                'dry_run_enabled' => true,
                'raw_sql_allowed' => false,
                'ddl_allowed' => false,
                'next_contract' => 'P7_LSTSAR_SCHEDULER_CRON_TRIGGER_CONTRACT_CORE',
            ],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function operationsForSite(string $siteId = 'site-demo'): array
    {
        $siteId = $this->safeSiteId($siteId);
        $config = $this->declarations->sampleConfig();
        $configArray = $config->toArray();
        $sourceModel = $this->declarations->sampleSourceModel();
        $destinationModel = $this->declarations->sampleDestinationModel();
        $coverage = $this->coverage($config, $destinationModel);

        return [[
            'contract' => 'OPUS_LSTSAR_MANAGER_OPERATION_V1',
            'operation_id' => 'lstsar.orders.import',
            'site_id' => $siteId,
            'client_id' => 'client-demo',
            'label' => 'Import orders from legacy ODBC',
            'active' => true,
            'status' => $coverage['missing_required_fields'] === [] ? 'ready' : 'blocked',
            'source' => [
                'driver' => 'odbc',
                'datasource' => $configArray['source']['datasource'] ?? '',
                'model' => $sourceModel->id(),
                'table' => $sourceModel->tableName(),
            ],
            'destination' => [
                'driver' => 'odbc',
                'datasource' => $configArray['destination']['datasource'] ?? '',
                'model' => $destinationModel->id(),
                'table' => $destinationModel->tableName(),
            ],
            'mapping' => $config->mapping(),
            'assignments' => $this->assignments($config),
            'coverage' => $coverage,
            'last_dry_run' => [
                'ok' => true,
                'run_id' => 'manager-dry-run-sample',
                'finished_at' => '2026-06-30T09:45:00+00:00',
                'report_route' => 'opus_lstsar_manager_dry_run',
            ],
            'last_run' => [
                'ok' => true,
                'run_id' => 'orders-import-20260630-083000',
                'finished_at' => '2026-06-30T08:30:00+00:00',
                'report_ref' => 'report://lstsar/orders-import-20260630-083000',
                'archive_ref' => 'archive://lstsar/orders-import-20260630-083000',
            ],
            'next_run' => [
                'trigger' => 'cron',
                'expression' => '*/15 * * * *',
                'planned_at' => '2026-06-30T10:00:00+00:00',
                'enabled' => false,
                'contract_pending' => 'P7_LSTSAR_SCHEDULER_CRON_TRIGGER_CONTRACT_CORE',
            ],
            'launch_actions' => [
                ['name' => 'dry_run', 'enabled' => true, 'permission' => 'opus.lstsar_manager.dry_run'],
                ['name' => 'manual_run', 'enabled' => false, 'permission' => 'opus.lstsar_manager.execute', 'disabled_reason' => 'guarded execution milestone pending'],
                ['name' => 'cron_trigger', 'enabled' => false, 'permission' => 'opus.lstsar_manager.scheduler_trigger', 'disabled_reason' => 'scheduler/cron trigger contract pending'],
            ],
            'links' => [
                'dry_run' => '/opus-lstsar-manager/dry-run',
                'archive_report' => '/opus-lstsar-manager/archive-report',
                'declaration' => '/opus-lstsar-manager/declarations',
            ],
        ]];
    }

    /** @return array<string,mixed> */
    private function coverage(LstsarConfig $config, TableModel $destinationModel): array
    {
        $mapped = array_values($config->mapping());
        $assigned = array_keys($this->assignments($config));
        $covered = array_values(array_unique(array_merge($mapped, $assigned)));
        $required = [];
        $missing = [];
        foreach ($destinationModel->fields() as $field) {
            if (!$field instanceof ModelField) {
                continue;
            }
            if (!$field->nullable()) {
                $required[] = $field->name();
                if (!in_array($field->name(), $covered, true)) {
                    $missing[] = $field->name();
                }
            }
        }

        return [
            'destination_fields' => array_map(static fn (ModelField $field): string => $field->name(), $destinationModel->fields()),
            'mapped_fields' => $mapped,
            'assigned_fields' => $assigned,
            'covered_fields' => $covered,
            'required_fields' => $required,
            'missing_required_fields' => $missing,
            'coverage_ok' => $missing === [],
        ];
    }

    /** @return array<string,array<string,mixed>> */
    private function assignments(LstsarConfig $config): array
    {
        $transform = $config->transform();
        $assignments = $transform['assignments'] ?? [];
        if (!is_array($assignments)) {
            return [];
        }

        $out = [];
        foreach ($assignments as $field => $assignment) {
            if (is_string($field) && is_array($assignment)) {
                $out[$field] = $assignment;
            }
        }

        return $out;
    }

    private function safeSiteId(string $siteId): string
    {
        $siteId = trim($siteId);
        if ($siteId === '') {
            return 'site-demo';
        }
        if (!preg_match('/^[a-zA-Z0-9_\-.]{1,120}$/', $siteId)) {
            throw new \InvalidArgumentException('OPUS_LSTSAR_MANAGER_SITE_ID_INVALID: ' . $siteId);
        }

        return $siteId;
    }
}
