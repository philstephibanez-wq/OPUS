<?php
declare(strict_types=1);

namespace Opus\Owasys;

use RuntimeException;

/**
 * Installs the mandatory development profiler in every OWASYS-generated OPUS application.
 * OWASYS itself never boots this profiler and production can never activate it.
 */
final class GeneratedProfilerWriter
{
    public const CONTRACT = 'OPUS_GENERATED_PROFILER_V1';

    public function __construct(private readonly string $opusRoot)
    {
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    public function write(array $plan, bool $dryRun = true): array
    {
        if (($plan['profiler']['enabled'] ?? null) !== true || ($plan['profiler']['mandatory'] ?? null) !== true) {
            throw new RuntimeException('OWASYS_GENERATED_PROFILER_PLAN_MANDATORY');
        }

        $siteRoot = trim(str_replace('\\', '/', (string) ($plan['site_root'] ?? '')), '/');
        if ($siteRoot === '' || str_contains($siteRoot, '..')) {
            throw new RuntimeException('OWASYS_GENERATED_PROFILER_SITE_ROOT_INVALID');
        }

        $files = [
            $siteRoot . '/config/profiler.json',
            $siteRoot . '/application/default/helpers/GeneratedProfiler.php',
            $siteRoot . '/www/asset/css/profiler.css',
            $siteRoot . '/www/asset/js/profiler.js',
        ];

        if ($dryRun) {
            return [
                'enabled' => true,
                'mandatory' => true,
                'mode' => 'dry-run',
                'contract' => self::CONTRACT,
                'environment' => 'dev-only',
                'production_available' => false,
                'files' => $files,
            ];
        }

        $root = $this->absolute($siteRoot);
        if (!is_dir($root)) {
            throw new RuntimeException('OWASYS_GENERATED_PROFILER_SITE_MISSING');
        }

        $contents = [
            $files[0] => json_encode([
                'contract' => self::CONTRACT,
                'enabled' => true,
                'mandatory' => true,
                'environment' => 'dev-only',
                'allowed_environments' => ['dev', 'local', 'development'],
                'production_available' => false,
                'query_enable' => 'profiler=1',
                'query_disable' => 'profiler=0',
                'collectors' => ['request', 'response', 'route', 'fsm', 'acl', 'controller', 'model', 'database', 'viewmodel', 'template', 'layout', 'logs', 'exceptions', 'time', 'memory'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
            $files[1] => $this->runtimeSource(),
            $files[2] => $this->cssSource(),
            $files[3] => $this->jsSource(),
        ];

        foreach ($contents as $relative => $content) {
            $absolute = $this->absolute($relative);
            $parent = dirname($absolute);
            if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
                throw new RuntimeException('OWASYS_GENERATED_PROFILER_DIRECTORY_FAILED');
            }
            if (file_put_contents($absolute, $content) === false) {
                throw new RuntimeException('OWASYS_GENERATED_PROFILER_WRITE_FAILED:' . $relative);
            }
        }

        $front = $this->absolute($siteRoot . '/www/index.php');
        $source = is_file($front) ? (string) file_get_contents($front) : '';
        if ($source === '') {
            throw new RuntimeException('OWASYS_GENERATED_PROFILER_FRONT_MISSING');
        }
        if (!str_contains($source, 'OPUS_GENERATED_PROFILER_BOOTSTRAP')) {
            $bootstrap = "\n// OPUS_GENERATED_PROFILER_BOOTSTRAP\nrequire_once \$siteRoot . '/application/default/helpers/GeneratedProfiler.php';\n\$opusProfiler = \\OpusGenerated\\GeneratedProfiler::boot(\$siteRoot);\n";
            $marker = '$fsmFile = $siteRoot . \'/config/application.fsm.json\';';
            if (!str_contains($source, $marker)) {
                throw new RuntimeException('OWASYS_GENERATED_PROFILER_FRONT_MARKER_MISSING');
            }
            $source = str_replace($marker, $marker . $bootstrap, $source);
            $source .= "\nif (isset(\$opusProfiler) && \$opusProfiler instanceof \\OpusGenerated\\GeneratedProfiler) {\n    echo \$opusProfiler->render([\n        'path' => \$path ?? null,\n        'route' => \$route ?? null,\n        'state' => \$currentState ?? null,\n        'page' => \$page ?? null,\n    ]);\n}\n";
            if (file_put_contents($front, $source) === false) {
                throw new RuntimeException('OWASYS_GENERATED_PROFILER_FRONT_PATCH_FAILED');
            }
        }

        return [
            'enabled' => true,
            'mandatory' => true,
            'mode' => 'write',
            'contract' => self::CONTRACT,
            'environment' => 'dev-only',
            'production_available' => false,
            'files' => $files,
        ];
    }

    private function absolute(string $relative): string
    {
        return rtrim($this->opusRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function runtimeSource(): string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);

namespace OpusGenerated;

final class GeneratedProfiler
{
    private const ALLOWED_ENVIRONMENTS = ['dev', 'local', 'development'];

    private float $startedAt;
    private int $startedMemory;

    private function __construct(private readonly string $siteRoot, private readonly bool $visible)
    {
        $this->startedAt = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        $this->startedMemory = memory_get_usage(true);
    }

    public static function boot(string $siteRoot): ?self
    {
        $environment = strtolower((string) (getenv('OPUS_ENV') ?: ($_SERVER['OPUS_ENV'] ?? 'prod')));
        if (!in_array($environment, self::ALLOWED_ENVIRONMENTS, true)) {
            return null;
        }

        $flag = $_GET['profiler'] ?? null;
        return new self($siteRoot, $flag === '1');
    }

    /** @param array<string,mixed> $context */
    public function render(array $context): string
    {
        if (!$this->visible) {
            return '';
        }

        $elapsed = (microtime(true) - $this->startedAt) * 1000;
        $memory = max(0, memory_get_usage(true) - $this->startedMemory);
        $route = is_array($context['route'] ?? null) ? $context['route'] : [];
        $state = (string) ($context['state'] ?? ($route['state'] ?? 'unknown'));
        $path = (string) ($context['path'] ?? ($_SERVER['REQUEST_URI'] ?? '/'));
        $status = http_response_code();
        $details = htmlspecialchars(json_encode([
            'request' => ['method' => $_SERVER['REQUEST_METHOD'] ?? 'GET', 'path' => $path],
            'response' => ['status' => $status],
            'route' => $route,
            'fsm' => ['state' => $state],
            'time_ms' => round($elapsed, 2),
            'memory_bytes' => $memory,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeState = htmlspecialchars($state, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<link rel="stylesheet" href="/asset/css/profiler.css">'
            . '<aside class="opus-profiler" data-contract="OPUS_GENERATED_PROFILER_V1">'
            . '<button type="button" class="opus-profiler-toggle" aria-expanded="false">OPUS · ' . $safeState . ' · ' . number_format($elapsed, 1) . ' ms · ' . $status . '</button>'
            . '<pre class="opus-profiler-panel" hidden>' . $details . '</pre></aside>'
            . '<script src="/asset/js/profiler.js"></script>';
    }
}
PHP;
    }

    private function cssSource(): string
    {
        return ".opus-profiler{position:fixed;left:0;right:0;bottom:0;z-index:2147483647;font:12px/1.4 ui-monospace,monospace;background:#111827;color:#e5e7eb;border-top:1px solid #374151}.opus-profiler-toggle{width:100%;padding:7px 12px;text-align:left;background:#111827;color:#e5e7eb;border:0;cursor:pointer}.opus-profiler-panel{max-height:45vh;overflow:auto;margin:0;padding:12px;background:#030712;color:#d1fae5;white-space:pre-wrap}\n";
    }

    private function jsSource(): string
    {
        return "document.querySelectorAll('.opus-profiler-toggle').forEach((button)=>button.addEventListener('click',()=>{const panel=button.nextElementSibling;if(!(panel instanceof HTMLElement))return;panel.hidden=!panel.hidden;button.setAttribute('aria-expanded',String(!panel.hidden));}));\n";
    }
}
