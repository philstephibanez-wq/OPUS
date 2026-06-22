<?php
/**
 * OPUS P1 boot/render smoke.
 *
 * Scope:
 * - read-only runtime check;
 * - no framework mutation;
 * - validates OPUS bootstrap/core class loading;
 * - validates ScoreTemplateRenderer on an isolated temporary template root;
 * - explicitly reports whether View.php is already wired to ScoreTemplateRenderer.
 */
declare(strict_types=1);

final class P1Smoke
{
    /** @var list<string> */
    private array $failures = [];

    public function run(): int
    {
        $root = realpath(__DIR__ . '/../..');
        if ($root === false) {
            $this->fail('CHECK_REPO_ROOT', 'Unable to resolve repository root.');
            return $this->finish();
        }

        $this->ok('CHECK_REPO_ROOT');
        $this->checkPhpVersion();
        $this->checkBootstrap($root);
        $this->checkCoreClasses($root);
        $this->checkScoreTemplateRenderer($root);
        $this->checkViewScoreTemplateIntegration($root);

        return $this->finish();
    }

    private function checkPhpVersion(): void
    {
        if (PHP_VERSION_ID < 80000) {
            $this->fail('CHECK_PHP_VERSION', 'PHP 8.0+ required, got ' . PHP_VERSION);
            return;
        }
        $this->ok('CHECK_PHP_VERSION');
    }

    private function checkBootstrap(string $root): void
    {
        $bootstrap = $root . '/Opus/Bootstrap.php';
        if (!is_file($bootstrap)) {
            $this->fail('CHECK_BOOTSTRAP_FILE', 'Missing Opus/Bootstrap.php');
            return;
        }

        require_once $bootstrap;

        if (!class_exists('Opus\\Bootstrap')) {
            $this->fail('CHECK_BOOTSTRAP_CLASS', 'Class Opus\\Bootstrap not loaded.');
            return;
        }

        $source = (string) file_get_contents($bootstrap);
        if (!str_contains($source, "'/Opus/'")) {
            $this->fail('CHECK_BOOTSTRAP_LOAD_PATH', 'Bootstrap must load from /Opus/.');
            return;
        }

        $this->ok('CHECK_BOOTSTRAP_FILE');
        $this->ok('CHECK_BOOTSTRAP_CLASS');
        $this->ok('CHECK_BOOTSTRAP_LOAD_PATH');
    }

    private function checkCoreClasses(string $root): void
    {
        foreach ([
            'Support.php',
            'Request.php',
            'Response.php',
            'Package.php',
            'PackageRepository.php',
            'I18n.php',
            'View.php',
            'Acl.php',
            'Fsm.php',
            'Router.php',
            'Kernel.php',
        ] as $file) {
            $path = $root . '/Opus/' . $file;
            if (!is_file($path)) {
                $this->fail('CHECK_CORE_FILE_' . strtoupper(str_replace(['.', '-'], '_', $file)), 'Missing ' . $path);
                return;
            }
            require_once $path;
        }

        foreach ([
            'Opus\\Support',
            'Opus\\Request',
            'Opus\\Response',
            'Opus\\Package',
            'Opus\\PackageRepository',
            'Opus\\I18n',
            'Opus\\View',
            'Opus\\Acl',
            'Opus\\Fsm',
            'Opus\\Router',
            'Opus\\Kernel',
        ] as $class) {
            if (!class_exists($class)) {
                $this->fail('CHECK_CORE_CLASS_' . str_replace('\\', '_', strtoupper($class)), 'Missing class ' . $class);
                return;
            }
        }

        $this->ok('CHECK_CORE_CLASSES');
    }

