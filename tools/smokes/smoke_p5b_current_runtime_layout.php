<?php
/**
 * P5B_CURRENT_RUNTIME_LAYOUT_SMOKE
 *
 * Visibility: internal CLI smoke.
 * Role: validate the current OPUS runtime layout after the P4 root cleanup.
 * Inputs: repository root resolved from this script location.
 * Output: explicit CHECK_* lines and final P5B_CURRENT_RUNTIME_LAYOUT_SMOKE_OK.
 * Side effects: none.
 * Errors: exits non-zero on any missing/obsolete runtime contract.
 *
 * Contract:
 * - OPUS root entrypoint index.php is modern and does not boot legacy Application.
 * - Legacy www/index.php explicitly loads Composer, legacy autoloader and legacy Application.
 * - Legacy autoloader requires Composer-loaded Opus\\Bootstrap instead of requiring Opus/Bootstrap.php directly.
 * - Opus/ root contains Bootstrap.php as the only remaining root PHP runtime file.
 * - Former root legacy classes stay absent from Opus/ root.
 * - P4 runners stay archived outside repository root.
 */
declare(strict_types=1);

final class P5BCurrentRuntimeLayoutSmoke
{
    private string $root;
    private int $failures = 0;

    public function __construct(string $root)
    {
        $realRoot = realpath($root);
        if ($realRoot === false) {
            $this->fail('CHECK_REPO_ROOT', 'Unable to resolve repository root.');
            $this->root = $root;
            return;
        }
        $this->root = rtrim(str_replace('\\', '/', $realRoot), '/');
    }

    public function run(): int
    {
        $this->ok('CHECK_REPO_ROOT', $this->root);
        $this->checkNoRootP4Runners();
        $this->checkRootOpusPhpBoundary();
        $this->checkFormerRootLegacyClassesAbsent();
        $this->checkModernEntrypoint();
        $this->checkLegacyEntrypoint();
        $this->checkLegacyAutoloaderComposerGuard();
        $this->checkBootstrapContract();
        $this->checkLegacyApplicationContract();
        $this->lintRuntimeFiles();

        if ($this->failures > 0) {
            echo 'P5B_CURRENT_RUNTIME_LAYOUT_SMOKE_FAILED failures=' . $this->failures . PHP_EOL;
            return 1;
        }

        echo 'P5B_CURRENT_RUNTIME_LAYOUT_SMOKE_OK' . PHP_EOL;
        return 0;
    }

    private function checkNoRootP4Runners(): void
    {
        $matches = glob($this->root . '/RUN_P4*.cmd') ?: [];
        if ($matches !== []) {
            $this->fail('CHECK_NO_ROOT_P4_RUNNERS', implode(', ', array_map('basename', $matches)));
            return;
        }
        $this->ok('CHECK_NO_ROOT_P4_RUNNERS');
    }

    private function checkRootOpusPhpBoundary(): void
    {
        $matches = array_merge(
            glob($this->root . '/Opus/*.php') ?: [],
            glob($this->root . '/Opus/*.class.php') ?: []
        );
        sort($matches);
        $relative = array_map(fn(string $path): string => $this->relative($path), $matches);
        if ($relative !== ['Opus/Bootstrap.php']) {
            $this->fail('CHECK_OPUS_ROOT_PHP_BOUNDARY', implode(', ', $relative));
            return;
        }
        $this->ok('CHECK_OPUS_ROOT_PHP_BOUNDARY', 'Opus/Bootstrap.php');
    }

    private function checkFormerRootLegacyClassesAbsent(): void
    {
        $former = [
            'Opus/Application.class.php',
            'Opus/autoloader.class.php',
            'Opus/autoloader_new2.class.php',
            'Opus/Validator.class.php',
            'Opus/Kernel.php',
            'Opus/Router.php',
            'Opus/View.php',
        ];

        foreach ($former as $path) {
            $fullPath = $this->root . '/' . $path;
            if (is_file($fullPath)) {
                $this->fail('CHECK_FORMER_ROOT_LEGACY_CLASS_ABSENT', $path);
            }
        }
        if ($this->failures === 0) {
            $this->ok('CHECK_FORMER_ROOT_LEGACY_CLASSES_ABSENT');
        }
    }

