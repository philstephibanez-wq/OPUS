<?php
declare(strict_types=1);

namespace OpusOdbcManager\View;

/**
 * Builds deterministic view-models for the OPUS ODBC Manager read-only site.
 *
 * The class deliberately returns arrays so the current OPUS routing/template
 * layer can consume it without coupling the package to a concrete HTTP stack.
 */
final class OdbcManagerReadOnlyViewModelFactory
{
    public const MODE = 'readonly';

    /** @return array<string,mixed> */
    public function dashboard(): array
    {
        return [
            'title' => 'OPUS ODBC Manager',
            'mode' => self::MODE,
            'protected' => true,
            'application' => 'opus-odbc-manager',
            'package' => 'logandplay/opus-odbc-manager',
            'capabilities' => [
                'datasources' => true,
                'tables' => true,
                'inspect' => true,
                'preview' => true,
                'lstsar_draft' => true,
                'crud' => false,
                'ddl' => false,
                'sql_console' => false,
            ],
            'navigation' => $this->navigation(),
        ];
    }

    /** @param list<array<string,mixed>> $datasources @return array<string,mixed> */
    public function datasources(array $datasources = []): array
    {
        return [
            'title' => 'ODBC data sources',
            'mode' => self::MODE,
            'datasources' => $datasources,
            'empty_state' => $datasources === [],
            'navigation' => $this->navigation(),
        ];
    }

    /** @param list<array<string,mixed>> $tables @return array<string,mixed> */
    public function tables(array $tables = []): array
    {
        return [
            'title' => 'ODBC tables and views',
            'mode' => self::MODE,
            'tables' => $tables,
            'empty_state' => $tables === [],
            'navigation' => $this->navigation(),
        ];
    }

    /** @param array<string,mixed>|null $inspection @return array<string,mixed> */
    public function tableDetail(string $table, ?array $inspection = null): array
    {
        return [
            'title' => 'ODBC table detail',
            'mode' => self::MODE,
            'table' => $this->safeTableName($table),
            'inspection' => $inspection ?? [
                'columns' => [],
                'model' => null,
            ],
            'navigation' => $this->navigation(),
        ];
    }

    /** @param array<string,mixed>|null $preview @return array<string,mixed> */
    public function preview(string $table, int $limit = 20, ?array $preview = null): array
    {
        $limit = max(1, min(200, $limit));

        return [
            'title' => 'ODBC table preview',
            'mode' => self::MODE,
            'table' => $this->safeTableName($table),
            'limit' => $limit,
            'preview' => $preview ?? [
                'rows' => [],
            ],
            'navigation' => $this->navigation(),
        ];
    }

    /** @param array<string,mixed>|null $draft @return array<string,mixed> */
    public function lstsarDraft(string $table, ?array $draft = null): array
    {
        return [
            'title' => 'LSTSAR draft',
            'mode' => self::MODE,
            'table' => $this->safeTableName($table),
            'draft' => $draft ?? [
                'odbc_only' => true,
                'operations' => ['load', 'secure', 'transform', 'store', 'audit', 'restore'],
            ],
            'navigation' => $this->navigation(),
        ];
    }

    /** @return list<array<string,string>> */
    public function navigation(): array
    {
        return [
            ['label' => 'Dashboard', 'route' => 'opus_odbc_manager_dashboard', 'path' => '/opus-odbc-manager'],
            ['label' => 'Data sources', 'route' => 'opus_odbc_manager_datasources', 'path' => '/opus-odbc-manager/datasources'],
            ['label' => 'Tables', 'route' => 'opus_odbc_manager_tables', 'path' => '/opus-odbc-manager/tables'],
        ];
    }

    private function safeTableName(string $table): string
    {
        $table = trim($table);
        if ($table === '') {
            return '__no_table_selected__';
        }

        return $table;
    }
}
