<?php
declare(strict_types=1);

namespace Opus\Owasys;

use RuntimeException;

/** Orchestrates OWASYS application creation. */
final class ApplicationCreator
{
    private const RESULT_CONTRACT = 'OWASYS_APPLICATION_CREATION_RESULT_V1';
    private const SITE_CONTRACT = 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL';
    private const APPLICATION_FSM_CONTRACT = 'OPUS_APPLICATION_FSM_V1';

    /** @var list<string> */
    private const REQUIRED_SITE_PATHS = [
        'config',
        'config/site.json',
        'config/routes.json',
        'config/application.fsm.json',
        'config/profiler.json',
        'application',
        'application/default',
        'application/default/acl',
        'application/default/helpers',
        'application/default/helpers/GeneratedProfiler.php',
        'application/default/css',
        'application/default/javascript',
        'application/default/local',
        'application/default/models',
        'application/default/templates',
        'application/default/views',
        'application/states',
        'www',
        'www/index.php',
        'www/asset',
        'www/asset/css',
        'www/asset/css/profiler.css',
        'www/asset/js',
        'www/asset/js/profiler.js',
        'www/asset/themes',
    ];

    /** @var list<string> */
    private const FORBIDDEN_ROOTS = ['public', 'src', 'resources'];

    public function __construct(
        private readonly string $opusRoot,
        private readonly ?ScaffoldPlanBuilder $planBuilder = null,
        private readonly ?ApplicationScaffoldWriter $scaffoldWriter = null,
        private readonly ?GeneratedProfilerWriter $profilerWriter = null
    ) {
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    public function create(array $request, bool $write = false, bool $validate = false): array
    {
        $builder = $this->planBuilder ?? new ScaffoldPlanBuilder();
        $writer = $this->scaffoldWriter ?? new ApplicationScaffoldWriter($this->opusRoot);
        $profilerWriter = $this->profilerWriter ?? new GeneratedProfilerWriter($this->opusRoot);

        $plan = $builder->build($request);
        if (($plan['profiler']['enabled'] ?? null) !== true || ($plan['profiler']['mandatory'] ?? null) !== true) {
            throw new RuntimeException('OWASYS_APPLICATION_PROFILER_PLAN_INVALID');
        }

        $dryRunSummary = $writer->write($plan, true);
        $profilerDryRun = $profilerWriter->write($plan, true);

        $result = [
            'contract' => self::RESULT_CONTRACT,
            'mode' => $write ? 'write' : 'dry-run',
            'site_id' => (string) $plan['site_id'],
            'site_root' => (string) $plan['site_root'],
            'states_root' => (string) $plan['site_root'] . '/application/states',
            'application_fsm' => (string) $plan['site_root'] . '/config/application.fsm.json',
            'dry_run' => $dryRunSummary,
            'profiler' => $profilerDryRun,
            'write' => null,
            'validation' => ['status' => 'not-run'],
            'manifest' => null,
        ];

        if (!$write) {
            return $result;
        }

        $writeSummary = $writer->write($plan, false);
        $profilerSummary = $profilerWriter->write($plan, false);
        $validation = $validate ? $this->validateGeneratedSite($plan) : ['status' => 'not-run'];
        $manifest = $this->buildManifest($plan, $dryRunSummary, $writeSummary, $validation, $profilerSummary);
        $manifestPath = $this->writeCreationManifest($plan, $manifest);
        $manifest['path'] = $manifestPath;

        $result['write'] = $writeSummary;
        $result['profiler'] = $profilerSummary;
        $result['validation'] = $validation;
        $result['manifest'] = $manifest;

        return $result;
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
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

        if (file_exists($absoluteSiteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'home')) {
            throw new RuntimeException('OWASYS_CREATED_SITE_LEGACY_STATE_ROOT_PRESENT');
        }

        $siteConfigFile = $absoluteSiteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json';
        $siteConfig = json_decode((string) file_get_contents($siteConfigFile), true);
        if (!is_array($siteConfig) || ($siteConfig['contract'] ?? null) !== self::SITE_CONTRACT) {
            throw new RuntimeException('OWASYS_CREATED_SITE_CONTRACT_INVALID');
        }
        if (($siteConfig['application_fsm'] ?? null) !== 'config/application.fsm.json') {
            throw new RuntimeException('OWASYS_CREATED_SITE_APPLICATION_FSM_POINTER_INVALID');
        }
        if (($siteConfig['states_root'] ?? null) !== 'application/states' || ($siteConfig['dispatch_model'] ?? null) !== 'state-first') {
            throw new RuntimeException('OWASYS_CREATED_SITE_STATE_ROOT_INVALID');
        }

        $fsmFile = $absoluteSiteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'application.fsm.json';
        $fsm = json_decode((string) file_get_contents($fsmFile), true);
        if (!is_array($fsm) || ($fsm['contract'] ?? null) !== self::APPLICATION_FSM_CONTRACT) {
            throw new RuntimeException('OWASYS_CREATED_SITE_APPLICATION_FSM_INVALID');
        }
        if (($fsm['site_id'] ?? null) !== $siteId || ($fsm['dispatch_model'] ?? null) !== 'state-first' || empty($fsm['states']) || !array_key_exists('initial_state', $fsm) || !isset($fsm['transitions']) || !is_array($fsm['transitions'])) {
            throw new RuntimeException('OWASYS_CREATED_SITE_APPLICATION_FSM_INCOMPLETE');
        }

        $profilerConfigFile = $absoluteSiteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'profiler.json';
        $profilerConfig = json_decode((string) file_get_contents($profilerConfigFile), true);
        if (!is_array($profilerConfig)
            || ($profilerConfig['contract'] ?? null) !== GeneratedProfilerWriter::CONTRACT
            || ($profilerConfig['mandatory'] ?? null) !== true
            || ($profilerConfig['production_available'] ?? null) !== false
            || ($profilerConfig['environment'] ?? null) !== 'dev-only') {
            throw new RuntimeException('OWASYS_CREATED_SITE_PROFILER_CONFIG_INVALID');
        }

        $front = (string) file_get_contents($absoluteSiteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'index.php');
        if (!str_contains($front, 'OPUS_GENERATED_PROFILER_BOOTSTRAP')) {
            throw new RuntimeException('OWASYS_CREATED_SITE_PROFILER_BOOTSTRAP_MISSING');
        }

        $runtime = (string) file_get_contents($absoluteSiteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'GeneratedProfiler.php');
        foreach (["['dev', 'local', 'development']", "return null;", "\$_GET['profiler']"] as $marker) {
            if (!str_contains($runtime, $marker)) {
                throw new RuntimeException('OWASYS_CREATED_SITE_PROFILER_RUNTIME_INVALID:' . $marker);
            }
        }

        return [
            'status' => 'ok',
            'site_id' => $siteId,
            'site_root' => $siteRoot,
            'states_root' => $siteRoot . '/application/states',
            'application_fsm' => $siteRoot . '/config/application.fsm.json',
            'profiler' => GeneratedProfilerWriter::CONTRACT,
            'profiler_mandatory' => true,
            'profiler_production_available' => false,
            'required_paths' => count(self::REQUIRED_SITE_PATHS),
            'command' => 'php bin/opus validate:site ' . $siteId,
        ];
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $dryRunSummary
     * @param array<string,mixed> $writeSummary
     * @param array<string,mixed> $validation
     * @param array<string,mixed> $profilerSummary
     * @return array<string,mixed>
     */
    private function buildManifest(array $plan, array $dryRunSummary, array $writeSummary, array $validation, array $profilerSummary): array
    {
        return [
            'contract' => 'OWASYS_APPLICATION_CREATION_MANIFEST_V1',
            'generator' => self::class,
            'generated_at_utc' => gmdate('c'),
            'site_id' => (string) $plan['site_id'],
            'site_root' => (string) $plan['site_root'],
            'site_contract' => self::SITE_CONTRACT,
            'states_root' => (string) $plan['site_root'] . '/application/states',
            'application_fsm' => (string) $plan['site_root'] . '/config/application.fsm.json',
            'application_fsm_contract' => self::APPLICATION_FSM_CONTRACT,
            'owasys_plan_contract' => (string) ($plan['owasys_contract'] ?? ''),
            'blueprint' => (string) ($plan['blueprint'] ?? ''),
            'profiler' => $profilerSummary,
            'profiler_mandatory' => true,
            'profiler_production_available' => false,
            'dry_run' => $dryRunSummary,
            'write' => $writeSummary,
            'validation' => $validation,
            'forbidden_output_roots' => ['public', 'src', 'resources'],
        ];
    }

    /** @param array<string,mixed> $plan @param array<string,mixed> $manifest */
    private function writeCreationManifest(array $plan, array $manifest): string
    {
        $siteRoot = $this->normalizeRelativePath((string) ($plan['site_root'] ?? ''));
        $path = $this->absolutePath($siteRoot . '/config/owasys-creation-manifest.json');
        if (file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n") === false) {
            throw new RuntimeException('OWASYS_CREATION_MANIFEST_WRITE_FAILED');
        }
        return $siteRoot . '/config/owasys-creation-manifest.json';
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1 || str_contains($path, '..')) {
            throw new RuntimeException('OWASYS_CREATED_SITE_PATH_INVALID');
        }
        return $path;
    }

    private function absolutePath(string $relativePath): string
    {
        return rtrim($this->opusRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }
}
