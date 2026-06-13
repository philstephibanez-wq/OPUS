<?php

declare(strict_types=1);

namespace Opus\RefBook\Api;

use ASAP\RefBook\Attribute\OpusRefBookClass;
use ASAP\RefBook\Attribute\OpusRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;
use ASAP\RefBook\RefBookContractValidator;
use ASAP\RefBook\RefBookReflectionScanner;
use ASAP\RefBook\RefBookSnapshotBuilder;

/**
 * PUBLIC RefBook REST snapshot provider.
 *
 * Role:
 *   Builds the complete JSON payload exposed by the official read-only RefBook
 *   REST API.
 *
 * Responsibility:
 *   Compose Reflection technical truth, RefBook functional metadata, validation
 *   diagnostics, official examples and Mermaid diagrams.
 */
#[OpusRefBookClass(
    domain: 'RefBook',
    role: 'Build the official RefBook REST snapshot payload',
    responsibility: 'Compose scanner, validator, snapshot builder and documentation assets for the read-only RefBook API.',
    contracts: [
        'Reflection remains the only technical signature source.',
        'Attributes remain the functional documentation source.',
        'Examples and diagrams are official resources/refbook assets referenced by id.',
        'The provider does not render HTML and does not mutate source files.',
    ],
    examples: ['refbook-rest-api-client'],
    diagrams: ['framework-fsm-runtime', 'refbook-rest-api-flow'],
    introducedIn: 'P113D1'
)]
/*
 * OPUS_REFBOOK:
 *   domain: REFBOOK
 *   role: Class RefBookRestSnapshotProvider belongs to the REFBOOK Opus framework domain.
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
final class RefBookRestSnapshotProvider implements RefBookInspectableInterface
{
    public const API_VERSION = 'opus-refbook-rest/v1';


    #[OpusRefBookMethod(
        role: 'Expose the RefBook domain for the REST snapshot provider',
        behavior: 'Returns the stable RefBook domain used by snapshot and API consumers.',
        preconditions: ['none'],
        postconditions: ['The returned domain is RefBook.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookRestApiContractTest.php'],
        examples: ['refbook-rest-api-client'],
        diagrams: ['refbook-rest-api-flow'],
        introducedIn: 'P113D1A'
    )]
    public static function refBookDomain(): string
    {
        return 'RefBook';
    }

    public function __construct(private readonly string $projectRoot)
    {
        if (!is_dir($this->projectRoot)) {
            throw new \RuntimeException('OPUS_REFBOOK_PROJECT_ROOT_MISSING: ' . $this->projectRoot);
        }
    }

    #[OpusRefBookMethod(
        role: 'Build the full REST snapshot',
        behavior: 'Scans framework classes through Reflection, validates RefBook metadata and adds REST endpoints, examples and diagrams.',
        preconditions: ['The Opus project root exists.', 'framework/Opus exists.', 'resources/refbook exists.'],
        postconditions: ['Returns a versioned JSON-serializable RefBook REST payload.'],
        sideEffects: ['Reads PHP source files and documentation assets from disk.'],
        errors: ['OPUS_REFBOOK_PROJECT_ROOT_MISSING', 'OPUS_REFBOOK_SOURCE_ROOT_MISSING', 'OPUS_REFBOOK_ASSET_ROOT_MISSING'],
        testRefs: ['tests/Contract/RefBookRestApiContractTest.php'],
        examples: ['refbook-rest-api-client'],
        diagrams: ['refbook-rest-api-flow'],
        introducedIn: 'P113D1'
    )]
    public function snapshot(): array
    {
        $sourceRoot = $this->path('framework/Opus');
        $assetRoot = $this->path('resources/refbook');

        $scanner = new RefBookReflectionScanner();
        $scan = $scanner->scan($sourceRoot, 'ASAP');
        $validator = new RefBookContractValidator();
        $validation = $validator->validate($scan);
        $builder = new RefBookSnapshotBuilder();
        $snapshot = $builder->build($scan, $sourceRoot);
        $assets = new RefBookDocumentationAssetRepository($assetRoot);

        $snapshot['api'] = [
            'version' => self::API_VERSION,
            'style' => 'REST',
            'read_only' => true,
            'base_path' => '/api/refbook',
            'endpoints' => $this->endpoints(),
        ];
        $snapshot['validation'] = $validation;
        $snapshot['domains'] = $this->domains($snapshot['classes']);
        $snapshot['documentation_assets'] = $assets->index();

        return $snapshot;
    }

    #[OpusRefBookMethod(
        role: 'Return REST endpoint metadata',
        behavior: 'Returns the stable RefBook REST endpoint list consumed by smoke tests and OPUS_REF_BOOK.',
        preconditions: ['none'],
        postconditions: ['Returns endpoint metadata without scanning source files.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookRestApiContractTest.php'],
        examples: ['refbook-rest-api-client'],
        diagrams: ['refbook-rest-api-flow'],
        introducedIn: 'P113D1'
    )]
    public function endpoints(): array
    {
        return [
            ['method' => 'GET', 'path' => '/api/refbook/health', 'description' => 'RefBook API health and version.'],
            ['method' => 'GET', 'path' => '/api/refbook/snapshot', 'description' => 'Complete RefBook snapshot including domains, classes, examples and diagrams.'],
            ['method' => 'GET', 'path' => '/api/refbook/domains', 'description' => 'Classes grouped by RefBook domain.'],
            ['method' => 'GET', 'path' => '/api/refbook/classes', 'description' => 'Flat class index.'],
            ['method' => 'GET', 'path' => '/api/refbook/classes/{fqcn}', 'description' => 'One class entry. Encode backslashes as %5C.'],
            ['method' => 'GET', 'path' => '/api/refbook/examples/{id}', 'description' => 'One official code example.'],
            ['method' => 'GET', 'path' => '/api/refbook/diagrams/{id}', 'description' => 'One official Mermaid diagram.'],
        ];
    }

    private function domains(array $classes): array
    {
        $domains = [];
        foreach ($classes as $class) {
            $domain = (string) ($class['metadata']['domain'] ?? 'UNCLASSIFIED');
            if (!isset($domains[$domain])) {
                $domains[$domain] = ['name' => $domain, 'classes' => []];
            }
            $domains[$domain]['classes'][] = [
                'name' => $class['name'],
                'short_name' => $class['short_name'],
                'kind' => $class['kind'],
                'metadata' => $class['metadata'],
                'public_methods' => count($class['methods']),
            ];
        }
        ksort($domains);

        return array_values($domains);
    }

    private function path(string $relative): string
    {
        return rtrim($this->projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
