<?php
declare(strict_types=1);

echo 'OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$siteRoot = $root . '/sites/opus-manager';

spl_autoload_register(static function (string $class) use ($siteRoot): void {
    $prefix = 'Opus\\Manager\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $siteRoot . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['lang' => 'uk', 'profiler' => '1'];

\Opus\Manager\Service\OpusManagerEnvironment::filterProfilerInput('prod');

if (isset($_GET['profiler'])) {
    throw new RuntimeException('OPUS_MANAGER_PROD_PROFILER_NOT_FILTERED');
}

$html = (new \Opus\Manager\Controller\SignInController())->render(['lang' => 'uk', 'env' => 'prod']);

foreach ([
    'Sign in',
    'Українська',
    '<select name="lang"',
    'admin / admin',
] as $marker) {
    if (!str_contains($html, $marker)) {
        throw new RuntimeException('OPUS_MANAGER_AUTH_I18N_MARKER_MISSING: ' . $marker);
    }
}

if (str_contains($html, 'Langue :')) {
    throw new RuntimeException('OPUS_MANAGER_LANGUAGE_DUPLICATE_VISIBLE');
}

echo 'OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE_SMOKE_OK' . PHP_EOL;