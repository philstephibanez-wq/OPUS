<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';

    if ($requestPath !== '/' && !str_contains($requestPath, "\0")) {
        $publicRoot = realpath(__DIR__);
        $relativePath = ltrim(str_replace('/', DIRECTORY_SEPARATOR, $requestPath), DIRECTORY_SEPARATOR);
        $candidate = realpath(__DIR__ . DIRECTORY_SEPARATOR . $relativePath);
        $frontController = realpath(__FILE__);

        if ($publicRoot !== false && $candidate !== false && $candidate !== $frontController) {
            $publicPrefix = rtrim(str_replace('\\', '/', $publicRoot), '/') . '/';
            $candidatePath = str_replace('\\', '/', $candidate);

            if (str_starts_with($candidatePath, $publicPrefix) && is_file($candidate)) {
                return false;
            }
        }
    }

    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = __FILE__;
}

$siteRoot = dirname(__DIR__);
$opusRoot = dirname(dirname($siteRoot));
$autoload = $opusRoot . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(500);
    exit('OWASYS_COMPOSER_AUTOLOAD_MISSING');
}

require_once $autoload;

$siteConfigFile = $siteRoot . '/config/site.json';
$siteConfig = is_file($siteConfigFile)
    ? json_decode((string) file_get_contents($siteConfigFile), true)
    : null;

if (!is_array($siteConfig)) {
    http_response_code(500);
    exit('OWASYS_SITE_CONFIG_INVALID');
}

require_once $siteRoot . '/application/default/Models/RuntimeUserStore.php';
require_once $siteRoot . '/application/default/Models/AuthSession.php';
require_once $siteRoot . '/application/default/Controllers/RuntimeController.php';

$auth = is_array($siteConfig['auth'] ?? null) ? $siteConfig['auth'] : [];
$userStoreRelative = trim(str_replace('\\', '/', (string) ($auth['user_store'] ?? '')), '/');

if ($userStoreRelative === '' || str_contains($userStoreRelative, '..')) {
    http_response_code(500);
    exit('OWASYS_AUTH_USER_STORE_PATH_INVALID');
}

$runtime = new OwasysRuntimeController(
    $siteRoot,
    $siteConfig,
    new OwasysRuntimeUserStore($siteRoot . '/' . $userStoreRelative),
    new OwasysAuthSession()
);

$runtime->run();
