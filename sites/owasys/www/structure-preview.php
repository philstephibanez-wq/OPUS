<?php
declare(strict_types=1);

use Opus\Owasys\RegistryRepository;
use Opus\Owasys\StructureDraftRepository;
use Opus\Owasys\StructureDraftWritePlanner;

$siteRoot = dirname(__DIR__);
$opusRoot = dirname(dirname($siteRoot));
$autoload = $opusRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo 'OWASYS_COMPOSER_AUTOLOAD_MISSING';
    exit;
}
require_once $autoload;

$h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$siteFile = $siteRoot . '/config/site.json';
$siteConfig = is_file($siteFile) ? json_decode((string) file_get_contents($siteFile), true) : null;
if (!is_array($siteConfig)) {
    http_response_code(500);
    echo 'OWASYS_STRUCTURE_PREVIEW_SITE_CONFIG_INVALID';
    exit;
}

$authConfig = is_array($siteConfig['auth'] ?? null) ? $siteConfig['auth'] : [];
$sessionName = (string) ($authConfig['session_name'] ?? 'OWASYS_LOCAL_SESSION');
if (preg_match('/^[A-Za-z0-9_-]+$/', $sessionName) !== 1) {
    http_response_code(500);
    echo 'OWASYS_STRUCTURE_PREVIEW_SESSION_NAME_INVALID';
    exit;
}
if (session_status() === PHP_SESSION_NONE) {
    session_name($sessionName);
    session_start();
}
if (!is_array($_SESSION['owasys_user'] ?? null)) {
    http_response_code(401);
    echo 'OWASYS_STRUCTURE_PREVIEW_AUTH_REQUIRED';
    exit;
}

$locales = array_values(array_filter((array) ($siteConfig['locales'] ?? ['fr']), 'is_string'));
$defaultLocale = in_array((string) ($siteConfig['default_locale'] ?? 'fr'), $locales, true) ? (string) ($siteConfig['default_locale'] ?? 'fr') : 'fr';
$requestedLocale = strtolower((string) ($_GET['lang'] ?? $_SESSION['owasys_locale'] ?? $defaultLocale));
$locale = in_array($requestedLocale, $locales, true) ? $requestedLocale : $defaultLocale;
$loadMessages = static function (string $locale) use ($siteRoot): array {
    $file = $siteRoot . '/application/default/local/' . $locale . '.php';
    if (!is_file($file)) {
        return [];
    }
    $messages = require $file;
    return is_array($messages) ? $messages : [];
};
$messages = array_replace($loadMessages('en'), $loadMessages($defaultLocale), $loadMessages($locale));
$t = static function (string $key) use (&$messages): string {
    $value = $messages[$key] ?? null;
    return is_string($value) && $value !== '' ? $value : $key;
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'OWASYS_STRUCTURE_PREVIEW_METHOD_INVALID';
    exit;
}
$draftId = filter_var($_POST['owasys_draft_id'] ?? null, FILTER_VALIDATE_INT);
if (!is_int($draftId) || $draftId < 1) {
    http_response_code(400);
    echo 'OWASYS_STRUCTURE_PREVIEW_DRAFT_ID_INVALID';
    exit;
}

$registryConfig = is_array($siteConfig['registry'] ?? null) ? $siteConfig['registry'] : [];
$registrySeedRelative = trim(str_replace('\\', '/', (string) ($registryConfig['seed'] ?? 'config/registry.seed.json')), '/');
$registryDatabaseRelative = trim(str_replace('\\', '/', (string) ($registryConfig['runtime_database'] ?? 'var/registry/owasys.sqlite')), '/');
if ($registrySeedRelative === '' || str_contains($registrySeedRelative, '..') || $registryDatabaseRelative === '' || str_contains($registryDatabaseRelative, '..')) {
    http_response_code(500);
    echo 'OWASYS_STRUCTURE_PREVIEW_REGISTRY_PATH_INVALID';
    exit;
}

try {
    $registry = RegistryRepository::forOwasysSite($siteRoot, $opusRoot, $registryDatabaseRelative);
    $registry->synchronize($siteRoot . '/' . $registrySeedRelative);
    $currentApp = is_array($_SESSION['owasys_current_app'] ?? null) ? $_SESSION['owasys_current_app'] : $registry->currentApplication();
    if (!is_array($currentApp)) {
        throw new RuntimeException('OWASYS_STRUCTURE_PREVIEW_CURRENT_APPLICATION_MISSING');
    }
    $draftRepository = StructureDraftRepository::forRegistry($registry);
    $draft = null;
    foreach ($draftRepository->recentDrafts((string) ($currentApp['id'] ?? ''), 50) as $candidate) {
        if (is_array($candidate) && (int) ($candidate['id'] ?? 0) === $draftId) {
            $draft = $candidate;
            break;
        }
    }
    if (!is_array($draft)) {
        throw new RuntimeException('OWASYS_STRUCTURE_PREVIEW_DRAFT_MISSING');
    }
    $plan = StructureDraftWritePlanner::forOpusRoot($opusRoot)->planAddStateDraft($currentApp, $draft);
} catch (Throwable $exception) {
    http_response_code(409);
    echo '<section class="ow-card" data-context="OWASYS_STRUCTURE_WRITE_PLAN_RESULT"><h2>' . $h($t('draft.preview_result')) . '</h2><p class="ow-login-error">' . $h($t('draft.preview_error')) . '</p></section>';
    exit;
}

$status = (string) ($plan['status'] ?? 'blocked');
$html = '<section class="ow-card" data-context="OWASYS_STRUCTURE_WRITE_PLAN_RESULT">';
$html .= '<h2>' . $h($t('draft.preview_result')) . '</h2>';
$html .= '<div class="ow-tags"><span data-context="OWASYS_STRUCTURE_WRITE_PLAN_STATUS">' . $h($t('draft.preview_status')) . ': ' . $h($status) . '</span><span>' . $h($t('draft.disk_mutation_false')) . '</span></div>';
$html .= '<ul>';
foreach ((array) ($plan['files'] ?? []) as $file) {
    if (!is_array($file)) {
        continue;
    }
    $html .= '<li data-context="OWASYS_STRUCTURE_WRITE_PLAN_FILE">' . $h((string) ($file['operation'] ?? '')) . ' · ' . $h((string) ($file['path'] ?? '')) . '</li>';
}
$html .= '</ul>';
if (($plan['collisions'] ?? []) !== []) {
    $html .= '<p class="ow-login-error">' . $h($t('draft.preview_collisions')) . ': ' . $h(implode(', ', array_map('strval', (array) $plan['collisions']))) . '</p>';
}
$html .= '</section>';

echo $html;