    private function checkModernEntrypoint(): void
    {
        $file = $this->root . '/index.php';
        $content = $this->read($file);
        if ($content === null) { return; }

        $this->contains($content, '\\Opus\\Autoload\\Autoloader::boot', 'CHECK_INDEX_MODERN_AUTOLOADER');
        $this->contains($content, '\\Opus\\Runtime\\NativeHttpKernel', 'CHECK_INDEX_NATIVE_KERNEL');
        $this->notContains($content, 'Opus/Bootstrap.php', 'CHECK_INDEX_DOES_NOT_REQUIRE_LEGACY_BOOTSTRAP');
        $this->notContains($content, 'OPUS_Application', 'CHECK_INDEX_DOES_NOT_BOOT_LEGACY_APPLICATION');
    }

    private function checkLegacyEntrypoint(): void
    {
        $file = $this->root . '/www/index.php';
        $content = $this->read($file);
        if ($content === null) { return; }

        $this->contains($content, "define('ROOT', realpath(__DIR__ . '/..'));", 'CHECK_WWW_DEFINES_ROOT');
        $this->contains($content, "\$composerAutoload = ROOT . '/vendor/autoload.php';", 'CHECK_WWW_DECLARES_COMPOSER_AUTOLOAD');
        $this->contains($content, "throw new RuntimeException('OPUS_COMPOSER_AUTOLOAD_REQUIRED: ' . \$composerAutoload);", 'CHECK_WWW_COMPOSER_AUTOLOAD_REQUIRED_ERROR');
        $this->contains($content, 'require_once $composerAutoload;', 'CHECK_WWW_REQUIRES_COMPOSER_AUTOLOAD');
        $this->notContains($content, "require_once ROOT . '/Opus/Bootstrap.php';", 'CHECK_WWW_DOES_NOT_REQUIRE_BOOTSTRAP_DIRECTLY');
        $this->contains($content, "require_once ROOT . '/Opus/Legacy/Autoload/autoloader.class.php';", 'CHECK_WWW_REQUIRES_LEGACY_AUTOLOADER');
        $this->contains($content, "require_once ROOT . '/Opus/Legacy/Application/Application.class.php';", 'CHECK_WWW_REQUIRES_LEGACY_APPLICATION');
        $this->contains($content, 'OPUS_Application::getInstance()', 'CHECK_WWW_BOOTSTRAPS_LEGACY_APPLICATION');
        $this->notContains($content, 'Opus/Application.class.php', 'CHECK_WWW_DOES_NOT_REQUIRE_ROOT_APPLICATION');
    }

    private function checkLegacyAutoloaderComposerGuard(): void
    {
        $file = $this->root . '/Opus/Legacy/Autoload/autoloader.class.php';
        $content = $this->read($file);
        if ($content === null) { return; }

        $this->contains($content, 'class_exists(\Opus\Bootstrap::class)', 'CHECK_LEGACY_AUTOLOADER_COMPOSER_BOOTSTRAP_GUARD');
        $this->contains($content, 'OPUS_BOOTSTRAP_CLASS_REQUIRED', 'CHECK_LEGACY_AUTOLOADER_BOOTSTRAP_REQUIRED_ERROR');
        $this->notContains($content, 'require_once $opusBootstrap;', 'CHECK_LEGACY_AUTOLOADER_NO_BOOTSTRAP_REQUIRE');
        $this->notContains($content, "ROOT . '/Opus/Bootstrap.php'", 'CHECK_LEGACY_AUTOLOADER_NO_ROOT_BOOTSTRAP_PATH');
    }

