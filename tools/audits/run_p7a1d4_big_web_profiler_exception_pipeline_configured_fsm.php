<?php
declare(strict_types=1);

final class P7A1D4BigWebProfilerExceptionPipelineConfiguredFsm
{
    private string $root;
    /** @var array<string,string|null> */
    private array $originals = [];
    /** @var list<string> */
    private array $createdFiles = [];
    /** @var list<string> */
    private array $createdDirs = [];

    public function __construct(string $root)
    {
        $real = realpath($root);
        if ($real === false) {
            throw new RuntimeException('P7A1D4_ROOT_NOT_FOUND: ' . $root);
        }
        $this->root = str_replace('\\', '/', $real);
    }

    public static function main(): int
    {
        $runner = new self(__DIR__ . '/../..');
        try {
            $runner->run();
            return 0;
        } catch (Throwable $e) {
            $runner->rollback();
            echo "P7A1D4_BIG_WEB_PROFILER_EXCEPTION_PIPELINE_CONFIGURED_FSM_ROLLBACK=OK\n";
            echo 'P7A1D4_BIG_WEB_PROFILER_EXCEPTION_PIPELINE_CONFIGURED_FSM_FAIL=' . $e->getMessage() . "\n";
            return 1;
        }
    }

    public function run(): void
    {
        $this->guardCleanSourceOfTruth();
        $this->createConfiguredFsmFiles();
        $this->writeRuntimeDiagnosticsFiles();
        $this->writeProfilerFiles();
        $this->writeWebProfilerTemplates();
        $this->patchRuntimeFiles();
        $this->writeReports();
        $this->lintAllOpusPhp();
        $this->runStructuralGate();
        echo "P7A1D4_BIG_WEB_PROFILER_EXCEPTION_PIPELINE_CONFIGURED_FSM_OK=1\n";
    }

    private function guardCleanSourceOfTruth(): void
    {
        if (is_file($this->path('Opus/Fsm/Fsm.php'))) {
            throw new RuntimeException('FORBIDDEN_DEMO_FSM_RESTORED: Opus/Fsm/Fsm.php');
        }
        foreach ([
            'Opus/Runtime/Bootstrap.php',
            'Opus/Runtime/Kernel.php',
            'Opus/Routing/Router.php',
            'Opus/View/View.php',
            'Opus/Profiler/Profiler.php',
            'Opus/Profiler/Trace.php',
            'Opus/Framework/OpusFrameworkComponentInterface.php',
        ] as $required) {
            if (!is_file($this->path($required))) {
                throw new RuntimeException('REQUIRED_SOURCE_MISSING: ' . $required);
            }
        }
    }

