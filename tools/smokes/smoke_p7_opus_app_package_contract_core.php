<?php
declare(strict_types=1);

use Opus\Application\Package\ApplicationPackageContract;
use Opus\Application\Package\ApplicationPackageManifest;
use Opus\Application\Package\ComposerApplicationPackageRepository;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

final class SmokeApplicationPackage
{
    private string $root;

    public function __construct()
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'opus_p7_app_package_contract_' . bin2hex(random_bytes(4));
    }

    public function run(): void
    {
        echo "P7_OPUS_APP_PACKAGE_CONTRACT_CORE_SMOKE\n";
        try {
            $this->mkdir($this->root);
            $manager = $this->createPackage('logandplay/opus-odbc-manager', 'opus-odbc-manager', 'OPUS ODBC Manager', true);
            $this->createPackage('logandplay/opus-ref-book', 'opus-ref-book', 'OPUS RefBook', false);
            $this->createPackage('logandplay/opus-demo', 'opus-demo', 'OPUS Demo', false);

            $contract = new ApplicationPackageContract();
            $manifest = $contract->validatePackageDirectory($manager);
            $this->assert($manifest instanceof ApplicationPackageManifest, 'CHECK_APP_PACKAGE_MANIFEST_OBJECT');
            $this->assert($manifest->packageName() === 'logandplay/opus-odbc-manager', 'CHECK_APP_PACKAGE_NAME');
            $this->assert($manifest->applicationSlug() === 'opus-odbc-manager', 'CHECK_APP_PACKAGE_SLUG');
            $this->assert($manifest->isProtected() === true, 'CHECK_APP_PACKAGE_PROTECTED');
            $this->assert($manifest->integrations()['scoretemplate'] === true, 'CHECK_APP_PACKAGE_SCORETEMPLATE');
            $this->assert($manifest->integrations()['i18n'] === true, 'CHECK_APP_PACKAGE_I18N');
            $this->assert($manifest->integrations()['sso_acl'] === true, 'CHECK_APP_PACKAGE_SSO_ACL');
            echo "CHECK_APP_PACKAGE_CONTRACT=OK\n";

            $repoRoot = $this->root . DIRECTORY_SEPARATOR . 'host';
            $this->mkdir($repoRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer');
            $installed = <<<'PHP'
<?php
return [
    'versions' => [
        'logandplay/opus-odbc-manager' => [
            'type' => 'opus-application',
            'install_path' => __DIR__ . '/../../packages/opus-odbc-manager',
        ],
        'logandplay/opus-ref-book' => [
            'type' => 'opus-application',
            'install_path' => __DIR__ . '/../../packages/opus-ref-book',
        ],
        'logandplay/opus-demo' => [
            'type' => 'opus-application',
            'install_path' => __DIR__ . '/../../packages/opus-demo',
        ],
        'example/not-opus-app' => [
            'type' => 'library',
            'install_path' => __DIR__ . '/../../packages/not-opus-app',
        ],
    ],
];
PHP;
            file_put_contents($repoRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.php', $installed);
            $this->mirrorPackage($manager, $repoRoot . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'opus-odbc-manager');
            $this->mirrorPackage($this->root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'opus-ref-book', $repoRoot . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'opus-ref-book');
            $this->mirrorPackage($this->root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'opus-demo', $repoRoot . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'opus-demo');

            $repository = new ComposerApplicationPackageRepository($repoRoot);
            $discovered = $repository->discover();
            $this->assert(count($discovered) === 3, 'CHECK_APP_PACKAGE_DISCOVERY_COUNT');
            $names = array_map(static fn (ApplicationPackageManifest $m): string => $m->packageName(), $discovered);
            $this->assert(in_array('logandplay/opus-odbc-manager', $names, true), 'CHECK_APP_PACKAGE_DISCOVERY_MANAGER');
            $this->assert(in_array('logandplay/opus-ref-book', $names, true), 'CHECK_APP_PACKAGE_DISCOVERY_REFBOOK');
            $this->assert(in_array('logandplay/opus-demo', $names, true), 'CHECK_APP_PACKAGE_DISCOVERY_DEMO');
            echo "CHECK_APP_PACKAGE_DISCOVERY=OK\n";

            $this->expectFailure(static function () use ($contract): void {
                $bad = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'opus_bad_package_' . bin2hex(random_bytes(4));
                mkdir($bad, 0777, true);
                file_put_contents($bad . DIRECTORY_SEPARATOR . 'composer.json', json_encode(['name' => 'logandplay/bad', 'type' => 'library', 'autoload' => ['psr-4' => ['Bad\\' => 'src/']]], JSON_PRETTY_PRINT));
                try {
                    $contract->validatePackageDirectory($bad);
                } finally {
                    self::rm($bad);
                }
            }, 'CHECK_APP_PACKAGE_REJECTS_NON_OPUS_TYPE');

            echo "P7_OPUS_APP_PACKAGE_CONTRACT_CORE_SMOKE_OK\n";
        } finally {
            self::rm($this->root);
        }
    }

    private function createPackage(string $packageName, string $slug, string $name, bool $protected): string
    {
        $dir = $this->root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . $slug;
        $this->mkdir($dir . DIRECTORY_SEPARATOR . 'src');
        $this->mkdir($dir . DIRECTORY_SEPARATOR . 'app');
        $this->mkdir($dir . DIRECTORY_SEPARATOR . 'templates');
        $this->mkdir($dir . DIRECTORY_SEPARATOR . 'i18n');
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'composer.json', json_encode([
            'name' => $packageName,
            'type' => 'opus-application',
            'autoload' => ['psr-4' => [$this->namespaceFromSlug($slug) . '\\' => 'src/']],
            'extra' => ['opus' => ['application_manifest' => 'opus.application.json']],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'opus.application.json', json_encode([
            'contract' => ApplicationPackageManifest::CONTRACT_ID,
            'package' => $packageName,
            'application' => ['slug' => $slug, 'name' => $name],
            'paths' => ['application' => 'app', 'routes' => 'app/routes.php', 'views' => 'templates', 'i18n' => 'i18n'],
            'integrations' => ['scoretemplate' => true, 'i18n' => true, 'sso_acl' => true, 'diagnostics' => true, 'profiler' => true],
            'security' => ['protected' => $protected],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'routes.php', "<?php\nreturn [];\n");

        return $dir;
    }

    private function namespaceFromSlug(string $slug): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
    }

    private function mirrorPackage(string $source, string $target): void
    {
        $this->mkdir($target);
        foreach (['composer.json', 'opus.application.json'] as $file) {
            copy($source . DIRECTORY_SEPARATOR . $file, $target . DIRECTORY_SEPARATOR . $file);
        }
        foreach (['src', 'app', 'templates', 'i18n'] as $dir) {
            $this->mkdir($target . DIRECTORY_SEPARATOR . $dir);
        }
        copy($source . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'routes.php', $target . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'routes.php');
    }

    private function mkdir(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            throw new RuntimeException('MKDIR_FAILED: ' . $path);
        }
    }

    private function assert(bool $ok, string $label): void
    {
        if (!$ok) {
            throw new RuntimeException($label . '=FAIL');
        }
        echo $label . "=OK\n";
    }

    private function expectFailure(callable $callback, string $label): void
    {
        try {
            $callback();
        } catch (Throwable) {
            echo $label . "=OK\n";
            return;
        }
        throw new RuntimeException($label . '=FAIL');
    }

    private static function rm(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            self::rm($path . DIRECTORY_SEPARATOR . $item);
        }
        @rmdir($path);
    }
}

(new SmokeApplicationPackage())->run();