    private function checkBootstrapContract(): void
    {
        $file = $this->root . '/Opus/Bootstrap.php';
        $content = $this->read($file);
        if ($content === null) { return; }

        $this->contains($content, 'namespace Opus;', 'CHECK_BOOTSTRAP_NAMESPACE');
        $this->contains($content, 'final class Bootstrap', 'CHECK_BOOTSTRAP_CLASS');
        $this->contains($content, "'Runtime/Kernel.php'", 'CHECK_BOOTSTRAP_LOADS_RUNTIME_KERNEL');
        $this->contains($content, "'Routing/Router.php'", 'CHECK_BOOTSTRAP_LOADS_ROUTER');
        $this->contains($content, "'View/View.php'", 'CHECK_BOOTSTRAP_LOADS_VIEW');
    }

    private function checkLegacyApplicationContract(): void
    {
        $file = $this->root . '/Opus/Legacy/Application/Application.class.php';
        $content = $this->read($file);
        if ($content === null) { return; }

        $this->contains($content, 'class OPUS_Application', 'CHECK_LEGACY_APPLICATION_CLASS');
        $this->contains($content, "dirname(__DIR__, 3) . '/config/fsm.boot.php'", 'CHECK_LEGACY_APPLICATION_BOOT_FSM_ROOT');
        $this->notContains($content, "dirname(__DIR__) . '/config/fsm.boot.php'", 'CHECK_LEGACY_APPLICATION_NO_OLD_BOOT_FSM_ROOT');
    }

    private function lintRuntimeFiles(): void
    {
        foreach ([
            'index.php',
            'www/index.php',
            'Opus/Bootstrap.php',
            'Opus/Legacy/Autoload/autoloader.class.php',
            'Opus/Legacy/Application/Application.class.php',
            'Opus/Runtime/Kernel.php',
            'Opus/Routing/Router.php',
            'Opus/View/View.php',
        ] as $path) {
            $this->phpLint($path);
        }
    }

    private function phpLint(string $relativePath): void
    {
        $fullPath = $this->root . '/' . $relativePath;
        if (!is_file($fullPath)) {
            $this->fail('CHECK_PHP_LINT_FILE_EXISTS', $relativePath);
            return;
        }

        $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($fullPath) . ' 2>&1';
        exec($command, $output, $code);
        if ($code !== 0) {
            $this->fail('CHECK_PHP_LINT_' . strtoupper(str_replace(['/', '.', '-'], '_', $relativePath)), implode(' | ', $output));
            return;
        }
        $this->ok('CHECK_PHP_LINT_' . strtoupper(str_replace(['/', '.', '-'], '_', $relativePath)));
    }

    private function contains(string $content, string $needle, string $check): void
    {
        if (!str_contains($content, $needle)) {
            $this->fail($check, 'Missing: ' . $needle);
            return;
        }
        $this->ok($check);
    }

    private function notContains(string $content, string $needle, string $check): void
    {
        if (str_contains($content, $needle)) {
            $this->fail($check, 'Forbidden: ' . $needle);
            return;
        }
        $this->ok($check);
    }

    private function read(string $path): ?string
    {
        if (!is_file($path)) {
            $this->fail('CHECK_FILE_EXISTS', $this->relative($path));
            return null;
        }

        $content = file_get_contents($path);
        if (!is_string($content)) {
            $this->fail('CHECK_FILE_READABLE', $this->relative($path));
            return null;
        }

        return $content;
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace('\\', '/', str_replace($this->root, '', $path)), '/');
    }

    private function ok(string $check, string $detail = ''): void
    {
        echo $check . '=OK' . ($detail !== '' ? ' ' . $detail : '') . PHP_EOL;
    }

    private function fail(string $check, string $detail): void
    {
        ++$this->failures;
        echo $check . '=FAIL ' . $detail . PHP_EOL;
    }
}

$root = dirname(__DIR__, 2);
$smoke = new P5BCurrentRuntimeLayoutSmoke($root);
exit($smoke->run());
