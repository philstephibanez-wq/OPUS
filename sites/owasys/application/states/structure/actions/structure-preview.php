<?php
declare(strict_types=1);

use Opus\Owasys\RegistryRepository;
use Opus\Owasys\StructureDraftPreviewConfirmation;
use Opus\Owasys\StructureDraftRepository;
use Opus\Owasys\StructureDraftWritePlanner;
use Owasys\Application\Configuration\SiteConfiguration;
use Owasys\Application\I18n\Translator;
use Owasys\Application\Session\SessionContext;

$siteRoot = dirname(__DIR__, 4);
$opusRoot = dirname(dirname($siteRoot));
$autoload = $opusRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo 'OWASYS_COMPOSER_AUTOLOAD_MISSING';
    exit;
}
require_once $autoload;

$h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

try {
    $configuration = SiteConfiguration::load($siteRoot);
    $authConfig = $configuration->auth();
    $session = new SessionContext((string) ($authConfig['session_name'] ?? 'OWASYS_LOCAL_SESSION'));
    $session->start();

    $requestedLocale = strtolower((string) ($_GET['lang'] ?? $session->locale($configuration->defaultLocale())));
    $translator = Translator::load(
        $siteRoot,
        $configuration->locales(),
        $configuration->defaultLocale(),
        $requestedLocale
    );
    $session->setLocale($translator->locale());
    $t = $translator(...);
} catch (Throwable $exception) {
    http_response_code(500);
    echo $exception->getMessage();
    exit;
}

$user = $session->user();
if ($user === null) {
    http_response_code(401);
    echo 'OWASYS_STRUCTURE_PREVIEW_AUTH_REQUIRED';
    exit;
}
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

$siteConfig = $configuration->site();
$registryConfig = is_array($siteConfig['registry'] ?? null) ? $siteConfig['registry'] : [];
$registrySeedRelative = trim(str_replace('\\', '/', (string) ($registryConfig['seed'] ?? 'config/registry.seed.json')), '/');
$registryDatabaseRelative = trim(str_replace('\\', '/', (string) ($registryConfig['runtime_database'] ?? 'var/registry/owasys.sqlite')), '/');
if ($registrySeedRelative === '' || str_contains($registrySeedRelative, '..') || $registryDatabaseRelative === '' || str_contains($registryDatabaseRelative, '..')) {
    http_response_code(500);
    echo 'OWASYS_STRUCTURE_PREVIEW_REGISTRY_PATH_INVALID';
    exit;
}

$confirmation = null;
try {
    $registry = RegistryRepository::forOwasysSite($siteRoot, $opusRoot, $registryDatabaseRelative);
    $registry->synchronize($siteRoot . '/' . $registrySeedRelative);
    $currentApp = $session->currentApplication() ?? $registry->currentApplication();
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
    if (($plan['status'] ?? null) === 'ready') {
        $confirmation = StructureDraftPreviewConfirmation::persist($registry, $plan, (string) ($user['id'] ?? 'runtime'));
    }
} catch (Throwable $exception) {
    http_response_code(409);
    echo '<section class="ow-card" data-context="OWASYS_STRUCTURE_WRITE_PLAN_RESULT"><h2>' . $h($t('draft.preview_result')) . '</h2><p class="ow-login-error">' . $h($t('draft.preview_error')) . '</p></section>';
    exit;
}

$status = (string) ($plan['status'] ?? 'blocked');
$html = '<section class="ow-card" data-context="OWASYS_STRUCTURE_WRITE_PLAN_RESULT">';
$html .= '<h2>' . $h($t('draft.preview_result')) . '</h2>';
$html .= '<div class="ow-tags"><span data-context="OWASYS_STRUCTURE_WRITE_PLAN_STATUS">' . $h($t('draft.preview_status')) . ': ' . $h($status) . '</span><span>' . $h($t('draft.disk_mutation_false')) . '</span></div>';
if (is_array($confirmation)) {
    $html .= '<p data-context="OWASYS_STRUCTURE_PREVIEW_CONFIRMED">' . $h($t('draft.preview_confirmed')) . '</p>';
}
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
