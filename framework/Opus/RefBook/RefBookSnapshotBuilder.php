<?php

declare(strict_types=1);

namespace Opus\RefBook;

use ASAP\RefBook\Model\RefBookScanResult;

/*
 * OPUS_REFBOOK:
 *   domain: REFBOOK
 *   role: Class RefBookSnapshotBuilder belongs to the REFBOOK Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the REFBOOK domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - refbook-overview
 *   diagrams:
 *     - refbook-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC RefBook snapshot builder.
 *
 * Role:
 *   Builds a versioned, machine-readable snapshot that OPUS_REF_BOOK can consume
 *   through a file client today and through an official API later.
 */
final class RefBookSnapshotBuilder
{
    public const SCHEMA_VERSION = 'opus-refbook-snapshot/v1';

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
            'producer' => 'Opus\\RefBook\\RefBookSnapshotBuilder',
            'contract' => [
                'technical_truth' => 'PHP Reflection',
                'functional_truth' => 'OpusRefBookClass and OpusRefBookMethod attributes',
                'consumer' => 'OPUS_REF_BOOK snapshot/API client',
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
