<?php
/**
 * OPUS P1B View/ScoreTemplate smoke.
 *
 * Scope:
 * - read-only runtime check;
 * - validates that Opus\View renders through ScoreTemplateRenderer and the
 *   framework-owned layout.score template;
 * - does not depend on real site packages.
 */
declare(strict_types=1);

final class P1BViewSmoke
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
        $this->loadFramework($root);
        $this->checkLayoutTemplate($root);
        $this->checkViewSource($root);
        $this->checkViewRuntimeRender($root);

        return $this->finish();
    }

    private function loadFramework(string $root): void
    {
        foreach ([
            'Opus/Score/TemplateException.php',
            'Opus/Score/TemplateRendererInterface.php',
            'Opus/Score/ScoreTemplateRenderer.php',
            'Opus/Support.php',
            'Opus/Request.php',
            'Opus/Response.php',
            'Opus/Package.php',
            'Opus/PackageRepository.php',
            'Opus/I18n.php',
            'Opus/View.php',
            'Opus/Acl.php',
            'Opus/Fsm.php',
            'Opus/Router.php',
            'Opus/Kernel.php',
        ] as $file) {
            $path = $root . '/' . $file;
            if (!is_file($path)) {
                $this->fail('CHECK_FRAMEWORK_FILE', 'Missing ' . $file);
                return;
            }
            require_once $path;
        }

        foreach ([
            'Opus\\Template\\ScoreTemplateRenderer',
            'Opus\\View',
            'Opus\\Kernel',
            'Opus\\Package',
            'Opus\\I18n',
        ] as $class) {
            if (!class_exists($class)) {
                $this->fail('CHECK_FRAMEWORK_CLASS', 'Missing class ' . $class);
                return;
            }
        }

        $this->ok('CHECK_FRAMEWORK_LOAD');
    }

    private function checkLayoutTemplate(string $root): void
    {
        $layout = $root . '/Opus/Score/templates/view/layout.score';
        if (!is_file($layout)) {
            $this->fail('CHECK_VIEW_LAYOUT_SCORE_EXISTS', 'Missing Opus/Score/templates/view/layout.score');
            return;
        }

        $source = (string) file_get_contents($layout);
        foreach ([
            '<html lang="{{ lang }}"',
            '{{{ nav.main }}}',
            '{{{ nav.switcher }}}',
            '{{{ nav.package }}}',
            '{{{ body.html }}}',
            '{{ footer.year }}',
        ] as $needle) {
            if (!str_contains($source, $needle)) {
                $this->fail('CHECK_VIEW_LAYOUT_SCORE_CONTRACT', 'Missing marker: ' . $needle);
                return;
            }
        }

        $this->ok('CHECK_VIEW_LAYOUT_SCORE_EXISTS');
        $this->ok('CHECK_VIEW_LAYOUT_SCORE_CONTRACT');
    }

    private function checkViewSource(string $root): void
    {
        $view = $root . '/Opus/View.php';
        $source = is_file($view) ? (string) file_get_contents($view) : '';

        foreach ([
            'ScoreTemplateRenderer',
            'renderLayout',
            '/Score/templates/view',
            "renderer->render('layout.score'",
        ] as $needle) {
            if (!str_contains($source, $needle)) {
                $this->fail('CHECK_VIEW_SCORE_TEMPLATE_SOURCE', 'Missing marker: ' . $needle);
                return;
            }
        }

        $this->ok('CHECK_VIEW_SCORE_TEMPLATE_SOURCE');
    }

    private function checkViewRuntimeRender(string $root): void
    {
        $tmpRoot = rtrim((string) sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'opus_p1b_view_smoke_' . getmypid();

        try {
            $this->createPackage($tmpRoot, 'logandplay', 'Log&Play');
            $this->createPackage($tmpRoot, 'demo', 'OPUS Demo');
            $this->createPackage($tmpRoot, 'maestro', 'Maestro');

            $kernel = new Opus\Kernel($tmpRoot);
            $package = $kernel->getPackage('logandplay');
            $page = $package->content()['fr']['home'];
            $view = new Opus\View($kernel, new Opus\I18n());
            $html = $view->render($package, 'fr', 'home', $page);

            foreach ([
                '<!doctype html>',
                '<html lang="fr"',
                '<header class="site-header">',
                '<main class="shell">',
                '<section class="hero">',
                '<section class="card-grid">',
                'Log&amp;Play',
                'OPUS runtime smoke',
            ] as $needle) {
                if (!str_contains($html, $needle)) {
                    $this->fail('CHECK_VIEW_RUNTIME_RENDER', 'Missing rendered marker: ' . $needle);
                    return;
                }
            }

            if (str_contains($html, '{{') || str_contains($html, '[[')) {
                $this->fail('CHECK_VIEW_RUNTIME_RENDER', 'Template markers leaked into rendered HTML.');
                return;
            }

            $this->ok('CHECK_VIEW_RUNTIME_RENDER');
        } finally {
            $this->removeDirectory($tmpRoot);
        }
    }

    private function createPackage(string $root, string $slug, string $name): void
    {
        $dir = $root . '/sites/' . $slug;
        $localDir = $dir . '/local';
        if (!is_dir($localDir) && !mkdir($localDir, 0777, true)) {
            throw new RuntimeException('Cannot create smoke package: ' . $slug);
        }

        $package = [
            'slug' => $slug,
            'name' => $name,
            'default_lang' => 'fr',
            'languages' => ['fr'],
            'domains' => [],
            'badge' => 'OPUS',
            'theme' => 'blue',
        ];

        file_put_contents($dir . '/package.php', '<?php return ' . var_export($package, true) . ';');
        file_put_contents($dir . '/routes.php', "<?php return ['fr' => ['' => 'home', 'framework' => 'framework']];");
        file_put_contents($localDir . '/fr.php', "<?php return ['nav.logandplay' => 'Log&Play', 'nav.demo' => 'Démo', 'nav.maestro' => 'Maestro'];");

        $content = [
            'fr' => [
                'home' => [
                    'title' => 'OPUS runtime smoke',
                    'description' => 'ScoreTemplate-backed View rendering smoke.',
                    'kicker' => 'framework',
                    'lead' => 'The View output is assembled through layout.score.',
                    'cards' => [
                        [
                            'title' => 'ScoreTemplate',
                            'text' => 'Layout rendering is active.',
                            'href' => 'framework',
                            'cta' => 'Voir',
                        ],
                    ],
                ],
                'framework' => [
                    'title' => 'Framework',
                    'nav' => 'Framework',
                    'lead' => 'Framework page.',
                ],
            ],
        ];
        file_put_contents($dir . '/content.php', '<?php return ' . var_export($content, true) . ';');
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
            echo "P1B_OPUS_VIEW_SCORETEMPLATE_SMOKE_FAIL\n";
            foreach ($this->failures as $failure) {
                echo ' - ' . $failure . "\n";
            }
            return 1;
        }

        echo "P1B_OPUS_VIEW_SCORETEMPLATE_SMOKE_OK\n";
        return 0;
    }
}

exit((new P1BViewSmoke())->run());