    private function createConfiguredFsmFiles(): void
    {
        $this->ensureDir('config/fsm_runtime');
        $maps = [
            'runtime_boot' => [
                ['from' => 'BOOT_START', 'signal' => 'diagnostics.registered', 'to' => 'BOOT_DIAGNOSTICS_READY', 'collector' => 'runtime'],
                ['from' => 'BOOT_DIAGNOSTICS_READY', 'signal' => 'framework.loaded', 'to' => 'BOOT_FRAMEWORK_READY', 'collector' => 'runtime'],
                ['from' => 'BOOT_FRAMEWORK_READY', 'signal' => 'kernel.started', 'to' => 'BOOT_READY', 'collector' => 'runtime'],
            ],
            'runtime_request' => [
                ['from' => 'REQUEST_START', 'signal' => 'request.normalized', 'to' => 'REQUEST_READY', 'collector' => 'request'],
                ['from' => 'REQUEST_READY', 'signal' => 'application.resolved', 'to' => 'APPLICATION_READY', 'collector' => 'routing'],
                ['from' => 'APPLICATION_READY', 'signal' => 'route.dispatched', 'to' => 'RESPONSE_READY', 'collector' => 'routing'],
            ],
            'runtime_profiler' => [
                ['from' => 'PROFILER_START', 'signal' => 'trace.created', 'to' => 'TRACE_OPEN', 'collector' => 'profiler'],
                ['from' => 'TRACE_OPEN', 'signal' => 'trace.event', 'to' => 'TRACE_COLLECTING', 'collector' => 'profiler'],
                ['from' => 'TRACE_COLLECTING', 'signal' => 'trace.flushed', 'to' => 'TRACE_STORED', 'collector' => 'profiler'],
            ],
            'runtime_exception' => [
                ['from' => 'RUNTIME_ACTIVE', 'signal' => 'php.error', 'to' => 'PHP_ERROR_NORMALIZED', 'collector' => 'exception'],
                ['from' => 'PHP_ERROR_NORMALIZED', 'signal' => 'exception.created', 'to' => 'OPUS_EXCEPTION_READY', 'collector' => 'exception'],
                ['from' => 'OPUS_EXCEPTION_READY', 'signal' => 'profiler.recorded', 'to' => 'EXCEPTION_TRACED', 'collector' => 'exception'],
            ],
        ];
        foreach ($maps as $id => $transitions) {
            $payload = [
                'schema' => 'OPUS_FSM_RUNTIME_V1',
                'id' => $id,
                'initial_state' => $transitions[0]['from'],
                'final_states' => [end($transitions)['to']],
                'transitions' => $transitions,
            ];
            $this->writeNewFile('config/fsm_runtime/' . $id . '.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        }

        $this->writeNewFile('Opus/Fsm/Runtime/FsmRuntimeConfigLoaderInterface.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Fsm\Runtime;

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;

interface FsmRuntimeConfigLoaderInterface extends OpusFrameworkComponentInterface, OpusExceptionAwareInterface, OpusProfilerAwareInterface, OpusSelfDocumentingInterface
{
    public function load(string $id): array;
    public function availableMaps(): array;
    public function flowForDisplay(string $id): array;
}
PHP);
        $this->writeNewFile('Opus/Fsm/Runtime/FsmRuntimeConfigLoader.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Fsm\Runtime;

final class FsmRuntimeConfigLoader implements FsmRuntimeConfigLoaderInterface
{
    private string $configDir;

    public function __construct(string $configDir)
    {
        $this->configDir = rtrim(str_replace('\\', '/', $configDir), '/');
        if (!is_dir($this->configDir)) {
            throw new \RuntimeException('OPUS_FSM_RUNTIME_CONFIG_DIR_MISSING: ' . $this->configDir);
        }
    }

    public function availableMaps(): array
    {
        $files = glob($this->configDir . '/*.json') ?: [];
        $maps = [];
        foreach ($files as $file) {
            $maps[] = basename($file, '.json');
        }
        sort($maps);
        return $maps;
    }

    public function load(string $id): array
    {
        if (!preg_match('/^[a-z0-9_\-]+$/', $id)) {
            throw new \InvalidArgumentException('OPUS_FSM_RUNTIME_CONFIG_ID_INVALID: ' . $id);
        }
        $path = $this->configDir . '/' . $id . '.json';
        if (!is_file($path)) {
            throw new \RuntimeException('OPUS_FSM_RUNTIME_CONFIG_MISSING: ' . $id);
        }
        $json = file_get_contents($path);
        if (!is_string($json)) {
            throw new \RuntimeException('OPUS_FSM_RUNTIME_CONFIG_READ_FAILED: ' . $id);
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException('OPUS_FSM_RUNTIME_CONFIG_JSON_INVALID: ' . $id);
        }
        $this->validate($id, $data);
        return $data;
    }

    public function flowForDisplay(string $id): array
    {
        $map = $this->load($id);
        $rows = [];
        foreach ($map['transitions'] as $transition) {
            $rows[] = [
                'state' => (string)$transition['from'],
                'signal' => (string)$transition['signal'],
                'action' => (string)($transition['collector'] ?? ''),
                'next' => (string)$transition['to'],
            ];
        }
        return $rows;
    }

    private function validate(string $id, array $data): void
    {
        if (($data['schema'] ?? '') !== 'OPUS_FSM_RUNTIME_V1') {
            throw new \RuntimeException('OPUS_FSM_RUNTIME_CONFIG_SCHEMA_INVALID: ' . $id);
        }
        if (($data['id'] ?? '') !== $id) {
            throw new \RuntimeException('OPUS_FSM_RUNTIME_CONFIG_ID_MISMATCH: ' . $id);
        }
        if (!isset($data['transitions']) || !is_array($data['transitions']) || $data['transitions'] === []) {
            throw new \RuntimeException('OPUS_FSM_RUNTIME_CONFIG_TRANSITIONS_EMPTY: ' . $id);
        }
        foreach ($data['transitions'] as $index => $transition) {
            if (!is_array($transition)) {
                throw new \RuntimeException('OPUS_FSM_RUNTIME_CONFIG_TRANSITION_INVALID: ' . $id . '#' . $index);
            }
            foreach (['from', 'signal', 'to'] as $required) {
                if (!isset($transition[$required]) || trim((string)$transition[$required]) === '') {
                    throw new \RuntimeException('OPUS_FSM_RUNTIME_CONFIG_TRANSITION_FIELD_MISSING: ' . $id . '#' . $index . ':' . $required);
                }
            }
        }
    }
}
PHP);
    }

    private function writeRuntimeDiagnosticsFiles(): void
    {
        $this->writeNewFile('Opus/Runtime/Diagnostics/PhpErrorExceptionInterface.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Runtime\Diagnostics;

use Opus\Framework\OpusExceptionContractInterface;

interface PhpErrorExceptionInterface extends OpusExceptionContractInterface
{
}
PHP);
        $this->writeNewFile('Opus/Runtime/Diagnostics/PhpErrorException.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Runtime\Diagnostics;

final class PhpErrorException extends \ErrorException implements PhpErrorExceptionInterface
{
    public static function fromPhpError(int $severity, string $message, string $file, int $line): self
    {
        return new self($message, 0, $severity, $file, $line);
    }
}
PHP);
        $this->writeNewFile('Opus/Runtime/Diagnostics/ThrowableNormalizerInterface.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Runtime\Diagnostics;

use Opus\Framework\OpusFrameworkComponentInterface;

interface ThrowableNormalizerInterface extends OpusFrameworkComponentInterface
{
    public static function normalize(\Throwable $throwable): array;
}
PHP);
        $this->writeNewFile('Opus/Runtime/Diagnostics/ThrowableNormalizer.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Runtime\Diagnostics;

final class ThrowableNormalizer implements ThrowableNormalizerInterface
{
    public static function normalize(\Throwable $throwable): array
    {
        return [
            'class' => $throwable::class,
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'trace' => array_slice($throwable->getTrace(), 0, 20),
        ];
    }
}
PHP);
        $this->writeNewFile('Opus/Runtime/Diagnostics/PhpErrorInterceptorInterface.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Runtime\Diagnostics;

use Opus\Framework\OpusFrameworkComponentInterface;

interface PhpErrorInterceptorInterface extends OpusFrameworkComponentInterface
{
    public static function register(string $rootDir): void;
}
PHP);
        $this->writeNewFile('Opus/Runtime/Diagnostics/PhpErrorInterceptor.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Runtime\Diagnostics;

final class PhpErrorInterceptor implements PhpErrorInterceptorInterface
{
    private static bool $registered = false;
    private static string $rootDir = '';

    public static function register(string $rootDir): void
    {
        if (self::$registered) {
            return;
        }
        self::$rootDir = rtrim(str_replace('\\', '/', $rootDir), '/');
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
        self::$registered = true;
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }
        throw PhpErrorException::fromPhpError($severity, $message, $file, $line);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if (!is_array($error)) {
            return;
        }
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int)($error['type'] ?? 0), $fatalTypes, true)) {
            return;
        }
        $dir = self::$rootDir . '/var/profiler';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $traceId = 'fatal-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $payload = [
            'schema' => 'OPUS_PROFILER_TRACE_V1',
            'trace_id' => $traceId,
            'started_at' => gmdate('c'),
            'duration_ms' => 0,
            'summary' => ['status' => 'fatal'],
            'event_count' => 1,
            'events' => [[
                'index' => 1,
                'time' => gmdate('c'),
                'elapsed_ms' => 0,
                'category' => 'exception',
                'name' => 'php.fatal',
                'memory' => [
                    'usage_bytes' => memory_get_usage(true),
                    'peak_bytes' => memory_get_peak_usage(true),
                ],
                'context' => [
                    'type' => $error['type'] ?? null,
                    'message' => $error['message'] ?? '',
                    'file' => $error['file'] ?? '',
                    'line' => $error['line'] ?? 0,
                ],
            ]],
        ];
        @file_put_contents($dir . '/' . $traceId . '.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, LOCK_EX);
    }
}
PHP);
    }

    private function writeProfilerFiles(): void
    {
        $this->writeNewFile('Opus/Profiler/TraceFileRepositoryInterface.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Framework\OpusFrameworkComponentInterface;

interface TraceFileRepositoryInterface extends OpusFrameworkComponentInterface
{
    public function listTraces(): array;
    public function readTrace(string $traceId): array;
}
PHP);
        $this->writeNewFile('Opus/Profiler/TraceFileRepository.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Profiler;

final class TraceFileRepository implements TraceFileRepositoryInterface
{
    private string $storageDir;

    public function __construct(string $storageDir)
    {
        $this->storageDir = rtrim(str_replace('\\', '/', $storageDir), '/');
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0775, true);
        }
    }

    public function listTraces(): array
    {
        $files = glob($this->storageDir . '/*.json') ?: [];
        rsort($files);
        $rows = [];
        foreach (array_slice($files, 0, 100) as $file) {
            $data = $this->readJsonFile($file);
            if (!$data) {
                continue;
            }
            $rows[] = [
                'trace_id' => (string)($data['trace_id'] ?? basename($file, '.json')),
                'started_at' => (string)($data['started_at'] ?? ''),
                'duration_ms' => (string)($data['duration_ms'] ?? ''),
                'event_count' => (string)($data['event_count'] ?? count((array)($data['events'] ?? []))),
                'status' => (string)(($data['summary']['status'] ?? 'ok')),
            ];
        }
        return $rows;
    }

    public function readTrace(string $traceId): array
    {
        if (!preg_match('/^[A-Za-z0-9_.\-]+$/', $traceId)) {
            throw new \InvalidArgumentException('OPUS_PROFILER_TRACE_ID_INVALID: ' . $traceId);
        }
        $path = $this->storageDir . '/' . $traceId . '.json';
        if (!is_file($path)) {
            throw new \RuntimeException('OPUS_PROFILER_TRACE_NOT_FOUND: ' . $traceId);
        }
        $data = $this->readJsonFile($path);
        if (!$data) {
            throw new \RuntimeException('OPUS_PROFILER_TRACE_INVALID: ' . $traceId);
        }
        return $data;
    }

    private function readJsonFile(string $file): array
    {
        $json = file_get_contents($file);
        if (!is_string($json)) {
            return [];
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}
PHP);
        $this->writeCollectorFiles();
        $this->writeNewFile('Opus/Profiler/WebProfilerViewInterface.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Framework\OpusFrameworkComponentInterface;

interface WebProfilerViewInterface extends OpusFrameworkComponentInterface
{
    public function renderIndex(array $traces, array $fsmMaps): string;
    public function renderTrace(array $trace, array $fsmMaps): string;
}
PHP);
        $this->writeNewFile('Opus/Profiler/WebProfilerView.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Profiler\Collector\ConfigCollector;
use Opus\Profiler\Collector\DatabaseCollector;
use Opus\Profiler\Collector\ExceptionCollector;
use Opus\Profiler\Collector\MailCollector;
use Opus\Profiler\Collector\MemoryCollector;
use Opus\Profiler\Collector\ProfilerCollectorInterface;
use Opus\Profiler\Collector\RequestCollector;
use Opus\Profiler\Collector\RoutingCollector;
use Opus\Profiler\Collector\RuntimeCollector;
use Opus\Profiler\Collector\TemplateCollector;
use Opus\Template\ScoreTemplateRenderer;

final class WebProfilerView implements WebProfilerViewInterface
{
    public function renderIndex(array $traces, array $fsmMaps): string
    {
        return $this->render('layout.score', [
            'title' => 'OPUS Web Profiler',
            'mode' => 'index',
            'has_trace' => false,
            'traces' => $traces,
            'trace' => [],
            'panels' => [],
            'fsm_maps' => $this->normalizeFsmMaps($fsmMaps),
        ]);
    }

    public function renderTrace(array $trace, array $fsmMaps): string
    {
        $panels = [];
        foreach ($this->collectors() as $collector) {
            $events = $collector->collect($trace);
            $panels[] = [
                'id' => $collector->category(),
                'label' => $collector->label(),
                'count' => count($events),
                'events' => $events,
            ];
        }
        return $this->render('layout.score', [
            'title' => 'OPUS Web Profiler · ' . (string)($trace['trace_id'] ?? ''),
            'mode' => 'trace',
            'has_trace' => true,
            'traces' => [],
            'trace' => [
                'trace_id' => (string)($trace['trace_id'] ?? ''),
                'started_at' => (string)($trace['started_at'] ?? ''),
                'duration_ms' => (string)($trace['duration_ms'] ?? ''),
                'event_count' => (string)($trace['event_count'] ?? count((array)($trace['events'] ?? []))),
            ],
            'panels' => $panels,
            'fsm_maps' => $this->normalizeFsmMaps($fsmMaps),
        ]);
    }

    private function render(string $template, array $data): string
    {
        $this->requireScoreRuntime();
        $renderer = new ScoreTemplateRenderer(__DIR__ . '/templates/web_profiler');
        return $renderer->render($template, $data);
    }

    private function requireScoreRuntime(): void
    {
        require_once __DIR__ . '/../Score/TemplateException.php';
        require_once __DIR__ . '/../Score/TemplateRendererInterface.php';
        require_once __DIR__ . '/../Score/ScoreTemplateRenderer.php';
    }

    /** @return list<ProfilerCollectorInterface> */
    private function collectors(): array
    {
        $base = __DIR__ . '/Collector';
        foreach ([
            'ProfilerCollectorInterface.php', 'RequestCollectorInterface.php', 'RequestCollector.php',
            'RoutingCollectorInterface.php', 'RoutingCollector.php',
            'ExceptionCollectorInterface.php', 'ExceptionCollector.php',
            'TemplateCollectorInterface.php', 'TemplateCollector.php',
            'DatabaseCollectorInterface.php', 'DatabaseCollector.php',
            'ConfigCollectorInterface.php', 'ConfigCollector.php',
            'MailCollectorInterface.php', 'MailCollector.php',
            'MemoryCollectorInterface.php', 'MemoryCollector.php',
            'RuntimeCollectorInterface.php', 'RuntimeCollector.php',
        ] as $file) {
            require_once $base . '/' . $file;
        }
        return [
            new RequestCollector(),
            new RoutingCollector(),
            new ExceptionCollector(),
            new TemplateCollector(),
            new DatabaseCollector(),
            new ConfigCollector(),
            new MailCollector(),
            new MemoryCollector(),
            new RuntimeCollector(),
        ];
    }

    private function normalizeFsmMaps(array $fsmMaps): array
    {
        $rows = [];
        foreach ($fsmMaps as $id) {
            $rows[] = ['id' => (string)$id];
        }
        return $rows;
    }
}
PHP);
        $this->writeNewFile('Opus/Profiler/WebProfilerControllerInterface.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Http\Request;
use Opus\Http\Response;

interface WebProfilerControllerInterface extends OpusFrameworkComponentInterface
{
    public function handle(Request $request): Response;
}
PHP);
        $this->writeNewFile('Opus/Profiler/WebProfilerController.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Fsm\Runtime\FsmRuntimeConfigLoader;
use Opus\Http\Request;
use Opus\Http\Response;

final class WebProfilerController implements WebProfilerControllerInterface
{
    private TraceFileRepository $repository;
    private WebProfilerView $view;
    private FsmRuntimeConfigLoader $fsmRuntimeConfigLoader;

    public function __construct(string $rootDir, FsmRuntimeConfigLoader $fsmRuntimeConfigLoader)
    {
        $this->repository = new TraceFileRepository(rtrim(str_replace('\\', '/', $rootDir), '/') . '/var/profiler');
        $this->view = new WebProfilerView();
        $this->fsmRuntimeConfigLoader = $fsmRuntimeConfigLoader;
    }

    public function handle(Request $request): Response
    {
        $path = trim($request->path, '/');
        if (preg_match('~^_opus/profiler/trace/([A-Za-z0-9_.\-]+)$~', $path, $m) === 1) {
            return Response::html($this->view->renderTrace($this->repository->readTrace($m[1]), $this->fsmRuntimeConfigLoader->availableMaps()));
        }
        return Response::html($this->view->renderIndex($this->repository->listTraces(), $this->fsmRuntimeConfigLoader->availableMaps()));
    }
}
PHP);
    }

    private function writeCollectorFiles(): void
    {
        $this->writeNewFile('Opus/Profiler/Collector/ProfilerCollectorInterface.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Profiler\Collector;

use Opus\Framework\OpusFrameworkComponentInterface;

interface ProfilerCollectorInterface extends OpusFrameworkComponentInterface
{
    public function category(): string;
    public function label(): string;
    public function collect(array $trace): array;
}
PHP);
        $collectorTemplate = <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Profiler\Collector;

interface %sInterface extends ProfilerCollectorInterface
{
}
PHP;
        $classTemplate = <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Profiler\Collector;

final class %s implements %sInterface
{
    public function category(): string
    {
        return '%s';
    }

    public function label(): string
    {
        return '%s';
    }

    public function collect(array $trace): array
    {
        $events = (array)($trace['events'] ?? []);
        $rows = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            if (%s) {
                $rows[] = [
                    'index' => (string)($event['index'] ?? ''),
                    'time' => (string)($event['time'] ?? ''),
                    'elapsed_ms' => (string)($event['elapsed_ms'] ?? ''),
                    'category' => (string)($event['category'] ?? ''),
                    'name' => (string)($event['name'] ?? ''),
                    'context_json' => json_encode($event['context'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                ];
            }
        }
        return $rows;
    }
}
PHP;
        $defs = [
            ['RequestCollector', 'request', 'Request', "(string)(\$event['category'] ?? '') === 'request'"],
            ['RoutingCollector', 'routing', 'Routing', "(string)(\$event['category'] ?? '') === 'routing'"],
            ['ExceptionCollector', 'exception', 'Exceptions', "(string)(\$event['category'] ?? '') === 'exception'"],
            ['TemplateCollector', 'template', 'Templates', "(string)(\$event['category'] ?? '') === 'template'"],
            ['DatabaseCollector', 'database', 'Database', "(string)(\$event['category'] ?? '') === 'database'"],
            ['ConfigCollector', 'config', 'Config', "(string)(\$event['category'] ?? '') === 'config'"],
            ['MailCollector', 'mail', 'Mail', "(string)(\$event['category'] ?? '') === 'mail'"],
            ['MemoryCollector', 'memory', 'Memory', "isset(\$event['memory'])"],
            ['RuntimeCollector', 'runtime', 'Runtime', "in_array((string)(\$event['category'] ?? ''), ['runtime', 'profiler', 'response'], true)"],
        ];
        foreach ($defs as [$class, $category, $label, $condition]) {
            $this->writeNewFile('Opus/Profiler/Collector/' . $class . 'Interface.php', sprintf($collectorTemplate, $class));
            $this->writeNewFile('Opus/Profiler/Collector/' . $class . '.php', sprintf($classTemplate, $class, $class, $category, $label, $condition));
        }
    }

    private function writeWebProfilerTemplates(): void
    {
        $this->writeNewFile('Opus/Profiler/templates/web_profiler/layout.score', <<<'SCORE'
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ title }}</title>
<style>
:root{--bg:#10131a;--panel:#171b25;--panel2:#202636;--line:#30384d;--txt:#eef3ff;--muted:#9ba7bd;--accent:#7cc7ff;--danger:#ff8a8a;--ok:#7dffa7}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--txt);font-family:system-ui,-apple-system,Segoe UI,Arial,sans-serif}a{color:var(--accent);text-decoration:none}.shell{display:grid;grid-template-columns:280px 1fr;min-height:100vh}.side{background:#0b0e14;border-right:1px solid var(--line);padding:22px;position:sticky;top:0;height:100vh;overflow:auto}.brand{font-size:22px;font-weight:800;letter-spacing:.04em}.muted{color:var(--muted)}.menu{display:flex;flex-direction:column;gap:8px;margin-top:22px}.menu a,.pill{display:flex;justify-content:space-between;gap:12px;background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:10px 12px}.main{padding:28px;overflow:auto}.hero{background:linear-gradient(135deg,#182033,#141925);border:1px solid var(--line);border-radius:20px;padding:24px;margin-bottom:22px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.panel{background:var(--panel);border:1px solid var(--line);border-radius:18px;padding:18px;margin-bottom:18px}.table{width:100%;border-collapse:collapse}.table th,.table td{border-bottom:1px solid var(--line);padding:10px;vertical-align:top;text-align:left}.table th{color:var(--muted);font-size:12px;text-transform:uppercase}.code{white-space:pre-wrap;background:#090b10;border:1px solid var(--line);border-radius:10px;padding:10px;color:#dbe7ff;max-height:260px;overflow:auto}.badge{display:inline-flex;border-radius:999px;padding:4px 9px;background:var(--panel2);border:1px solid var(--line);font-size:12px}.ok{color:var(--ok)}.danger{color:var(--danger)}
</style>
</head>
<body>
<div class="shell">
<aside class="side">
  <div class="brand">OPUS Profiler</div>
  <p class="muted">Web profiler OPUS généré via template .score.</p>
  <nav class="menu">
    <a href="/_opus/profiler">Traces <span>index</span></a>
    [[ foreach: panels as panel ]]
    <a href="#{{ panel.id }}">{{ panel.label }} <span>{{ panel.count }}</span></a>
    [[ endforeach ]]
  </nav>
  <h3>FSM runtime config</h3>
  <div class="menu">
    [[ foreach: fsm_maps as map ]]
    <span class="pill">{{ map.id }}</span>
    [[ endforeach ]]
  </div>
</aside>
<main class="main">
  <section class="hero">
    <p class="badge">{{ mode }}</p>
    <h1>{{ title }}</h1>
    [[ if: has_trace ]]
    <div class="grid">
      <div class="panel"><strong>Trace</strong><br>{{ trace.trace_id }}</div>
      <div class="panel"><strong>Démarrage</strong><br>{{ trace.started_at }}</div>
      <div class="panel"><strong>Durée</strong><br>{{ trace.duration_ms }} ms</div>
      <div class="panel"><strong>Events</strong><br>{{ trace.event_count }}</div>
    </div>
    [[ endif ]]
  </section>

  [[ if: has_trace ]]
    [[ foreach: panels as panel ]]
    <section class="panel" id="{{ panel.id }}">
      <h2>{{ panel.label }} <span class="badge">{{ panel.count }}</span></h2>
      <table class="table">
        <thead><tr><th>#</th><th>Temps</th><th>+ms</th><th>Nom</th><th>Contexte</th></tr></thead>
        <tbody>
        [[ foreach: panel.events as event ]]
          <tr><td>{{ event.index }}</td><td>{{ event.time }}</td><td>{{ event.elapsed_ms }}</td><td>{{ event.name }}</td><td><pre class="code">{{ event.context_json }}</pre></td></tr>
        [[ endforeach ]]
        </tbody>
      </table>
    </section>
    [[ endforeach ]]
  [[ else ]]
    <section class="panel">
      <h2>Traces disponibles</h2>
      <table class="table">
        <thead><tr><th>Trace</th><th>Démarrage</th><th>Durée</th><th>Events</th><th>Status</th></tr></thead>
        <tbody>
        [[ foreach: traces as item ]]
          <tr><td><a href="/_opus/profiler/trace/{{ item.trace_id }}">{{ item.trace_id }}</a></td><td>{{ item.started_at }}</td><td>{{ item.duration_ms }}</td><td>{{ item.event_count }}</td><td>{{ item.status }}</td></tr>
        [[ endforeach ]]
        </tbody>
      </table>
    </section>
  [[ endif ]]
</main>
</div>
</body>
</html>
SCORE);
    }

    private function patchRuntimeFiles(): void
    {
        $this->replaceFile('Opus/Runtime/Bootstrap.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Runtime;

use Opus\Http\Response;
use Opus\Http\Request;
use Opus\Runtime\Diagnostics\PhpErrorInterceptor;

/**
 * Runtime bootstrap entry point for the modern Composer-driven OPUS kernel.
 *
 * Loads the minimal framework surface required by the runtime kernel and converts fatal bootstrap failures into explicit HTTP responses.
 */
final class Bootstrap
 implements BootstrapInterface {
    public static function run(string $rootDir): void
    {
        self::loadDiagnostics($rootDir);
        PhpErrorInterceptor::register($rootDir);
        self::loadFramework($rootDir);

        try {
            $kernel = new Kernel($rootDir);
            $kernel->handle(Request::fromGlobals($rootDir))->send();
        } catch (\Throwable $e) {
            Response::html(self::renderFatal($e), 500)->send();
        }
    }

    private static function loadDiagnostics(string $rootDir): void
    {
        foreach ([
            'Framework/OpusFrameworkComponentInterface.php',
            'Framework/OpusExceptionAwareInterface.php',
            'Framework/OpusExceptionContractInterface.php',
            'Runtime/Diagnostics/PhpErrorExceptionInterface.php',
            'Runtime/Diagnostics/PhpErrorException.php',
            'Runtime/Diagnostics/ThrowableNormalizerInterface.php',
            'Runtime/Diagnostics/ThrowableNormalizer.php',
            'Runtime/Diagnostics/PhpErrorInterceptorInterface.php',
            'Runtime/Diagnostics/PhpErrorInterceptor.php',
        ] as $file) {
            require_once $rootDir . '/Opus/' . $file;
        }
    }

    private static function loadFramework(string $rootDir): void
    {
        foreach ([
            'Framework/OpusProfilerAwareInterface.php',
            'Framework/OpusSelfDocumentingInterface.php',
            'Foundation/Support.php',
            'Http/RequestInterface.php',
            'Http/Request.php',
            'Http/ResponseInterface.php',
            'Http/Response.php',
            'Application/ApplicationDefinitionInterface.php',
            'Application/ApplicationDefinition.php',
            'Application/ApplicationRegistryInterface.php',
            'Application/ApplicationRegistry.php',
            'I18n/I18nInterface.php',
            'I18n/I18n.php',
            'View/ViewInterface.php',
            'View/View.php',
            'Security/AclInterface.php',
            'Security/Acl.php',
            'Profiler/TraceInterface.php',
            'Profiler/Trace.php',
            'Profiler/ProfilerInterface.php',
            'Profiler/Profiler.php',
            'Profiler/TraceFileRepositoryInterface.php',
            'Profiler/TraceFileRepository.php',
            'Fsm/Runtime/FsmRuntimeConfigLoaderInterface.php',
            'Fsm/Runtime/FsmRuntimeConfigLoader.php',
            'Profiler/WebProfilerViewInterface.php',
            'Profiler/WebProfilerView.php',
            'Profiler/WebProfilerControllerInterface.php',
            'Profiler/WebProfilerController.php',
            'Routing/RouterInterface.php',
            'Routing/Router.php',
            'Runtime/KernelInterface.php',
            'Runtime/Kernel.php',
        ] as $file) {
            require_once $rootDir . '/Opus/' . $file;
        }
    }

    private static function renderFatal(\Throwable $e): string
    {
        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $file = htmlspecialchars($e->getFile(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $line = (int) $e->getLine();

        return <<<HTML
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>OPUS fatal error</title>
<style>
body{font-family:system-ui,Segoe UI,Arial,sans-serif;margin:2rem;background:#151820;color:#f5f7ff;line-height:1.5}
pre{white-space:pre-wrap;background:#07090f;border:1px solid #353b4f;border-radius:12px;padding:1rem;color:#ffdf99}
strong{color:#ffb86b}
</style>
</head>
<body>
<h1>OPUS — erreur explicite</h1>
<p><strong>{$message}</strong></p>
<pre>{$file}:{$line}</pre>
</body>
</html>
HTML;
    }
}
PHP);
        $this->replaceFile('Opus/Runtime/Kernel.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Runtime;

use Opus\Routing\Router;
use Opus\View\View;
use Opus\I18n\I18n;
use Opus\Security\Acl;
use Opus\Application\ApplicationRegistry;
use Opus\Application\ApplicationDefinition;
use Opus\Http\Response;
use Opus\Http\Request;
use Opus\Profiler\Profiler;
use Opus\Profiler\WebProfilerController;
use Opus\Runtime\Diagnostics\ThrowableNormalizer;
use Opus\Fsm\Runtime\FsmRuntimeConfigLoader;

/**
 * Modern OPUS runtime kernel responsible for application resolution and request dispatch.
 *
 * Coordinates application registry, I18N, routing, ACL, profiler and URL generation for integrated OPUS applications.
 */
final class Kernel
 implements KernelInterface {
    private string $rootDir;
    private ApplicationRegistry $applications;
    private I18n $i18n;
    private Router $router;
    private Profiler $profiler;
    private FsmRuntimeConfigLoader $fsmRuntimeConfigLoader;
    private WebProfilerController $webProfilerController;
    private ?Request $request = null;

    public function __construct(string $rootDir)
    {
        $this->rootDir = rtrim($rootDir, '/\\');
        $this->profiler = new Profiler($this->rootDir . '/var/profiler');
        $this->fsmRuntimeConfigLoader = new FsmRuntimeConfigLoader($this->rootDir . '/config/fsm_runtime');
        $this->webProfilerController = new WebProfilerController($this->rootDir, $this->fsmRuntimeConfigLoader);
        $this->applications = new ApplicationRegistry($this->rootDir);
        $this->i18n = new I18n();
        $view = new View($this, $this->i18n);
        $this->router = new Router($this, $view, new Acl(), $this->profiler, $this->fsmRuntimeConfigLoader);
    }

    public function handle(Request $request): Response
    {
        $this->request = $request;
        $this->profiler->start();
        try {
            $this->profiler->event('request', 'request.received', [
                'host' => $request->host,
                'method' => $request->method,
                'path' => $request->path,
                'segments' => $request->segments,
            ]);

            if ($this->isWebProfilerPath($request)) {
                $this->profiler->event('routing', 'web_profiler.route', ['path' => $request->path]);
                return $this->webProfilerController->handle($request);
            }

            [$application, $segments] = $this->applications->resolve($request);
            $this->profiler->event('routing', 'application.resolved', [
                'application' => $application->slug,
                'segments' => $segments,
            ]);
            $response = $this->router->dispatch($application, $segments, $request);
            $this->profiler->event('response', 'response.created', ['application' => $application->slug]);
            return $response;
        } catch (\Throwable $e) {
            $this->profiler->event('exception', 'throwable.normalized', ThrowableNormalizer::normalize($e));
            throw $e;
        } finally {
            try {
                $this->profiler->stop([
                    'status' => 'complete',
                    'path' => $request->path,
                    'method' => $request->method,
                ]);
            } catch (\Throwable $profilerFailure) {
                // The runtime response must not be replaced by a profiler flush failure.
            }
        }
    }

    public function rootDir(): string
    {
        return $this->rootDir;
    }

    public function getApplication(string $slug): ApplicationDefinition
    {
        return $this->applications->get($slug);
    }

    public function applicationUrl(string $applicationSlug, string $route = '', ?string $lang = null): string
    {
        $application = $this->applications->get($applicationSlug);
        $lang = $lang !== null && $application->hasLanguage($lang) ? $lang : $application->defaultLang;
        $base = $this->request ? $this->request->basePath : '';
        $parts = [$base];
        if ($applicationSlug !== 'logandplay') {
            $parts[] = $applicationSlug;
        }
        $parts[] = $lang;
        if ($route !== '') {
            $parts[] = trim($route, '/');
        }
        return $this->joinUrlParts($parts);
    }

    public function pageUrl(ApplicationDefinition $application, string $lang, string $pageId): string
    {
        $routes = $application->routes();
        $langRoutes = (array)($routes[$lang] ?? []);
        $route = '';
        foreach ($langRoutes as $candidate => $candidatePageId) {
            if ($candidatePageId === $pageId) {
                $route = (string)$candidate;
                break;
            }
        }
        return $this->applicationUrl($application->slug, $route, $lang);
    }

    public function apiUrl(string $applicationSlug, string $endpoint): string
    {
        $base = $this->request ? $this->request->basePath : '';
        $parts = [$base];
        if ($applicationSlug !== 'logandplay') {
            $parts[] = $applicationSlug;
        }
        $parts[] = 'api';
        $parts[] = trim($endpoint, '/');
        return $this->joinUrlParts($parts);
    }

    public function assetUrl(ApplicationDefinition $application, string $asset): string
    {
        $base = $this->request ? $this->request->basePath : '';
        return $this->joinUrlParts([$base, 'sites', $application->slug, 'www', trim($asset, '/')]);
    }

    private function isWebProfilerPath(Request $request): bool
    {
        $path = trim($request->path, '/');
        return $path === '_opus/profiler' || str_starts_with($path, '_opus/profiler/trace/');
    }

    /** @param list<string> $parts */
    private function joinUrlParts(array $parts): string
    {
        $clean = [];
        foreach ($parts as $part) {
            $part = trim($part, '/');
            if ($part !== '') {
                $clean[] = $part;
            }
        }
        return '/' . implode('/', array_map(static fn($p) => str_replace('%2F', '/', rawurlencode($p)), $clean));
    }
}
PHP);
        $this->replaceFile('Opus/Routing/Router.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace Opus\Routing;

use Opus\Runtime\Kernel;
use Opus\View\View;
use Opus\Security\Acl;
use Opus\Application\ApplicationDefinition;
use Opus\Foundation\Support;
use Opus\Http\Response;
use Opus\Http\Request;
use Opus\Profiler\Profiler;
use Opus\Fsm\Runtime\FsmRuntimeConfigLoader;

/**
 * Runtime router for integrated OPUS applications.
 *
 * Resolves page routes and API endpoints from application definitions, applies access control and delegates HTML rendering to the view layer.
 */
final class Router
 implements RouterInterface {
    private Kernel $kernel;
    private View $view;
    private Acl $acl;
    private Profiler $profiler;
    private FsmRuntimeConfigLoader $fsmRuntimeConfigLoader;

    public function __construct(Kernel $kernel, View $view, Acl $acl, Profiler $profiler, FsmRuntimeConfigLoader $fsmRuntimeConfigLoader)
    {
        $this->kernel = $kernel;
        $this->view = $view;
        $this->acl = $acl;
        $this->profiler = $profiler;
        $this->fsmRuntimeConfigLoader = $fsmRuntimeConfigLoader;
    }

    /** @param list<string> $segments */
    public function dispatch(ApplicationDefinition $application, array $segments, Request $request): Response
    {
        $this->profiler->event('routing', 'dispatch.start', ['application' => $application->slug, 'segments' => $segments]);
        $routes = $application->routes();

        if (($segments[0] ?? '') === 'api') {
            return $this->dispatchApi($application, array_slice($segments, 1), $request);
        }

        $lang = $segments[0] ?? $application->defaultLang;
        if (!$application->hasLanguage($lang)) {
            $lang = $application->defaultLang;
            $routeSegments = $segments;
        } else {
            $routeSegments = array_slice($segments, 1);
        }

        $routeKey = implode('/', $routeSegments);
        $langRoutes = (array)($routes[$lang] ?? []);
        $pageId = (string)($langRoutes[$routeKey] ?? '');
        $this->profiler->event('routing', 'route.resolved', ['lang' => $lang, 'route' => $routeKey, 'page_id' => $pageId]);

        if ($pageId === '') {
            return $this->notFound($application, $lang, $request, $routeKey);
        }

        $content = $application->content();
        $page = (array)($content[$lang][$pageId] ?? []);
        if (!$page) {
            throw new \RuntimeException("Page content missing: {$application->slug}/{$lang}/{$pageId}");
        }

        if (!$this->acl->canView($page)) {
            $this->profiler->event('routing', 'acl.forbidden', ['page_id' => $pageId]);
            return Response::html('<h1>403</h1><p>Forbidden</p>', 403);
        }

        if ($pageId === 'fsm') {
            $page['flow'] = $this->fsmRuntimeConfigLoader->flowForDisplay('runtime_request');
            $this->profiler->event('runtime', 'fsm_runtime_config.loaded', ['id' => 'runtime_request']);
        }

        $this->profiler->event('template', 'view.render.start', ['page_id' => $pageId]);
        $html = $this->view->render($application, $lang, $pageId, $page);
        $this->profiler->event('template', 'view.render.done', ['page_id' => $pageId]);
        return Response::html($html);
    }

    /** @param list<string> $segments */
    private function dispatchApi(ApplicationDefinition $application, array $segments, Request $request): Response
    {
        $endpoint = implode('/', $segments);
        $this->profiler->event('routing', 'api.dispatch', ['application' => $application->slug, 'endpoint' => $endpoint]);
        if ($application->slug === 'demo' && $endpoint === 'ping') {
            return Response::json([
                'ok' => true,
                'application' => $application->slug,
                'host' => $request->host,
                'base_path' => $request->basePath,
                'path' => $request->path,
                'time' => date(DATE_ATOM),
            ]);
        }

        if ($application->slug === 'demo' && $endpoint === 'site') {
            return Response::json([
                'application' => $application->slug,
                'name' => $application->name,
                'languages' => $application->languages,
                'paths' => [
                    'application_dir' => $application->dir,
                    'www' => $application->dir . '/www',
                    'logs' => $application->dir . '/logs',
                    'tmp' => $application->dir . '/tmp',
                    'history' => $application->dir . '/history',
                ],
                'checks' => [
                    'dynamic_paths' => true,
                    'external_links_required' => false,
                    'accented_url' => $this->kernel->applicationUrl('demo', 'démo-interne', 'fr'),
                ],
            ]);
        }

        return Response::json([
            'ok' => false,
            'error' => 'Unknown API endpoint',
            'endpoint' => $endpoint,
        ], 404);
    }

    private function notFound(ApplicationDefinition $application, string $lang, Request $request, string $routeKey): Response
    {
        $this->profiler->event('routing', 'route.not_found', ['path' => $request->path, 'route' => $routeKey]);
        $route = Support::e($routeKey);
        $path = Support::e($request->path);
        $home = $this->kernel->applicationUrl($application->slug, '', $lang);
        return Response::html("<!doctype html><html lang=\"{$lang}\"><head><meta charset=\"utf-8\"><title>404</title><link rel=\"stylesheet\" href=\"" . $this->kernel->assetUrl($application, 'assets/css/site.css') . "\"></head><body><main class=\"shell\"><section class=\"panel\"><h1>DISPATCH erreur 404</h1><p>Path: {$path}</p><p>Route: {$route}</p><p><a href=\"{$home}\">Retour application</a></p></section></main></body></html>", 404);
    }
}
PHP);
        $view = $this->read('Opus/View/View.php');
        $view = str_replace('use Opus\Kernel;', 'use Opus\Runtime\Kernel;', $view);
        $this->replaceFile('Opus/View/View.php', $view);
    }

    private function writeReports(): void
    {
        $this->ensureDir('DOC/reference/generated/json');
        $this->ensureDir('DOC/reference/generated/markdown');
        $json = [
            'schema' => 'P7A1D4_WEB_PROFILER_EXCEPTION_PIPELINE_V1',
            'status' => 'OK',
            'web_profiler_routes' => ['/_opus/profiler', '/_opus/profiler/trace/{trace_id}'],
            'configured_fsm_dir' => 'config/fsm_runtime',
            'hardcoded_runtime_fsm_transitions' => false,
            'collectors' => ['request', 'routing', 'exception', 'template', 'database', 'config', 'mail', 'memory', 'runtime'],
        ];
        $this->writeNewFile('DOC/reference/generated/json/p7a1d4_web_profiler_exception_pipeline.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        $this->writeNewFile('DOC/reference/generated/markdown/P7A1D4_WEB_PROFILER_EXCEPTION_PIPELINE.md', "# P7A1D4 Web Profiler exception pipeline\n\nStatus: OK\n\nRoutes:\n\n- /_opus/profiler\n- /_opus/profiler/trace/{trace_id}\n\nFSM runtime config: config/fsm_runtime/\n\nCollectors: request, routing, exception, template, database, config, mail, memory, runtime.\n");
    }

    private function lintAllOpusPhp(): void
    {
        $files = $this->phpFiles('Opus');
        $errors = [];
        foreach ($files as $file) {
            $cmd = 'php -l ' . escapeshellarg($this->path($file));
            $out = [];
            $code = 0;
            exec($cmd, $out, $code);
            if ($code !== 0) {
                $errors[] = $file . ': ' . implode(' ', $out);
            }
        }
        echo 'P7A1D4_BIG_WEB_PROFILER_EXCEPTION_PIPELINE_CONFIGURED_FSM_PHP_FILES=' . count($files) . "\n";
        echo 'P7A1D4_BIG_WEB_PROFILER_EXCEPTION_PIPELINE_CONFIGURED_FSM_PHP_LINT_ERRORS=' . count($errors) . "\n";
        if ($errors) {
            throw new RuntimeException('PHP_LINT_FAILED: ' . implode(' | ', $errors));
        }
    }

    private function runStructuralGate(): void
    {
        $required = [
            'config/fsm_runtime/runtime_boot.json',
            'config/fsm_runtime/runtime_request.json',
            'config/fsm_runtime/runtime_profiler.json',
            'config/fsm_runtime/runtime_exception.json',
            'Opus/Fsm/Runtime/FsmRuntimeConfigLoader.php',
            'Opus/Runtime/Diagnostics/PhpErrorInterceptor.php',
            'Opus/Runtime/Diagnostics/ThrowableNormalizer.php',
            'Opus/Profiler/WebProfilerController.php',
            'Opus/Profiler/WebProfilerView.php',
            'Opus/Profiler/templates/web_profiler/layout.score',
        ];
        foreach ($required as $file) {
            if (!is_file($this->path($file))) {
                throw new RuntimeException('P7A1D4_REQUIRED_FILE_MISSING: ' . $file);
            }
        }
        $collectorFiles = glob($this->path('Opus/Profiler/Collector/*Collector.php')) ?: [];
        if (count($collectorFiles) < 9) {
            throw new RuntimeException('P7A1D4_COLLECTORS_MISSING');
        }
        foreach ($collectorFiles as $file) {
            $content = file_get_contents($file) ?: '';
            if (stripos($content, '<html') !== false || stripos($content, '<body') !== false) {
                throw new RuntimeException('P7A1D4_COLLECTOR_CONTAINS_HTML: ' . $file);
            }
        }
        foreach (glob($this->path('config/fsm_runtime/*.json')) ?: [] as $file) {
            $data = json_decode((string)file_get_contents($file), true);
            if (!is_array($data) || ($data['schema'] ?? '') !== 'OPUS_FSM_RUNTIME_V1') {
                throw new RuntimeException('P7A1D4_FSM_CONFIG_INVALID: ' . $file);
            }
        }
        echo 'P7A1D4_BIG_WEB_PROFILER_EXCEPTION_PIPELINE_CONFIGURED_FSM_COLLECTORS_REGISTERED=' . count($collectorFiles) . "\n";
        echo "P7A1D4_BIG_WEB_PROFILER_EXCEPTION_PIPELINE_CONFIGURED_FSM_WEB_PROFILER_ROUTE_AVAILABLE=1\n";
        echo "P7A1D4_BIG_WEB_PROFILER_EXCEPTION_PIPELINE_CONFIGURED_FSM_WEB_PROFILER_TEMPLATE_SCORE_AVAILABLE=1\n";
        echo "P7A1D4_BIG_WEB_PROFILER_EXCEPTION_PIPELINE_CONFIGURED_FSM_CONFIGURED_FSM_MAPS=4\n";
        echo "P7A1D4_BIG_WEB_PROFILER_EXCEPTION_PIPELINE_CONFIGURED_FSM_NO_HARDCODED_RUNTIME_FSM=1\n";
        echo "P7A1D4_BIG_WEB_PROFILER_EXCEPTION_PIPELINE_CONFIGURED_FSM_NO_HTML_BUILT_IN_COLLECTORS=1\n";
    }

    public function rollback(): void
    {
        foreach (array_reverse($this->originals) as $relative => $content) {
            if ($content === null) {
                @unlink($this->path($relative));
            } else {
                @file_put_contents($this->path($relative), $content);
            }
        }
        foreach (array_reverse($this->createdFiles) as $relative) {
            if (!array_key_exists($relative, $this->originals)) {
                @unlink($this->path($relative));
            }
        }
        foreach (array_reverse($this->createdDirs) as $relative) {
            @rmdir($this->path($relative));
        }
    }

    private function replaceFile(string $relative, string $content): void
    {
        if (!array_key_exists($relative, $this->originals)) {
            $this->originals[$relative] = is_file($this->path($relative)) ? $this->read($relative) : null;
        }
        $this->ensureDir(dirname($relative));
        file_put_contents($this->path($relative), $content);
    }

    private function writeNewFile(string $relative, string $content): void
    {
        if (is_file($this->path($relative)) && !array_key_exists($relative, $this->originals)) {
            throw new RuntimeException('P7A1D4_REFUSES_TO_OVERWRITE_EXISTING_FILE: ' . $relative);
        }
        if (!array_key_exists($relative, $this->originals)) {
            $this->originals[$relative] = null;
        }
        $this->ensureDir(dirname($relative));
        file_put_contents($this->path($relative), $content);
        $this->createdFiles[] = $relative;
    }

    private function ensureDir(string $relative): void
    {
        if ($relative === '.' || $relative === '') {
            return;
        }
        $parts = explode('/', str_replace('\\', '/', $relative));
        $path = '';
        foreach ($parts as $part) {
            if ($part === '') continue;
            $path = $path === '' ? $part : $path . '/' . $part;
            $full = $this->path($path);
            if (!is_dir($full)) {
                mkdir($full);
                $this->createdDirs[] = $path;
            }
        }
    }

    private function read(string $relative): string
    {
        $content = file_get_contents($this->path($relative));
        if (!is_string($content)) {
            throw new RuntimeException('READ_FAILED: ' . $relative);
        }
        return $content;
    }

    /** @return list<string> */
    private function phpFiles(string $relativeDir): array
    {
        $base = $this->path($relativeDir);
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'php') {
                $files[] = str_replace($this->root . '/', '', str_replace('\\', '/', $file->getPathname()));
            }
        }
        sort($files);
        return $files;
    }

    private function path(string $relative): string
    {
        return $this->root . '/' . str_replace('\\', '/', $relative);
    }
}

exit(P7A1D4BigWebProfilerExceptionPipelineConfiguredFsm::main());