    private function checkScoreTemplateRenderer(string $root): void
    {
        foreach ([
            'TemplateException.php',
            'TemplateRendererInterface.php',
            'ScoreTemplateRenderer.php',
        ] as $file) {
            $path = $root . '/Opus/Score/' . $file;
            if (!is_file($path)) {
                $this->fail('CHECK_SCORE_FILE_' . strtoupper(str_replace('.', '_', $file)), 'Missing ' . $path);
                return;
            }
            require_once $path;
        }

        if (!interface_exists('Opus\\Template\\TemplateRendererInterface')) {
            $this->fail('CHECK_SCORE_RENDERER_INTERFACE', 'TemplateRendererInterface not loaded.');
            return;
        }
        if (!class_exists('Opus\\Template\\ScoreTemplateRenderer')) {
            $this->fail('CHECK_SCORE_RENDERER_CLASS', 'ScoreTemplateRenderer not loaded.');
            return;
        }

        $tmpRoot = rtrim((string) sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'opus_p1_score_smoke_' . getmypid();
        $partialDir = $tmpRoot . DIRECTORY_SEPARATOR . 'partials';

        try {
            if (!is_dir($partialDir) && !mkdir($partialDir, 0777, true)) {
                $this->fail('CHECK_SCORE_TEMP_ROOT', 'Cannot create temporary template root.');
                return;
            }

            file_put_contents($partialDir . DIRECTORY_SEPARATOR . 'item.score', '<li>{{ item }}</li>');
            file_put_contents(
                $tmpRoot . DIRECTORY_SEPARATOR . 'page.score',
                "[[ ignore: internal note ]]SHOULD_NOT_RENDER[[ endignore ]]<main><h1>{{ title }}</h1><div>{{{ body.html }}}</div><ul>[[ foreach: items as item ]][[ include:partials/item.score ]][[ endforeach ]]</ul></main>"
            );

            $renderer = new Opus\Template\ScoreTemplateRenderer($tmpRoot);
            $html = $renderer->render('page.score', [
                'title' => '<OPUS>',
                'body' => ['html' => '<strong>OK</strong>'],
                'items' => ['one', 'two'],
            ]);

            $checks = [
                '&lt;OPUS&gt;' => 'escaped interpolation',
                '<strong>OK</strong>' => 'raw interpolation',
                '<li>one</li>' => 'foreach/include first item',
                '<li>two</li>' => 'foreach/include second item',
            ];

            foreach ($checks as $needle => $label) {
                if (!str_contains($html, $needle)) {
                    $this->fail('CHECK_SCORE_TEMPLATE_RENDER', 'Missing ' . $label . ': ' . $needle);
                    return;
                }
            }

            if (str_contains($html, 'SHOULD_NOT_RENDER')) {
                $this->fail('CHECK_SCORE_TEMPLATE_IGNORE', 'Ignored block was rendered.');
                return;
            }

            $this->ok('CHECK_SCORE_RENDERER_INTERFACE');
            $this->ok('CHECK_SCORE_RENDERER_CLASS');
            $this->ok('CHECK_SCORE_TEMPLATE_RENDER');
        } finally {
            $this->removeDirectory($tmpRoot);
        }
    }

    private function checkViewScoreTemplateIntegration(string $root): void
    {
        $view = $root . '/Opus/View.php';
        $source = is_file($view) ? (string) file_get_contents($view) : '';

        if (!str_contains($source, 'ScoreTemplateRenderer')) {
            $this->fail('CHECK_VIEW_SCORE_TEMPLATE_INTEGRATION', 'Opus/View.php does not reference ScoreTemplateRenderer yet.');
            return;
        }

        $this->ok('CHECK_VIEW_SCORE_TEMPLATE_INTEGRATION');
    }

    private function removeDirectory(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function ok(string $check): void
    {
        echo $check . "=OK\n";
    }

    private function fail(string $check, string $message): void
    {
        $this->failures[] = $check . ': ' . $message;
        echo $check . "=FAIL " . $message . "\n";
    }

    private function finish(): int
    {
        if ($this->failures !== []) {
            echo "P1_OPUS_BOOT_RENDER_SMOKE_FAIL\n";
            foreach ($this->failures as $failure) {
                echo ' - ' . $failure . "\n";
            }
            return 1;
        }

        echo "P1_OPUS_BOOT_RENDER_SMOKE_OK\n";
        return 0;
    }
}

exit((new P1Smoke())->run());
