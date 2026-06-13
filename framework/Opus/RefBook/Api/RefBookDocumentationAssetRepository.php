<?php

declare(strict_types=1);

namespace Opus\RefBook\Api;

use ASAP\RefBook\Attribute\OpusRefBookClass;
use ASAP\RefBook\Attribute\OpusRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;

/**
 * PUBLIC RefBook documentation asset repository.
 *
 * Role:
 *   Reads official RefBook example and diagram files referenced by RefBook
 *   metadata and exposed by the RefBook REST API.
 *
 * Responsibility:
 *   Resolve stable asset identifiers to immutable file contents without scanning
 *   arbitrary paths and without falling back to generated placeholders.
 *
 * Contract:
 *   - only examples/*.php and diagrams/*.mmd under resources/refbook are exposed;
 *   - asset identifiers are stable file basenames, not file-system paths;
 *   - duplicate identifiers are explicit contract errors;
 *   - missing assets are returned as null and converted to 404 by the API.
 */
#[OpusRefBookClass(
    domain: 'RefBook',
    role: 'Expose official RefBook examples and diagrams by stable identifier',
    responsibility: 'Read documentation assets from resources/refbook for the RefBook REST API without path traversal or placeholder fallback.',
    contracts: [
        'Only resources/refbook/examples/*.php and resources/refbook/diagrams/*.mmd are exposed.',
        'Asset identifiers are validated and must not contain path separators.',
        'Duplicate asset identifiers fail explicitly.',
    ],
    examples: ['refbook-rest-api-client', 'fsm-state-machine-runtime'],
    diagrams: ['framework-fsm-runtime', 'refbook-rest-api-flow'],
    introducedIn: 'P113D1'
)]
/*
 * OPUS_REFBOOK:
 *   domain: REFBOOK
 *   role: Class RefBookDocumentationAssetRepository belongs to the REFBOOK Opus framework domain.
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
final class RefBookDocumentationAssetRepository implements RefBookInspectableInterface
{


    #[OpusRefBookMethod(
        role: 'Expose the RefBook domain for documentation assets',
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
    public function __construct(private readonly string $assetRoot)
    {
        if (!is_dir($this->assetRoot)) {
            throw new \RuntimeException('OPUS_REFBOOK_ASSET_ROOT_MISSING: ' . $this->assetRoot);
        }
    }

    #[OpusRefBookMethod(
        role: 'Return all official RefBook documentation assets',
        behavior: 'Scans the official resources/refbook examples and diagrams directories and returns a deterministic asset index.',
        preconditions: ['resources/refbook exists.', 'Asset identifiers are unique.'],
        postconditions: ['Examples and diagrams are returned as machine-readable arrays.'],
        sideEffects: ['Reads documentation files from disk.'],
        errors: ['OPUS_REFBOOK_ASSET_ROOT_MISSING', 'OPUS_REFBOOK_ASSET_DUPLICATE'],
        testRefs: ['tests/Contract/RefBookRestApiContractTest.php'],
        examples: ['refbook-rest-api-client'],
        diagrams: ['refbook-rest-api-flow'],
        introducedIn: 'P113D1'
    )]
    public function index(): array
    {
        return [
            'examples' => $this->scanAssetDirectory('examples', 'php', 'code/php'),
            'diagrams' => $this->scanAssetDirectory('diagrams', 'mmd', 'diagram/mermaid'),
        ];
    }

    #[OpusRefBookMethod(
        role: 'Return one code example by stable identifier',
        behavior: 'Finds one PHP example asset by identifier and returns its content or null when the asset does not exist.',
        preconditions: ['The identifier is a valid RefBook asset id.'],
        postconditions: ['Returns one example payload or null.'],
        sideEffects: ['Reads one documentation file from disk when present.'],
        errors: ['OPUS_REFBOOK_ASSET_ID_INVALID', 'OPUS_REFBOOK_ASSET_DUPLICATE'],
        testRefs: ['tests/Contract/RefBookRestApiContractTest.php'],
        examples: ['refbook-rest-api-client'],
        diagrams: ['refbook-rest-api-flow'],
        introducedIn: 'P113D1'
    )]
    public function example(string $id): ?array
    {
        return $this->assetById('examples', 'php', 'code/php', $id);
    }

    #[OpusRefBookMethod(
        role: 'Return one Mermaid diagram by stable identifier',
        behavior: 'Finds one Mermaid diagram asset by identifier and returns its content or null when the asset does not exist.',
        preconditions: ['The identifier is a valid RefBook asset id.'],
        postconditions: ['Returns one diagram payload or null.'],
        sideEffects: ['Reads one documentation file from disk when present.'],
        errors: ['OPUS_REFBOOK_ASSET_ID_INVALID', 'OPUS_REFBOOK_ASSET_DUPLICATE'],
        testRefs: ['tests/Contract/RefBookRestApiContractTest.php'],
        examples: ['refbook-rest-api-client'],
        diagrams: ['framework-fsm-runtime'],
        introducedIn: 'P113D1'
    )]
    public function diagram(string $id): ?array
    {
        return $this->assetById('diagrams', 'mmd', 'diagram/mermaid', $id);
    }

    private function assetById(string $directory, string $extension, string $format, string $id): ?array
    {
        $this->assertValidId($id);
        foreach ($this->scanAssetDirectory($directory, $extension, $format) as $asset) {
            if ($asset['id'] === $id) {
                return $asset;
            }
        }

        return null;
    }

    /** @return array<int,array<string,string>> */
    private function scanAssetDirectory(string $directory, string $extension, string $format): array
    {
        $dir = rtrim($this->assetRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $directory;
        if (!is_dir($dir)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $assets = [];
        $ids = [];

        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo || !$item->isFile()) {
                continue;
            }
            if (strtolower($item->getExtension()) !== $extension) {
                continue;
            }

            $id = $item->getBasename('.' . $extension);
            $this->assertValidId($id);
            if (isset($ids[$id])) {
                throw new \RuntimeException('OPUS_REFBOOK_ASSET_DUPLICATE: ' . $id);
            }
            $ids[$id] = true;

            $path = $item->getPathname();
            $content = file_get_contents($path);
            if (!is_string($content)) {
                throw new \RuntimeException('OPUS_REFBOOK_ASSET_READ_FAILED: ' . $path);
            }

            $assets[] = [
                'id' => $id,
                'type' => $directory === 'examples' ? 'example' : 'diagram',
                'format' => $format,
                'relative_path' => $this->relativePath($path),
                'content' => $content,
            ];
        }

        usort($assets, static fn (array $left, array $right): int => strcmp($left['id'], $right['id']));

        return $assets;
    }

    private function assertValidId(string $id): void
    {
        if ($id === '' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*$/', $id)) {
            throw new \InvalidArgumentException('OPUS_REFBOOK_ASSET_ID_INVALID: ' . $id);
        }
    }

    private function relativePath(string $path): string
    {
        $root = rtrim(str_replace('\\', '/', $this->assetRoot), '/');
        $normalized = str_replace('\\', '/', $path);
        if (str_starts_with($normalized, $root . '/')) {
            return 'resources/refbook/' . substr($normalized, strlen($root) + 1);
        }

        return $normalized;
    }
}
