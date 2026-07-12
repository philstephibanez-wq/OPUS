<?php
declare(strict_types=1);

namespace Opus\Owasys;

use RuntimeException;

/**
 * Orchestrates OWASYS application creation.
 *
 * The creator composes the request validator/plan builder with the scaffold writer.
 * Dry-run is always executed first. Real write is only executed when the caller
 * explicitly asks for it, and validation is explicit as a separate step.
 */
final class ApplicationCreator
{
    private const RESULT_CONTRACT = 'OWASYS_APPLICATION_CREATION_RESULT_V1';
    private const SITE_CONTRACT = 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL';

    /** @var list<string> */
    private const REQUIRED_SITE_PATHS = [
        'config',
        'config/site.json',
        'config/routes.json',
        'application',
        'application/default',
        'application/default/acl',
        'application/default/helpers',
        'application/default/css',
        'application/default/javascript',
        'application/default/local',
        'application/default/models',
        'application/default/templates',
        'application/default/views',
        'www',
        'www/index.php',
        'www/asset',
        'www/asset/css',
        'www/asset/js',
        'www/asset/themes',
    ];

    /** @var list<string> */
    private const FORBIDDEN_ROOTS = ['public', 'src', 'resources'];

    public function __construct(
        private readonly string $opusRoot,
        private readonly ?ScaffoldPlanBuilder $planBuilder = null,
        private readonly ?ApplicationScaffoldWriter $scaffoldWriter = null
    ) {
    }

    /**
     * Creates or previews an OPUS application from an OWASYS request.
     *
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    public function create(array $request, bool $write = false, bool $validate = false): array
    {
        $builder = $this->planBuilder ?? new ScaffoldPlanBuilder();
        $writer = $this->scaffoldWriter ?? new ApplicationScaffoldWriter($this->opusRoot);

        $plan = $builder->build($request);
        $dryRunSummary = $writer->write($plan, true);

        $result = [
            'contract' => self::RESULT_CONTRACT,
            'mode' => $write ? 'write' : 'dry-run',
            'site_id' => (string) $plan['site_id'],
            'site_root' => (string) $plan['site_root'],
            'dry_run' => $dryRunSummary,
            'write' => null,
            'validation' => ['status' => 'not-run'],
            'manifest' => null,
        ];

        if (!$write) {
            return $result;
        }

        $writeSummary = $writer->write($plan, false);
        $validation = $validate ? $this->validateGeneratedSite($plan) : ['status' => 'not-run'];
        $manifest = $this->buildManifest($plan, $dryRunSummary, $writeSummary, $validation);
        $manifestPath = $this->writeCreationManifest($plan, $manifest);
        $manifest['path'] = $manifestPath;

        $result['write'] = $writeSummary;
        $result['validation'] = $validation;
        $result['manifest'] = $manifest;

        return $result;
    }

    /**
     * Validates the generated OPUS site tree without shelling out.
     *
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private function validateGeneratedSite(array $plan): array
    {
        $siteId = (string) ($plan['site_id'] ?? '');
        $siteRoot = $this->normalizeRelativePath((string) ($plan['site_root'] ?? ''));
        $absoluteSiteRoot = $this->absolutePath($siteRoot);

        if (!is_dir($absoluteSiteRoot)) {
            throw new RuntimeException('OWASYS_CREATED_SITE_ROOT_MISSING: ' . $siteRoot);
        }

        foreach (self::FORBIDDEN_ROOTS as $root) {
            if (file_exists($absoluteSiteRoot . DIRECTORY_SEPARATOR . $root)) {
                throw new RuntimeException('OWASYS_CREATED_SITE_FORBIDDEN_ROOT_PRESENT: ' . $root);
            }
        }

        foreach (self::REQUIRED_SITE_PATHS as $relative) {
            $path = $absoluteSiteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (!file_exists($path)) {
                throw new RuntimeException('OWASYS_CREATED_SITE_REQUIRED_PATH_MISSING: ' . $relative);
            }
        }

        $siteConfigFile = $absoluteSiteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json';
        $siteConfig = json_decode((string) file_get_contents($siteConfigFile), true);
        if (!is_array($siteConfig) || ($siteConfig['contract'] ?? null) !== self::SITE_CONTRACT) {
            throw new RuntimeException('OWASYS_CREATED_SITE_CONTRACT_INVALID');
        }

        return [
            'status' => 'ok',
            'site_id' => $siteId,
            'site_root' => $siteRoot,
            'required_paths' => count(self::REQUIRED_SITE_PATHS),
            'command' => 'php bin/opus validate:site ' . $siteId,
        ];
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $dryRunSummary
     * @param array<string,mixed> $writeSummary
     * @param array<string,mixed> $validation
     * @return array<string,mixed>
     */
    private function buildManifest(array $plan, array $dryRunSummary, array $writeSummary, array $validation): array
    {
        return [
            'contract' => 'OWASYS_APPLICATION_CREATION_MANIFEST_V1',
            'generator' => self::class,
            'generated_at_utc' => gmdate('c'),
            'site_id' => (string) $plan['site_id'],
            'site_root' => (string) $plan['site_root'],
            'site_contract' => self::SITE_CONTRACT,
            'owasys_plan_contract' => (string) ($plan['owasys_contract'] ?? ''),
            'blueprint' => (string) ($plan['blueprint'] ?? ''),
            'dry_run' => $dryRunSummary,
            'write' => $writeSummary,
            'validation' => $validation,
            'forbidden_output_roots' => ['public', 'src', 'resources'],
        ];
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $manifest
     */
    private function writeCreationManifest(array $plan, array $manifest): string
    {
        $siteRoot = $this->normalizeRelativePath((string) ($plan['site_root'] ?? ''));
        $relativeManifestPath = $siteRoot . '/config/owasys-creation-manifest.json';
        $absoluteManifestPath = $this->absolutePath($relativeManifestPath);

        if (file_exists($absoluteManifestPath)) {
            throw new RuntimeException('OWASYS_CREATION_MANIFEST_ALREADY_EXISTS: ' . $relativeManifestPath);
        }

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new RuntimeException('OWASYS_CREATION_MANIFEST_JSON_ENCODE_FAILED');
        }

        if (file_put_contents($absoluteManifestPath, $json . "\n") === false) {
            throw new RuntimeException('OWASYS_CREATION_MANIFEST_WRITE_FAILED: ' . $relativeManifestPath);
        }

        return $relativeManifestPath;
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) === 1) {
            throw new RuntimeException('OWASYS_RELATIVE_PATH_INVALID: ' . $path);
        }
        return $path;
    }

    private function absolutePath(string $relativePath): string
    {
        return rtrim($this->opusRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->normalizeRelativePath($relativePath));
    }
}
