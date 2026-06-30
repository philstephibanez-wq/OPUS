<?php
declare(strict_types=1);

namespace OpusLstsarManager\Config;

use Opus\Lstsar\LstsarBackofficeDeclaration;
use Opus\Lstsar\LstsarConfig;
use Opus\Model\ModelField;
use Opus\Model\TableModel;

/**
 * Deterministic declaration repository for the protected LSTSAR Manager.
 *
 * This repository stays in-memory for the current milestone. It already exposes
 * the same objects that a future persistence-backed manager will edit: source
 * endpoint, destination endpoint, source model, destination model, mapping,
 * destination assignments, securize policy, transform rules, archive policy and
 * report policy.
 */
final class LstsarManagerDeclarationRepository
{
    public function sampleDeclaration(): LstsarBackofficeDeclaration
    {
        return new LstsarBackofficeDeclaration(
            $this->sampleConfig(),
            ['source', 'destination', 'mapping', 'security', 'transform', 'archive', 'report']
        );
    }

    public function sampleConfig(): LstsarConfig
    {
        return LstsarConfig::fromArray($this->sampleConfigArray());
    }

    /** @return array<string,mixed> */
    public function sampleConfigArray(): array
    {
        return [
            'contract' => LstsarConfig::CONTRACT,
            'run_id' => 'manager-dry-run-sample',
            'source' => [
                'driver' => 'odbc',
                'datasource' => 'source_dsn',
                'model' => 'source_orders_model',
                'table' => 'orders_source',
            ],
            'destination' => [
                'driver' => 'odbc',
                'datasource' => 'destination_dsn',
                'model' => 'destination_orders_model',
                'table' => 'orders_destination',
            ],
            'mapping' => [
                'code' => 'order_code',
                'amount' => 'total_amount',
            ],
            'security' => [
                'stage' => 'securize',
                'policy' => 'acl.required',
                'acl_required' => true,
                'acl_granted' => true,
                'anonymous' => false,
                'actor_id' => 'lstsar-manager-dry-run',
            ],
            'transform' => [
                'order_code' => ['trim' => true, 'uppercase' => true, 'pad_right' => ['length' => 4, 'char' => '0']],
                'total_amount' => ['cast' => 'float', 'round' => 2],
                'assignments' => [
                    'client_id' => ['type' => 'constant', 'value' => 'client-demo'],
                    'created_by' => ['type' => 'security', 'path' => 'actor_id', 'default' => 'lstsar-manager'],
                    'row_hash' => ['type' => 'hash', 'source' => 'destination', 'algo' => 'sha256', 'fields' => ['order_code', 'total_amount']],
                ],
            ],
            'archive' => [
                'enabled' => true,
                'mode' => 'metadata_and_payload_hash',
            ],
            'report' => [
                'enabled' => true,
                'format' => 'array',
            ],
            'metadata' => [
                'manager' => 'opus-lstsar-manager',
                'site_id' => 'site-demo',
                'client_id' => 'client-demo',
                'dry_run_only' => true,
                'direct_execute_allowed' => false,
                'raw_sql_allowed' => false,
                'ddl_allowed' => false,
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function sampleDeclarationArray(): array
    {
        return $this->sampleDeclaration()->toArray();
    }

    public function sampleSourceModel(): TableModel
    {
        return new TableModel('source_orders_model', 'orders_source', [
            new ModelField('code', 'string', false, 8),
            new ModelField('amount', 'decimal', false, null, 8, 3),
        ], [
            'datasource' => 'source_dsn',
            'driver' => 'odbc',
            'manager_declared' => true,
        ]);
    }

    public function sampleDestinationModel(): TableModel
    {
        return new TableModel('destination_orders_model', 'orders_destination', [
            new ModelField('order_code', 'string', false, 4),
            new ModelField('total_amount', 'decimal', false, null, 8, 2),
            new ModelField('client_id', 'string', false, 32),
            new ModelField('created_by', 'string', false, 80),
            new ModelField('row_hash', 'string', false, 64),
        ], [
            'datasource' => 'destination_dsn',
            'driver' => 'odbc',
            'manager_declared' => true,
        ]);
    }

    /** @return array<string,mixed> */
    public function sampleSourceRecord(array $payload = []): array
    {
        $record = [
            'code' => ' ab ',
            'amount' => '12.345',
        ];

        $sourceRecord = $payload['source_record'] ?? null;
        if (is_array($sourceRecord)) {
            foreach ($sourceRecord as $field => $value) {
                if (is_string($field) && array_key_exists($field, $record) && (is_scalar($value) || $value === null)) {
                    $record[$field] = $value;
                }
            }
        }

        return $record;
    }

    public function sampleConfigForPayload(array $payload = []): LstsarConfig
    {
        $data = $this->sampleConfigArray();
        if (isset($payload['security']) && is_array($payload['security']) && array_key_exists('acl_granted', $payload['security'])) {
            $data['security']['acl_granted'] = (bool) $payload['security']['acl_granted'];
        }

        return LstsarConfig::fromArray($data);
    }
}
