<?php
declare(strict_types=1);

namespace OpusLstsarManager\View;

use OpusLstsarManager\Config\LstsarManagerDeclarationRepository;
use OpusLstsarManager\DryRun\LstsarManagerDryRunService;

/**
 * Builds deterministic view-models for the protected LSTSAR Manager package.
 */
final class LstsarManagerViewModelFactory
{
    public const MODE = 'lstsar_manager';

    private LstsarManagerDeclarationRepository $repository;
    private LstsarManagerDryRunService $dryRunService;

    public function __construct(?LstsarManagerDeclarationRepository $repository = null, ?LstsarManagerDryRunService $dryRunService = null)
    {
        $this->repository = $repository ?? new LstsarManagerDeclarationRepository();
        $this->dryRunService = $dryRunService ?? new LstsarManagerDryRunService($this->repository);
    }

    /** @return array<string,mixed> */
    public function dashboard(): array
    {
        return [
            'title' => 'OPUS LSTSAR Manager',
            'mode' => self::MODE,
            'protected' => true,
            'application' => 'opus-lstsar-manager',
            'package' => 'logandplay/opus-lstsar-manager',
            'stages' => ['load', 'securize', 'transform', 'store', 'archive', 'report'],
            'capabilities' => [
                'declare_sources' => true,
                'declare_destinations' => true,
                'declare_models' => true,
                'declare_mappings' => true,
                'declare_rules' => true,
                'dry_run' => true,
                'dry_run_engine_integrated' => true,
                'execute' => false,
                'raw_sql' => false,
                'ddl' => false,
            ],
            'navigation' => $this->navigation(),
        ];
    }

    /** @return array<string,mixed> */
    public function declarations(): array
    {
        return [
            'title' => 'LSTSAR declarations',
            'mode' => self::MODE,
            'declaration' => $this->repository->sampleDeclarationArray(),
            'editable_sections' => ['source', 'destination', 'mapping', 'security', 'transform', 'archive', 'report'],
            'navigation' => $this->navigation(),
        ];
    }

    /** @return array<string,mixed> */
    public function endpoint(string $kind): array
    {
        if (!in_array($kind, ['source', 'destination'], true)) {
            throw new \InvalidArgumentException('OPUS_LSTSAR_MANAGER_ENDPOINT_KIND_INVALID: ' . $kind);
        }

        $declaration = $this->repository->sampleDeclarationArray();
        return [
            'title' => $kind === 'source' ? 'Source ODBC model' : 'Destination ODBC model',
            'mode' => self::MODE,
            'endpoint_type' => $kind,
            'endpoint' => $declaration['config'][$kind] ?? [],
            'model' => $kind === 'source' ? $this->repository->sampleSourceModel()->toArray() : $this->repository->sampleDestinationModel()->toArray(),
            'odbc_only' => true,
            'navigation' => $this->navigation(),
        ];
    }

    /** @return array<string,mixed> */
    public function mappings(): array
    {
        $declaration = $this->repository->sampleDeclarationArray();
        return [
            'title' => 'LSTSAR mappings',
            'mode' => self::MODE,
            'mapping_required' => true,
            'mapping' => $declaration['config']['mapping'] ?? [],
            'source_model' => $this->repository->sampleSourceModel()->toArray(),
            'destination_model' => $this->repository->sampleDestinationModel()->toArray(),
            'navigation' => $this->navigation(),
        ];
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $declaration = $this->repository->sampleDeclarationArray();
        return [
            'title' => 'LSTSAR rules',
            'mode' => self::MODE,
            'rules' => [
                'security' => $declaration['config']['security'] ?? [],
                'transform' => $declaration['config']['transform'] ?? [],
                'store' => ['destination_model_required' => true],
            ],
            'navigation' => $this->navigation(),
        ];
    }

    /** @return array<string,mixed> */
    public function archiveReport(): array
    {
        $declaration = $this->repository->sampleDeclarationArray();
        return [
            'title' => 'LSTSAR Archive and Report',
            'mode' => self::MODE,
            'archive' => $declaration['config']['archive'] ?? [],
            'report' => $declaration['config']['report'] ?? [],
            'navigation' => $this->navigation(),
        ];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function dryRun(array $payload = []): array
    {
        $preview = $this->dryRunService->preview($payload);

        return [
            'title' => 'LSTSAR dry-run',
            'mode' => self::MODE,
            'execution_enabled' => false,
            'dry_run' => true,
            'dry_run_engine_integrated' => true,
            'raw_sql_allowed' => false,
            'ddl_allowed' => false,
            'payload' => $payload,
            'declaration' => $this->repository->sampleDeclarationArray(),
            'preview' => $preview,
            'run_result' => $preview['run_result'] ?? [],
            'navigation' => $this->navigation(),
        ];
    }

    /** @return list<array<string,string>> */
    public function navigation(): array
    {
        return [
            ['label' => 'Dashboard', 'route' => 'opus_lstsar_manager_dashboard', 'path' => '/opus-lstsar-manager'],
            ['label' => 'Declarations', 'route' => 'opus_lstsar_manager_declarations', 'path' => '/opus-lstsar-manager/declarations'],
            ['label' => 'Sources', 'route' => 'opus_lstsar_manager_sources', 'path' => '/opus-lstsar-manager/sources'],
            ['label' => 'Destinations', 'route' => 'opus_lstsar_manager_destinations', 'path' => '/opus-lstsar-manager/destinations'],
            ['label' => 'Mappings', 'route' => 'opus_lstsar_manager_mappings', 'path' => '/opus-lstsar-manager/mappings'],
            ['label' => 'Rules', 'route' => 'opus_lstsar_manager_rules', 'path' => '/opus-lstsar-manager/rules'],
            ['label' => 'Archive & Report', 'route' => 'opus_lstsar_manager_archive_report', 'path' => '/opus-lstsar-manager/archive-report'],
            ['label' => 'Dry-run', 'route' => 'opus_lstsar_manager_dry_run', 'path' => '/opus-lstsar-manager/dry-run'],
        ];
    }
}
