<?php

declare(strict_types=1);

namespace ASAP\RefBook;

use ASAP\RefBook\Model\RefBookScanResult;

/**
 * PUBLIC RefBook snapshot builder.
 *
 * Role:
 *   Builds a versioned, machine-readable snapshot that ASAP_REF_BOOK can consume
 *   through a file client today and through an official API later.
 */
final class RefBookSnapshotBuilder
{
    public const SCHEMA_VERSION = 'asap-refbook-snapshot/v1';

    /**
     * PUBLIC snapshot builder.
     *
     * @param RefBookScanResult $result Reflection scan result.
     * @param string $sourceRoot Source root represented by the snapshot.
     *
     * @return array<string,mixed> Versioned snapshot payload.
     */
    public function build(RefBookScanResult $result, string $sourceRoot): array
    {
        $payload = $result->toArray();

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'generated_at' => gmdate('c'),
            'source_root' => $sourceRoot,
            'producer' => 'ASAP\\RefBook\\RefBookSnapshotBuilder',
            'contract' => [
                'technical_truth' => 'PHP Reflection',
                'functional_truth' => 'AsapRefBookClass and AsapRefBookMethod attributes',
                'consumer' => 'ASAP_REF_BOOK snapshot/API client',
                'no_manual_signature_duplication' => true,
            ],
            'summary' => $payload['summary'],
            'load_errors' => $payload['load_errors'],
            'classes' => $payload['classes'],
            'schema_hints' => [
                'fsm_mermaid' => 'future-generator-from-real-fsm-data',
                'router_graph' => 'future-generator-from-real-route-data',
                'acl_matrix' => 'future-generator-from-real-acl-data',
                'security_dispatch_sequence' => 'future-generator-from-real-security-gate-data',
            ],
        ];
    }
}
