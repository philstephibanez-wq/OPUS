<?php
declare(strict_types=1);

namespace OpusLstsarManager\Config;

use Opus\Lstsar\LstsarBackofficeDeclaration;
use Opus\Lstsar\LstsarConfig;

/**
 * Deterministic declaration repository for the first LSTSAR Manager milestone.
 *
 * Later milestones can replace this with persistence, but the contract already
 * exposes the complete source/destination/model/mapping policy surface.
 */
final class LstsarManagerDeclarationRepository
{
    public function sampleDeclaration(): LstsarBackofficeDeclaration
    {
        return new LstsarBackofficeDeclaration(
            LstsarConfig::fromArray([
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
                    'acl_required' => true,
                    'anonymous' => false,
                ],
                'transform' => [
                    'code' => ['trim' => true, 'uppercase' => true],
                    'amount' => ['cast' => 'float', 'round' => 2],
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
                    'dry_run_only' => true,
                ],
            ]),
            ['source', 'destination', 'mapping', 'security', 'transform', 'archive', 'report']
        );
    }

    /** @return array<string,mixed> */
    public function sampleDeclarationArray(): array
    {
        return $this->sampleDeclaration()->toArray();
    }
}
