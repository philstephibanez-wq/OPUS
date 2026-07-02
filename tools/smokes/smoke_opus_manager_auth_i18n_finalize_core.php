<?php
declare(strict_types=1);

echo 'OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE_SMOKE' . PHP_EOL;

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

$files = [
    $siteRoot . '/public/router.php',
    $siteRoot . '/src/Controller/AbstractOpusManagerController.php',
    $siteRoot . '/src/Controller/SignInController.php',
    $siteRoot . '/src/Controller/LogoutController.php',
    $siteRoot . '/src/Service/OpusManagerAuth.php',
    $siteRoot . '/src/Service/OpusManagerEnvironment.php',
    $siteRoot . '/src/Service/OpusManagerI18n.php',
    $siteRoot . '/config/languages.php',
    $root . '/DOC/OPUS_MANAGER_AUTH_I18N_FINALIZE.md',
    $root . '/DOC/P7_OPS_FINAL_CLOSURE_SCOPE.md',
];

$combined = '';
foreach ($files as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('OPUS_MANAGER_AUTH_I18N_FINALIZE_FILE_MISSING: ' . $file);
    }

    $source = file_get_contents($file);
    if (!is_string($source)) {
        throw new RuntimeException('OPUS_MANAGER_AUTH_I18N_FINALIZE_READ_FAILED: ' . $file);
    }

    $combined .= $source . PHP_EOL;
}

foreach ([
    'OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE',
    'OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE',
    'OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE',
    '/opus-manager/sign-in',
    '/opus-manager/login',
    '/opus-manager/signin',
    'OpusManagerAuth',
    'OpusManagerEnvironment',
    'OpusManagerI18n',
    'uk',
] as $marker) {
    if (!str_contains($combined, $marker)) {
        throw new RuntimeException('OPUS_MANAGER_AUTH_I18N_FINALIZE_MARKER_MISSING: ' . $marker);
    }
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['lang' => 'uk'];

$signin = (new \Opus\Manager\Controller\SignInController())->render(['lang' => 'uk', 'env' => 'dev']);

foreach ([
    'Sign in',
    'Українська',
    '<select name="lang"',
    'Dev : admin / admin',
] as $marker) {
    if (!str_contains($signin, $marker)) {
        throw new RuntimeException('OPUS_MANAGER_SIGNIN_FINALIZE_RENDER_MISSING: ' . $marker);
    }
}

if (str_contains($signin, 'Langue :')) {
    throw new RuntimeException('OPUS_MANAGER_SIGNIN_LANGUAGE_DUPLICATE');
}

if (!\Opus\Manager\Service\OpusManagerAuth::signIn('admin', 'admin')) {
    throw new RuntimeException('OPUS_MANAGER_DEV_AUTH_FAILED');
}

$site = (new \Opus\Manager\Controller\CreateSiteController())->render([
    'lang' => 'fr',
    'env' => 'dev',
    'signed_in' => true,
    'user' => 'admin',
]);

foreach ([
    'Créer un site avec OPUS',
    'StepTechnicalArchitecture',
    'Fullstack',
    'Frontend',
    'Backend',
    '<select id="om-lang-select"',
] as $marker) {
    if (!str_contains($site, $marker)) {
        throw new RuntimeException('OPUS_MANAGER_AUTH_SITE_RENDER_MISSING: ' . $marker);
    }
}

if (str_contains($site, 'Langue :')) {
    throw new RuntimeException('OPUS_MANAGER_SHELL_LANGUAGE_DUPLICATE');
}

$_GET = ['profiler' => '1'];
\Opus\Manager\Service\OpusManagerEnvironment::filterProfilerInput('prod');
if (isset($_GET['profiler'])) {
    throw new RuntimeException('OPUS_MANAGER_PROD_PROFILER_NOT_FILTERED');
}

echo 'CHECK_OPUS_MANAGER_AUTH_I18N_FINALIZE=OK' . PHP_EOL;
echo 'OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE_SMOKE_OK' . PHP_EOL;
